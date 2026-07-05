<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\Photo;
use App\Models\StoredFile;
use App\Services\Mail\MailSource;
use App\Services\Mail\SmtpSender;
use App\Support\BlobStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Composing and sending mail over SMTP: send, save draft. Attachments come from
 * a direct upload, the gallery, or the files browser. A sent copy is appended to
 * the IMAP Sent folder (best effort) so it shows on the next sync and on other
 * clients.
 */
class MailComposeController extends Controller
{
    /** Cap total attachment bytes to protect the worker from OOM. */
    private const MAX_ATTACH_BYTES = 25 * 1024 * 1024;

    public function send(Request $request, SmtpSender $sender, MailSource $source): JsonResponse
    {
        $data = $this->validated($request);
        $account = $this->account($request, $data['account_id']);
        $msg = $this->message($request, $account, $data);

        try {
            $raw = $sender->send($account->smtpConfig(), $msg);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => __('mail.send_failed')], 422);
        }

        // Keep a copy in Sent so it appears on the next sync and on other clients.
        $this->appendSilently($source, $account, ['Sent', 'INBOX.Sent', 'Sent Items', '[Gmail]/Sent Mail'], $raw);

        return response()->json(['ok' => true]);
    }

    public function draft(Request $request, SmtpSender $sender, MailSource $source): JsonResponse
    {
        $data = $this->validated($request);
        $account = $this->account($request, $data['account_id']);
        $raw = $sender->build($account->smtpConfig(), $this->message($request, $account, $data))->toString();

        $this->appendSilently($source, $account, ['Drafts', 'INBOX.Drafts', '[Gmail]/Drafts'], $raw);

        return response()->json(['ok' => true]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'account_id' => ['required', 'integer'],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['email'],
            'cc' => ['array'],
            'cc.*' => ['email'],
            'bcc' => ['array'],
            'bcc.*' => ['email'],
            'subject' => ['nullable', 'string', 'max:998'],
            'body' => ['nullable', 'string'],
            'in_reply_to' => ['nullable', 'string', 'max:998'],
            'references' => ['nullable', 'string', 'max:4000'],
            'refs' => ['array'],
            'refs.*.type' => ['required_with:refs.*.id', Rule::in(['gallery', 'file'])],
            'refs.*.id' => ['required_with:refs.*.type'],
            'uploads' => ['array'],
            'uploads.*' => ['file', 'max:'.(self::MAX_ATTACH_BYTES / 1024)],
        ]);
    }

    private function account(Request $request, int $id): MailAccount
    {
        $account = MailAccount::withoutGlobalScopes()->findOrFail($id);
        abort_unless($account->isOwnedBy($request->user()->id), 403);

        return $account;
    }

    /** @return array<string,mixed> */
    private function message(Request $request, MailAccount $account, array $data): array
    {
        $cfg = $account->smtpConfig();

        return [
            'from_email' => $cfg['from_email'],
            'from_name' => $cfg['from_name'],
            'to' => $data['to'],
            'cc' => $data['cc'] ?? [],
            'bcc' => $data['bcc'] ?? [],
            'subject' => $data['subject'] ?? '',
            'html' => $data['body'] ?? '',
            'text' => null,
            'attachments' => $this->attachments($request, $data),
            'in_reply_to' => $data['in_reply_to'] ?? null,
            'references' => $data['references'] ?? null,
        ];
    }

    /**
     * Resolve attachments from uploads + owned gallery photos + owned files,
     * enforcing the total-size cap.
     *
     * @return list<array{filename:string,content:string,mime:string}>
     */
    private function attachments(Request $request, array $data): array
    {
        $out = [];
        $total = 0;
        $add = function (string $name, string $content, string $mime) use (&$out, &$total): void {
            $total += strlen($content);
            abort_if($total > self::MAX_ATTACH_BYTES, 413, __('mail.attachments_too_large'));
            $out[] = ['filename' => $name, 'content' => $content, 'mime' => $mime];
        };

        foreach ((array) $request->file('uploads', []) as $file) {
            $add($file->getClientOriginalName() ?: 'attachment', (string) file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'application/octet-stream');
        }

        $disk = BlobStore::disk();
        $uid = $request->user()->id;
        foreach ($data['refs'] ?? [] as $ref) {
            if (($ref['type'] ?? null) === 'gallery') {
                $photo = Photo::ownedBy($uid)->find($ref['id']);
                if ($photo && $photo->disk_path && $disk->exists($photo->disk_path)) {
                    $add($photo->name ?: 'photo', (string) $disk->get($photo->disk_path), $photo->mime_type ?: 'application/octet-stream');
                }
            } elseif (($ref['type'] ?? null) === 'file') {
                $file = StoredFile::ownedBy($uid)->find($ref['id']);
                if ($file && is_string($file->blob) && $disk->exists('files/'.$file->blob)) {
                    $add($file->name ?: 'file', (string) $disk->get('files/'.$file->blob), $file->mime ?: 'application/octet-stream');
                }
            }
        }

        return $out;
    }

    /** Append a raw message to the first existing folder among the candidates. */
    private function appendSilently(MailSource $source, MailAccount $account, array $folders, string $raw): void
    {
        foreach ($folders as $folder) {
            try {
                $source->appendMessage($account->credentials(), $folder, $raw);

                return;
            } catch (\Throwable) {
                // Try the next candidate; a missing Sent/Drafts folder is non-fatal.
            }
        }
    }
}
