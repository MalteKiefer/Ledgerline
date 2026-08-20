<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAttachment;
use App\Models\MailBlob;
use App\Models\MailLabel;
use App\Models\MailMessage;
use App\Services\Mail\MailDecryptor;
use App\Support\BlobStore;
use App\Support\Mail\MailHtmlSanitizer;
use App\Support\Mail\MimeParser;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only, owner-scoped access to the archived-message ledger. `index`
 * returns the denormalised envelope rows the client lists + filters + searches;
 * `show` returns one message's full reader payload (bodies + headers + auth
 * signals). Archived mail is immutable: there is NO edit endpoint here (seen /
 * trash toggles live in MailSeenController / MailTrashController).
 *
 * Full-text search runs server-side over the `search_text` column: PostgreSQL
 * uses the GIN `to_tsvector('simple') @@ plainto_tsquery` index; SQLite falls
 * back to LIKE. All queries are explicitly owner-scoped (`ownedBy`).
 */
class MailMessageController extends Controller
{
    private const MAX_PER_PAGE = 1000;

    /**
     * Paginated, owner-scoped envelope listing.
     *
     * Query params: ?account_id ?folder ?trashed ?seen ?spam ?thread_id
     *   ?q (full-text) ?from ?to (YYYY-MM-DD date range on the message Date)
     *   ?page ?per_page (1..1000, default 50). No account_id = unified inbox.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $perPage = min(max(1, $request->integer('per_page', 50)), self::MAX_PER_PAGE);
        $wantTrashed = $request->boolean('trashed');

        $query = MailMessage::query()
            ->ownedBy($user->id)
            ->with('labels')
            // Trashed = hidden. Excluded by default; ?trashed=1 returns ONLY those.
            ->when($wantTrashed, fn (Builder $q) => $q->whereNotNull('trashed_at'), fn (Builder $q) => $q->whereNull('trashed_at'))
            ->when($request->filled('account_id'), fn (Builder $q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('folder'), fn (Builder $q) => $q->where('folder', $request->string('folder')->value()))
            ->when($request->filled('thread_id'), fn (Builder $q) => $q->where('thread_id', $request->string('thread_id')->value()))
            ->when($request->filled('label'), fn (Builder $q) => $q->whereHas('labels', fn (Builder $l) => $l->where('mail_labels.id', $request->integer('label'))))
            ->when($request->has('seen'), fn (Builder $q) => $q->where('seen', $request->boolean('seen')))
            ->when($request->has('spam'), fn (Builder $q) => $q->where('spam', $request->boolean('spam')));

        $this->applyDateRange($query, $request);
        $this->applySearch($query, trim($request->string('q')->value()));

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (MailMessage $m): array => $this->presentRow($m))->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** One message's full reader payload (owner-scoped; 404 on foreign). */
    public function show(Request $request, MailMessage $message): JsonResponse
    {
        $this->authorizeOwner($request, $message);
        $this->lazyDecrypt($message);
        $message->load('labels');

        return response()->json(['message' => $this->presentFull($message)]);
    }

    /**
     * If an encrypted message was archived without a key (decrypt_status !=
     * ok) and the owner has since imported a matching key, decrypt it now and
     * persist the plaintext body + attachments + refreshed search index. The
     * raw .eml is never rewritten. Best-effort — any failure leaves the row as
     * it was. Same transient-plaintext posture as the ingest decrypt path.
     */
    private function lazyDecrypt(MailMessage $message): void
    {
        if ($message->encrypted_type === null || $message->decrypt_status === 'ok') {
            return;
        }

        $raw = BlobStore::disk()->get('mail/'.$message->id);
        if (! is_string($raw) || $raw === '') {
            return;
        }

        $out = (new MailDecryptor)->attempt($raw, (int) $message->user_id);
        if ($out->status !== 'ok' || $out->plaintext === null) {
            if ($out->status !== null && $out->status !== $message->decrypt_status) {
                $message->forceFill(['decrypt_status' => $out->status])->saveQuietly();
            }

            return;
        }

        $textBody = $out->plaintext;
        $htmlBody = null;
        $attachments = [];
        if ($out->isMime) {
            $inner = (new MimeParser)->parse($out->plaintext);
            $textBody = $inner->textBody;
            $htmlBody = $inner->htmlBody;
            $attachments = $inner->attachments;
        }
        $html = (new MailHtmlSanitizer)->sanitize($htmlBody);

        DB::transaction(function () use ($message, $textBody, $html, $attachments): void {
            $now = now()->startOfHour();
            foreach ($attachments as $att) {
                $blobId = (string) Str::uuid();
                BlobStore::disk()->put('mail/att/'.$blobId, $att->bytes);
                (new MailBlob)->forceFill([
                    'blob' => $blobId, 'user_id' => $message->user_id, 'kind' => 'attachment',
                    'size' => $att->size(), 'created_at' => $now,
                ])->save();
                (new MailAttachment)->forceFill([
                    'id' => (string) Str::uuid(),
                    'message_id' => $message->id, 'user_id' => $message->user_id, 'blob' => $blobId,
                    'filename' => $att->filename !== null ? mb_substr($att->filename, 0, 500) : null,
                    'content_type' => $att->contentType !== null ? mb_substr($att->contentType, 0, 255) : null,
                    'content_id' => $att->contentId !== null ? mb_substr($att->contentId, 0, 512) : null,
                    'inline' => $att->inline, 'size' => $att->size(), 'created_at' => $now,
                ])->save();
            }

            $filenames = implode(' ', array_filter(array_map(static fn ($a): ?string => $a->filename, $attachments)));
            $search = trim(implode(' ', array_filter([
                $message->subject, $message->from_name, $message->from_email, $textBody, $filenames,
            ], static fn (?string $p): bool => $p !== null && $p !== '')));

            $message->forceFill([
                'text_body' => $textBody,
                'html_sanitized' => $html,
                'decrypt_status' => 'ok',
                'has_attachment' => $attachments !== [],
                'attachment_count' => count($attachments),
                'search_text' => $search === '' ? null : mb_substr($search, 0, 200_000),
                'indexed_at' => $now,
            ])->saveQuietly();
        });
    }

    /**
     * The message's HTML body as a standalone, sandboxed document for a reader
     * iframe. Re-derived from the immutable raw .eml so inline cid: images can
     * be embedded as data: URIs. Strict CSP: no scripts and no same-origin.
     * Remote resources stay blocked by default and are permitted only for an
     * explicit, one-message `?remote=1` reader action. cid: inline images are
     * rewritten to data: URIs from the stored attachment bytes.
     */
    public function body(Request $request, MailMessage $message): Response
    {
        $this->authorizeOwner($request, $message);

        // Remote content is privacy-sensitive, so it is opt-in for this one
        // reader request only. There is deliberately no persistent auto-load
        // preference: every newly opened message begins with tracking blocked.
        $allowRemote = $request->boolean('remote');
        $html = $this->renderBody($message, $allowRemote);

        // Remote images are permitted over TLS only: a cleartext fetch would
        // expose which message was opened, and when, to anyone on the path.
        $csp = "default-src 'none'; sandbox; style-src 'unsafe-inline'; img-src data:".($allowRemote ? ' https:' : '');
        $doc = '<!doctype html><html><head><meta charset="utf-8">'
            .'<meta name="referrer" content="no-referrer">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            ."</head><body>{$html}</body></html>";

        return response($doc, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => $csp,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Build the sanitized body HTML: parse the raw .eml, resolve cid: inline
     * images to data: URIs, sanitize with remote gating. Falls back to the
     * escaped plaintext body (then the stored sanitized HTML) when the raw blob
     * is unavailable or has no HTML part.
     */
    private function renderBody(MailMessage $message, bool $allowRemote): string
    {
        $disk = BlobStore::disk();
        $key = 'mail/'.$message->id;
        if ($disk->exists($key)) {
            $raw = $disk->get($key);
            if (is_string($raw) && $raw !== '') {
                $parsed = (new MimeParser)->parse($raw);
                $html = (new MailHtmlSanitizer)->sanitize($parsed->htmlBody, $allowRemote, $this->cidMap($message));
                if ($html !== null) {
                    return $html;
                }
                if ($parsed->textBody !== null) {
                    return '<pre style="white-space:pre-wrap;word-break:break-word">'.e($parsed->textBody).'</pre>';
                }
            }
        }

        if ($message->html_sanitized !== null) {
            // Historic imports can predate the current sanitizer. Re-sanitize the
            // fallback on every render so an unavailable raw .eml never revives
            // executable or remote markup from an old stored representation.
            $html = (new MailHtmlSanitizer)->sanitize($message->html_sanitized, $allowRemote, $this->cidMap($message));
            if ($html !== null) {
                return $html;
            }
        }
        if ($message->text_body !== null) {
            return '<pre style="white-space:pre-wrap;word-break:break-word">'.e($message->text_body).'</pre>';
        }

        return '';
    }

    /**
     * Map of normalized Content-Id → data: URI for the message's inline
     * attachments, capped in total size so a message with many/large inline
     * images cannot bloat the body document.
     *
     * @return array<string, string>
     */
    private function cidMap(MailMessage $message): array
    {
        $budget = 8 * 1024 * 1024; // total inlined bytes ceiling
        $map = [];

        $inline = MailAttachment::query()
            ->where('message_id', $message->id)
            ->where('inline', true)
            ->whereNotNull('content_id')
            ->get();

        $disk = BlobStore::disk();
        foreach ($inline as $att) {
            $cid = (string) $att->content_id;
            if ($cid === '' || $att->size > $budget) {
                continue;
            }
            $bytes = $disk->get('mail/att/'.$att->blob);
            if (! is_string($bytes)) {
                continue;
            }
            $budget -= strlen($bytes);
            if ($budget < 0) {
                break;
            }
            $type = $att->content_type ?? 'application/octet-stream';
            $map[$cid] = 'data:'.$type.';base64,'.base64_encode($bytes);
        }

        return $map;
    }

    /** @param  EloquentBuilder<MailMessage>  $query */
    private function applyDateRange(EloquentBuilder $query, Request $request): void
    {
        if ($request->filled('from')) {
            $from = $this->parseDate($request->string('from')->value());
            if ($from !== null) {
                $query->where('date', '>=', $from->startOfDay());
            }
        }
        if ($request->filled('to')) {
            $to = $this->parseDate($request->string('to')->value());
            if ($to !== null) {
                $query->where('date', '<=', $to->endOfDay());
            }
        }
    }

    /** @param  EloquentBuilder<MailMessage>  $query */
    private function applySearch(EloquentBuilder $query, string $q): void
    {
        if ($q === '') {
            return;
        }

        $like = '%'.$q.'%';
        if (DB::getDriverName() === 'pgsql') {
            $query->where(function (EloquentBuilder $inner) use ($q, $like): void {
                $inner->whereRaw(
                    "to_tsvector('simple', coalesce(search_text, '')) @@ plainto_tsquery('simple', ?)",
                    [$q]
                )
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('from_email', 'like', $like)
                    ->orWhere('from_name', 'like', $like);
            });

            return;
        }

        $query->where(function (EloquentBuilder $inner) use ($like): void {
            $inner->where('search_text', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('from_email', 'like', $like)
                ->orWhere('from_name', 'like', $like);
        });
    }

    private function parseDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Envelope row for the list. No bodies / no raw headers (kept lean).
     *
     * @return array<string, mixed>
     */
    private function presentRow(MailMessage $m): array
    {
        return [
            'id' => $m->id,
            'account_id' => $m->account_id,
            'folder' => $m->folder,
            'message_id' => $m->message_id,
            'thread_id' => $m->thread_id,
            'subject' => $m->subject,
            'from_name' => $m->from_name,
            'from_email' => $m->from_email,
            'to' => $m->to_json ?? [],
            'cc' => $m->cc_json ?? [],
            'date' => $m->date?->toIso8601String(),
            'size' => $m->size,
            'has_attachment' => $m->has_attachment,
            'attachment_count' => $m->attachment_count,
            'seen' => $m->seen,
            'trashed' => $m->trashed_at !== null,
            'spam' => $m->spam,
            'spf' => $m->spf,
            'dkim' => $m->dkim,
            'dmarc' => $m->dmarc,
            'encrypted_type' => $m->encrypted_type,
            'decrypt_status' => $m->decrypt_status,
            'created_at' => $m->created_at?->toIso8601String(),
            'labels' => $m->relationLoaded('labels')
                ? $m->labels->map(fn (MailLabel $l): array => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->all()
                : [],
        ];
    }

    /**
     * Full reader payload.
     *
     * @return array<string, mixed>
     */
    private function presentFull(MailMessage $m): array
    {
        return array_merge($this->presentRow($m), [
            'in_reply_to' => $m->in_reply_to,
            'references' => $m->references,
            'reply_to' => $m->reply_to,
            'text_body' => $m->text_body,
            'html' => $m->html_sanitized,
            'headers_raw' => $this->rawHeaders($m),
            'attachments' => $m->attachments()
                ->orderBy('created_at')
                ->get()
                ->map(fn (MailAttachment $a): array => [
                    'id' => $a->id,
                    'filename' => $a->filename,
                    'content_type' => $a->content_type,
                    'size' => $a->size,
                    'inline' => $a->inline,
                ])
                ->all(),
        ]);
    }

    /**
     * The raw RFC822 header block, read on demand from the immutable .eml blob
     * (mail/{id}) — everything before the first blank line. Never stored in a
     * column; capped so a pathological header block can't bloat the response.
     */
    private function rawHeaders(MailMessage $m): ?string
    {
        $disk = BlobStore::disk();
        $key = 'mail/'.$m->id;
        if (! $disk->exists($key)) {
            return null;
        }

        $raw = $disk->get($key);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $norm = str_replace("\r\n", "\n", $raw);
        $sep = strpos($norm, "\n\n");
        $block = $sep === false ? $norm : substr($norm, 0, $sep);

        return mb_substr($block, 0, 16_384);
    }

    private function authorizeOwner(Request $request, MailMessage $message): void
    {
        abort_if((int) $message->user_id !== (int) $this->requireUser($request)->id, 404);
    }
}
