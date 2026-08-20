<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileEntry;
use App\Models\MailAttachment;
use App\Services\Paperless\PaperlessClient;
use App\Support\BlobStore;
use App\Support\Redactor;
use App\Support\StorageUsage;
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
     * (Gallery is not a module in this deployment; it is rejected at validation.)
     */
    public function save(Request $request, MailAttachment $attachment): JsonResponse
    {
        $this->authorizeOwner($request, $attachment);
        $uid = (int) $this->requireUser($request)->id;

        $request->validate([
            'target' => ['required', Rule::in(['files', 'paperless'])],
            'folder_id' => ['nullable', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
        ]);

        $disk = BlobStore::disk();
        $key = 'mail/att/'.$attachment->blob;
        abort_unless($disk->exists($key), 404);
        $bytes = $disk->get($key);
        abort_if(! is_string($bytes), 404);

        return match ($request->string('target')->value()) {
            'files' => $this->saveToFiles($request, $attachment, $bytes, $uid),
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
