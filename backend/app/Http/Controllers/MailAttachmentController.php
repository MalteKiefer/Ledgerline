<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileEntry;
use App\Models\MailAttachment;
use App\Services\Finance\ReceiptFiler;
use App\Services\Paperless\PaperlessClient;
use App\Support\BlobStore;
use App\Support\Redactor;
use App\Support\StorageUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner-scoped access to an archived message's decoded attachment bytes
 * (mail/att/{blob}). `raw` streams the bytes sandboxed (inline preview for a
 * small safe allowlist, otherwise a forced download); `save` copies the
 * server-held bytes into another module (Files or Paperless) with no client
 * round-trip — the server already holds the plaintext.
 *
 * A foreign / unknown id is a 404 (no existence leak).
 */
class MailAttachmentController extends Controller
{
    /** MIME types safe to render inline; everything else is a forced download. */
    private const INLINE_TYPES = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'text/plain',
    ];

    /**
     * Every attachment the account holds, newest first — "the mail with the PDF"
     * without remembering which mail it was.
     *
     * Reads the attachment table directly rather than walking messages: the rows
     * are already there, one query answers it, and the message it belongs to
     * comes along as a subject so a hit can be opened.
     *
     * Filters: ?account_id ?folder ?q (filename) ?type (image|pdf|document|other).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $perPage = min(max(1, $request->integer('per_page', 100)), 500);

        $query = MailAttachment::query()
            ->ownedBy($user->id)
            // An inline image is part of how a message looks, not something the
            // sender attached; listing signatures and tracking pixels here would
            // bury the documents.
            ->where('inline', false)
            ->whereHas('message', function (Builder $m) use ($request): void {
                $m->whereNull('trashed_at')
                    ->when($request->filled('account_id'), fn (Builder $q) => $q->where('account_id', $request->integer('account_id')))
                    ->when($request->filled('folder'), fn (Builder $q) => $q->where('folder', $request->string('folder')->value()));
            })
            ->with(['message:id,subject,from_name,from_email,date,created_at,folder,account_id']);

        $q = trim($request->string('q')->value());
        if ($q !== '') {
            $query->whereRaw('lower(filename) like ?', ['%'.mb_strtolower($q).'%']);
        }

        $type = $request->string('type')->value();
        if ($type !== '') {
            $query->where(function (Builder $inner) use ($type): void {
                match ($type) {
                    'image' => $inner->where('content_type', 'like', 'image/%'),
                    'pdf' => $inner->where('content_type', 'application/pdf'),
                    'document' => $inner->where(fn (Builder $d) => $d
                        ->where('content_type', 'like', '%word%')
                        ->orWhere('content_type', 'like', '%excel%')
                        ->orWhere('content_type', 'like', '%spreadsheet%')
                        ->orWhere('content_type', 'like', '%presentation%')
                        ->orWhere('content_type', 'like', 'text/%')),
                    // Everything the three named buckets do not claim.
                    default => $inner->whereNotNull('id')
                        ->where('content_type', 'not like', 'image/%')
                        ->where('content_type', '!=', 'application/pdf')
                        ->where('content_type', 'not like', 'text/%'),
                };
            });
        }

        $page = $query->orderByDesc('created_at')->orderBy('id')->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (MailAttachment $a): array => [
                'id' => $a->id,
                'message_id' => $a->message_id,
                'filename' => $a->filename,
                'content_type' => $a->content_type,
                'size' => (int) $a->size,
                'subject' => $a->message?->subject,
                'from' => $a->message?->from_name ?: $a->message?->from_email,
                'folder' => $a->message?->folder,
                'date' => $a->message?->date?->toIso8601String() ?? $a->message?->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /** Stream one attachment's bytes, sandboxed. Inline for the safe allowlist unless download is requested. */
    public function raw(Request $request, MailAttachment $attachment): StreamedResponse
    {
        $this->authorizeOwner($request, $attachment);

        $disk = BlobStore::disk();
        $key = 'mail/att/'.$attachment->blob;
        abort_unless($disk->exists($key), 404);

        $type = (string) $attachment->content_type;
        $inline = ! $request->boolean('download')
            && in_array(strtolower(explode(';', $type)[0]), self::INLINE_TYPES, true);
        $filename = $this->safeName($attachment->filename);

        return $disk->response($key, $filename, [
            // Serve the real type for the inline allowlist so a preview renders;
            // otherwise octet-stream forces a download. nosniff + sandbox CSP so a
            // malicious attachment can never execute or embed cross-origin.
            'Content-Type' => $inline && $type !== '' ? $type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $inline ? 'inline' : 'attachment');
    }

    /**
     * Copy the attachment's server-held bytes into another module. The server
     * holds the plaintext, so there is no client upload:
     *   - target=files    → a new FileEntry (owner quota enforced, 413).
     *   - target=paperless→ POST to the user's Paperless via their own creds.
     *   - target=finance → a standalone receipt in the finance inbox, where the
     *     existing chain takes over: OCR, amount/date/merchant recognition,
     *     partner matching, sha256 dedup, matching against a bank booking.
     *     Invoices arrive by mail; this is the step that was done by hand.
     * (Gallery is not a module in this deployment; it is rejected at validation.)
     */
    public function save(Request $request, MailAttachment $attachment): JsonResponse
    {
        $this->authorizeOwner($request, $attachment);
        $uid = (int) $this->requireUser($request)->id;

        $request->validate([
            'target' => ['required', Rule::in(['files', 'paperless', 'finance'])],
            'folder_id' => ['nullable', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
        ]);

        $disk = BlobStore::disk();
        $key = 'mail/att/'.$attachment->blob;
        abort_unless($disk->exists($key), 404);
        $bytes = $disk->get($key);
        abort_if(! is_string($bytes), 404);

        return match ($request->string('target')->value()) {
            'files' => $this->saveToFiles($request, $attachment, $bytes, $uid),
            'finance' => $this->saveToFinance($attachment, $bytes, $uid),
            default => $this->saveToPaperless($attachment, $bytes, $uid),
        };
    }

    /** Create a plaintext file (server-set byte metadata) under the owner's quota. */
    private function saveToFiles(Request $request, MailAttachment $attachment, string $bytes, int $uid): JsonResponse
    {
        $size = strlen($bytes);
        if ($over = $this->overFilesQuota($uid, $size)) {
            return $over;
        }

        $path = 'files/'.Str::uuid()->toString();
        if (BlobStore::disk()->put($path, $bytes) === false) {
            return response()->json(['ok' => false, 'detail' => 'write_failed'], 422);
        }

        $file = new FileEntry;
        $file->fill([
            'name' => $this->safeName($attachment->filename),
            'file_folder_id' => $request->filled('folder_id') ? $request->integer('folder_id') : null,
        ]);
        $file->forceFill([
            'storage_path' => $path,
            'size' => $size,
            'mime' => $attachment->content_type,
            'sha256' => hash('sha256', $bytes),
        ]);
        $file->save();

        return response()->json(['ok' => true, 'target' => 'files', 'file_id' => $file->id]);
    }

    /** File the attachment in the finance inbox (see ReceiptFiler for the rules). */
    private function saveToFinance(MailAttachment $attachment, string $bytes, int $uid): JsonResponse
    {
        $filed = app(ReceiptFiler::class)->file($uid, $bytes, $attachment->filename, $attachment->content_type);
        if ($filed === null) {
            return response()->json(['ok' => false, 'detail' => 'unsupported_type'], 422);
        }

        return response()->json(['ok' => true, 'target' => 'finance'] + $filed);
    }

    /** Hand the bytes to the user's Paperless instance (their own credentials). */
    private function saveToPaperless(MailAttachment $attachment, string $bytes, int $uid): JsonResponse
    {
        $client = PaperlessClient::forUser($uid);
        if ($client === null) {
            return response()->json(['ok' => false, 'detail' => 'paperless_not_configured'], 422);
        }

        try {
            $task = $client->postDocument($bytes, $this->safeName($attachment->filename), []);
        } catch (\Throwable $e) {
            Log::warning('mail.attachment.paperless_failed', ['error' => Redactor::redact($e->getMessage())]);

            return response()->json(['ok' => false, 'detail' => 'request_failed'], 422);
        }

        return response()->json(['ok' => true, 'target' => 'paperless', 'task' => $task]);
    }

    /** 413 when storing the bytes would exceed the owner's combined Files+Gallery quota. */
    private function overFilesQuota(int $uid, int $incoming): ?JsonResponse
    {
        return StorageUsage::wouldExceed($uid, $incoming) ? response()->json(['error' => 'quota'], 413) : null;
    }

    private function safeName(?string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', (string) $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'attachment' : mb_substr($clean, 0, 255);
    }

    private function authorizeOwner(Request $request, MailAttachment $attachment): void
    {
        abort_if((int) $attachment->user_id !== (int) $this->requireUser($request)->id, 404);
    }
}
