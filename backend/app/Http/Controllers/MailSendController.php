<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailAttachment;
use App\Models\MailMessage;
use App\Models\MailSignature;
use App\Services\Mail\ComposedMessage;
use App\Services\Mail\MailSender;
use App\Support\BlobStore;
use App\Support\Mail\MailHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Compose / reply / forward — the SMTP send side of the mail archive (design
 * §2.7 item 8, the one deferred functional gap). Owner-scoped (404-not-403 on a
 * foreign account/message), module:mail-gated, throttled. Sends over the
 * account's OWN encrypted SMTP credentials (MailSender) and appends the sent
 * copy back to the origin Sent folder — a deliberate, per-action write-to-origin
 * (like pushback/delete-origin). SMTP send is opt-in: an account with no SMTP
 * configured returns `no_smtp`.
 *
 * The archived sent copy is created only via the IMAP Sent-append + the next
 * pull sync (the normal ingest path) — nothing here writes the immutable
 * mail_messages ledger directly, so the archive stays a faithful copy of origin.
 */
class MailSendController extends Controller
{
    private const MAX_RECIPIENTS = 50;

    private const MAX_ATTACHMENTS = 20;

    private const MAX_TOTAL_BYTES = 25 * 1024 * 1024;

    private const MAX_BODY = 1_000_000;

    private const MAX_SUBJECT = 998;

    /** Compose a fresh message from a chosen account. */
    public function compose(Request $request, MailSender $sender): JsonResponse
    {
        $user = $this->requireUser($request);

        if ($resp = $this->guard($request, [
            'account_id' => ['required', 'integer'],
            'to' => ['array', 'max:'.self::MAX_RECIPIENTS],
            'to.*' => ['email:rfc'],
            'cc' => ['array', 'max:'.self::MAX_RECIPIENTS],
            'cc.*' => ['email:rfc'],
            'bcc' => ['array', 'max:'.self::MAX_RECIPIENTS],
            'bcc.*' => ['email:rfc'],
            'subject' => ['nullable', 'string', 'max:'.self::MAX_SUBJECT],
            'text' => ['nullable', 'string', 'max:'.self::MAX_BODY],
            'html' => ['nullable', 'string', 'max:'.self::MAX_BODY],
            'signature_id' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'max:25600'],
            'attachment_ids' => ['nullable', 'array', 'max:'.self::MAX_ATTACHMENTS],
            'attachment_ids.*' => ['uuid'],
            'sent_folder' => ['nullable', 'string', 'max:255'],
        ])) {
            return $resp;
        }

        $account = MailAccount::query()->whereKey($request->integer('account_id'))->first();
        if ($account === null || (int) $account->user_id !== (int) $user->id) {
            abort(404);
        }
        if (! $account->hasSmtp()) {
            return $this->fail('no_smtp');
        }

        $text = $this->body($request, 'text');
        $html = $this->body($request, 'html');
        if ($text === null && $html === null) {
            return $this->fail('empty_body');
        }
        [$text, $html] = $this->withSignature($account, $text, $html, $request->integer('signature_id'));

        $composed = new ComposedMessage(
            subject: $this->subject($request->string('subject')->value(), ''),
            text: $text,
            html: $html,
            fromEmail: '', // set by MailSender from the account
            fromName: null,
            to: $this->addresses($request->input('to')),
            cc: $this->addresses($request->input('cc')),
            bcc: $this->addresses($request->input('bcc')),
            attachments: $this->gatherAttachments($request, (int) $user->id),
            sentFolder: $this->sentFolder($request),
        );

        if (! $composed->hasRecipient()) {
            return $this->fail('no_recipient');
        }

        return $this->dispatch($sender, $account, $composed);
    }

    /** Reply to an archived message (quotes the original; Re: subject; threaded). */
    public function reply(Request $request, MailMessage $message, MailSender $sender): JsonResponse
    {
        $user = $this->requireUser($request);
        $this->authorizeMessage($message, $user->id);

        if ($resp = $this->guard($request, [
            'text' => ['nullable', 'string', 'max:'.self::MAX_BODY],
            'html' => ['nullable', 'string', 'max:'.self::MAX_BODY],
            'signature_id' => ['nullable', 'integer'],
            'all' => ['nullable', 'boolean'],
            'sent_folder' => ['nullable', 'string', 'max:255'],
        ])) {
            return $resp;
        }

        $account = $this->sendableAccount($message);
        if (! $account instanceof MailAccount) {
            return $this->fail($account);
        }

        $recipient = $this->replyRecipient($message);
        if ($recipient === null) {
            return $this->fail('no_recipient');
        }

        $text = $this->body($request, 'text');
        $html = $this->body($request, 'html');
        if ($text === null && $html === null) {
            return $this->fail('empty_body');
        }
        [$text, $html] = $this->withSignature($account, $text, $html, $request->integer('signature_id'));
        $text = $this->appendQuote($text, $message);
        $html = $this->appendQuoteHtml($html, $message);

        $cc = [];
        if ($request->boolean('all')) {
            $cc = $this->replyAllCc($message, (string) $account->from_email, (string) $account->username, $recipient);
        }

        $composed = new ComposedMessage(
            subject: $this->subject((string) $message->subject, 'Re: '),
            text: $text,
            html: $html,
            fromEmail: '',
            fromName: null,
            to: [['name' => null, 'email' => $recipient]],
            cc: $cc,
            inReplyTo: $message->message_id,
            references: $this->references($message),
            sentFolder: $this->sentFolder($request),
        );

        return $this->dispatch($sender, $account, $composed);
    }

    /** Forward an archived message to new recipients (attaches the original .eml). */
    public function forward(Request $request, MailMessage $message, MailSender $sender): JsonResponse
    {
        $user = $this->requireUser($request);
        $this->authorizeMessage($message, $user->id);

        if ($resp = $this->guard($request, [
            'to' => ['required', 'array', 'min:1', 'max:'.self::MAX_RECIPIENTS],
            'to.*' => ['email:rfc'],
            'cc' => ['nullable', 'array', 'max:'.self::MAX_RECIPIENTS],
            'cc.*' => ['email:rfc'],
            'text' => ['nullable', 'string', 'max:'.self::MAX_BODY],
            'html' => ['nullable', 'string', 'max:'.self::MAX_BODY],
            'signature_id' => ['nullable', 'integer'],
            'sent_folder' => ['nullable', 'string', 'max:255'],
        ])) {
            return $resp;
        }

        $account = $this->sendableAccount($message);
        if (! $account instanceof MailAccount) {
            return $this->fail($account);
        }

        $text = $this->body($request, 'text');
        $html = $this->body($request, 'html');
        [$text, $html] = $this->withSignature($account, $text, $html, $request->integer('signature_id'));
        $text = $this->prependForwardHeader($text ?? '', $message);

        $composed = new ComposedMessage(
            subject: $this->subject((string) $message->subject, 'Fwd: '),
            text: $text,
            html: $html,
            fromEmail: '',
            fromName: null,
            to: $this->addresses($request->input('to')),
            cc: $this->addresses($request->input('cc')),
            attachments: $this->originalEml($message),
            sentFolder: $this->sentFolder($request),
        );

        return $this->dispatch($sender, $account, $composed);
    }

    // ---- helpers ----

    /** Hand off to MailSender, mapping a config/SSRF failure to a generic 502. */
    private function dispatch(MailSender $sender, MailAccount $account, ComposedMessage $composed): JsonResponse
    {
        try {
            $result = $sender->send($account, $composed);
        } catch (RuntimeException) {
            return response()->json(['ok' => false, 'error' => 'send_failed'], 502)
                ->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'ok' => true,
            'message_id' => $result->messageId,
            'appended_to_sent' => $result->appendedToSent,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * The SMTP-capable origin account for a reply/forward, or an error code
     * string (account_deleted / no_smtp) when it cannot send.
     */
    private function sendableAccount(MailMessage $message): MailAccount|string
    {
        $account = $message->account;
        if (! $account instanceof MailAccount) {
            return 'account_deleted';
        }
        if (! $account->hasSmtp()) {
            return 'no_smtp';
        }

        return $account;
    }

    /** Reply recipient: the message's Reply-To if set, else its From. */
    private function replyRecipient(MailMessage $message): ?string
    {
        $replyTo = is_string($message->reply_to) && trim($message->reply_to) !== '' ? trim($message->reply_to) : null;
        $from = is_string($message->from_email) && trim($message->from_email) !== '' ? trim($message->from_email) : null;

        return $replyTo ?? $from;
    }

    /**
     * Reply-all Cc = original To + Cc, minus our own address(es) and the primary
     * recipient (deduped, capped).
     *
     * @return list<array{name:?string, email:string}>
     */
    private function replyAllCc(MailMessage $message, string $selfFrom, string $selfUser, string $primary): array
    {
        $skip = array_map('strtolower', array_filter([$selfFrom, $selfUser, $primary]));
        $seen = [];
        $out = [];
        foreach ([$message->to_json ?? [], $message->cc_json ?? []] as $list) {
            foreach ($list as $addr) {
                $email = is_array($addr) && isset($addr['email']) && is_string($addr['email']) ? trim($addr['email']) : '';
                $key = strtolower($email);
                if ($email === '' || in_array($key, $skip, true) || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $name = isset($addr['name']) && is_string($addr['name']) ? $addr['name'] : null;
                $out[] = ['name' => $name, 'email' => $email];
                if (count($out) >= self::MAX_RECIPIENTS) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * The RFC822 References chain for a reply: the original References plus its
     * Message-Id (deduped, capped).
     *
     * @return list<string>
     */
    private function references(MailMessage $message): array
    {
        $refs = [];
        if (is_string($message->references) && trim($message->references) !== '') {
            foreach (preg_split('/\s+/', trim($message->references)) ?: [] as $r) {
                if ($r !== '') {
                    $refs[] = $r;
                }
            }
        }
        if (is_string($message->message_id) && $message->message_id !== '') {
            $refs[] = $message->message_id;
        }

        return array_values(array_slice(array_unique($refs), -20));
    }

    /**
     * Validated recipient emails → address list. `email:rfc` has already
     * rejected malformed values.
     *
     * @return list<array{name:?string, email:string}>
     */
    private function addresses(mixed $emails): array
    {
        if (! is_array($emails)) {
            return [];
        }
        $out = [];
        foreach ($emails as $email) {
            if (is_string($email) && trim($email) !== '') {
                $out[] = ['name' => null, 'email' => trim($email)];
            }
        }

        return $out;
    }

    /** A body field, trimmed, or null when blank. */
    private function body(Request $request, string $key): ?string
    {
        $value = $request->input($key);
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $value;
    }

    /** Subject with an optional Re:/Fwd: prefix, avoiding a double prefix. */
    private function subject(?string $subject, string $prefix): string
    {
        $base = is_string($subject) ? trim($subject) : '';
        if ($base === '') {
            $base = '(no subject)';
        }
        if ($prefix !== '' && ! str_starts_with(mb_strtolower($base), mb_strtolower($prefix))) {
            $base = $prefix.$base;
        }

        return mb_substr($base, 0, self::MAX_SUBJECT);
    }

    /**
     * Append the selected account signature to the text/html bodies.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function withSignature(MailAccount $account, ?string $text, ?string $html, int $signatureId = 0): array
    {
        $signature = MailSignature::query()
            ->ownedBy((int) $account->user_id)
            ->when($signatureId > 0, fn ($query) => $query->whereKey($signatureId))
            ->whereHas('accounts', fn ($query) => $query->where('mail_accounts.id', $account->id)->when($signatureId === 0, fn ($query) => $query->where('mail_account_signatures.is_default', true)))
            ->first();
        $htmlSignature = $signature instanceof MailSignature ? (new MailHtmlSanitizer)->sanitize($signature->html, true) : null;
        if ($htmlSignature === null) {
            return [$text, $html];
        }
        $plainSignature = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $htmlSignature))));
        if ($text !== null) {
            $text .= "\n\n-- \n".$plainSignature;
        }
        if ($html !== null) {
            $html .= '<br><br>-- <br>'.$htmlSignature;
        }

        return [$text, $html];
    }

    /** Append the quoted original text body to a reply's text part. */
    private function appendQuote(?string $text, MailMessage $message): ?string
    {
        $orig = is_string($message->text_body) ? trim($message->text_body) : '';
        if ($orig === '') {
            return $text;
        }
        $attribution = $this->attribution($message);
        $quoted = implode("\n", array_map(static fn (string $l): string => '> '.$l, preg_split('/\r\n|\r|\n/', $orig) ?: []));

        return ($text ?? '')."\n\n".$attribution."\n".$quoted;
    }

    /** Append an escaped, blockquoted original body to a reply's HTML part. */
    private function appendQuoteHtml(?string $html, MailMessage $message): ?string
    {
        if ($html === null) {
            return null;
        }
        $orig = is_string($message->text_body) ? trim($message->text_body) : '';
        if ($orig === '') {
            return $html;
        }

        return $html.'<br><br>'.e($this->attribution($message))
            .'<blockquote style="margin:0 0 0 .8ex;border-left:2px solid #ccc;padding-left:1ex">'
            .nl2br(e($orig)).'</blockquote>';
    }

    /** A one-line "On <date>, <from> wrote:" attribution for a quoted reply. */
    private function attribution(MailMessage $message): string
    {
        $who = is_string($message->from_name) && trim($message->from_name) !== ''
            ? trim($message->from_name)
            : (is_string($message->from_email) ? (string) $message->from_email : 'someone');
        $when = $message->date instanceof Carbon ? $message->date->toDayDateTimeString() : '';

        return trim(($when !== '' ? 'On '.$when.', ' : 'On a previous date, ').$who.' wrote:');
    }

    /** Prepend the standard forwarded-message header block to the text body. */
    private function prependForwardHeader(string $text, MailMessage $message): string
    {
        $to = implode(', ', array_map(
            static fn (array $a): string => is_string($a['email'] ?? null) ? $a['email'] : '',
            is_array($message->to_json) ? $message->to_json : []
        ));
        $block = "---------- Forwarded message ----------\n"
            .'From: '.$this->fromLine($message)."\n"
            .'Date: '.($message->date instanceof Carbon ? $message->date->toDayDateTimeString() : '')."\n"
            .'Subject: '.(is_string($message->subject) ? $message->subject : '')."\n"
            .'To: '.$to."\n";
        $body = is_string($message->text_body) ? $message->text_body : '';

        return ($text !== '' ? $text."\n\n" : '').$block."\n".$body;
    }

    private function fromLine(MailMessage $message): string
    {
        $name = is_string($message->from_name) ? trim($message->from_name) : '';
        $email = is_string($message->from_email) ? trim($message->from_email) : '';

        return $name !== '' && $email !== '' ? $name.' <'.$email.'>' : ($email !== '' ? $email : $name);
    }

    /**
     * The immutable original .eml as a single forward attachment (message/rfc822)
     * — it carries the original's own attachments intact. Empty when the raw blob
     * is missing.
     *
     * @return list<array{bytes:string, filename:string, mime:string}>
     */
    private function originalEml(MailMessage $message): array
    {
        $raw = BlobStore::disk()->get('mail/'.$message->id);
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $name = is_string($message->subject) && trim($message->subject) !== ''
            ? $this->safeName(trim($message->subject))
            : 'forwarded';

        return [['bytes' => $raw, 'filename' => $name.'.eml', 'mime' => 'message/rfc822']];
    }

    /**
     * Uploaded files + references to the caller's own archived attachments →
     * attachment specs, enforcing the count + total-size caps.
     *
     * @return list<array{bytes:string, filename:string, mime:string}>
     */
    private function gatherAttachments(Request $request, int $userId): array
    {
        $out = [];
        $total = 0;

        $files = $request->file('attachments');
        $files = is_array($files) ? $files : ($files instanceof UploadedFile ? [$files] : []);
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $bytes = (string) file_get_contents($file->getRealPath());
            $total += strlen($bytes);
            if (count($out) >= self::MAX_ATTACHMENTS || $total > self::MAX_TOTAL_BYTES) {
                break;
            }
            $out[] = [
                'bytes' => $bytes,
                'filename' => $this->safeName($file->getClientOriginalName()),
                'mime' => $file->getClientMimeType() !== '' ? $file->getClientMimeType() : 'application/octet-stream',
            ];
        }

        $ids = $request->input('attachment_ids');
        if (is_array($ids) && $ids !== []) {
            $rows = MailAttachment::query()
                ->where('user_id', $userId)
                ->whereIn('id', array_values(array_filter($ids, 'is_string')))
                ->get();
            $disk = BlobStore::disk();
            foreach ($rows as $att) {
                if (count($out) >= self::MAX_ATTACHMENTS) {
                    break;
                }
                $bytes = $disk->get('mail/att/'.$att->blob);
                if (! is_string($bytes) || $bytes === '') {
                    continue;
                }
                $total += strlen($bytes);
                if ($total > self::MAX_TOTAL_BYTES) {
                    break;
                }
                $out[] = [
                    'bytes' => $bytes,
                    'filename' => $this->safeName(is_string($att->filename) && $att->filename !== '' ? $att->filename : 'attachment'),
                    'mime' => is_string($att->content_type) && $att->content_type !== '' ? $att->content_type : 'application/octet-stream',
                ];
            }
        }

        return $out;
    }

    /** A filesystem-safe single-segment filename (no path, control chars, capped). */
    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name) ?? '';
        $name = trim($name);

        return $name === '' ? 'attachment' : mb_substr($name, 0, 255);
    }

    private function sentFolder(Request $request): string
    {
        $value = $request->input('sent_folder');

        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 255) : 'Sent';
    }

    private function fail(string $error): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $error], 422)->header('Cache-Control', 'no-store');
    }

    /**
     * Validate and, on failure, return a JSON 422 (never a thrown
     * ValidationException — which on the web/session route renders as a 302
     * redirect instead of the JSON contract the app + mobile expect). Returns
     * null when validation passes.
     *
     * @param  array<string, array<int, string>>  $rules
     */
    private function guard(Request $request, array $rules): ?JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'validation',
                'errors' => $validator->errors()->toArray(),
            ], 422)->header('Cache-Control', 'no-store');
        }

        return null;
    }

    private function authorizeMessage(MailMessage $message, int $userId): void
    {
        abort_if((int) $message->user_id !== $userId, 404);
    }
}
