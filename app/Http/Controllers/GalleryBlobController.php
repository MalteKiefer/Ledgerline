<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GalleryBlob;
use App\Support\BlobStore;
use Aws\S3\S3Client;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Zero-knowledge gallery blob store. The whole gallery structure — photo/album/
 * people organisation, names, metadata, EXIF, faces, derived renditions and the
 * reference graph — lives inside the user's sealed gallery index (the opaque
 * store, see GalleryStoreController); the server never sees any of it. This
 * controller only handles the OPAQUE CONTENT BLOBS: it stores/streams ciphertext
 * bytes at "gallery/{blob}" and keeps a blob ownership ledger (gallery_blobs) for
 * quota + access control. It cannot read a blob's contents, its metadata, or
 * which index entry references it.
 *
 * Residual side-channel (accepted): the ledger keeps per-blob owner, stored size
 * and created_at. Sizes are length-hidden by client-side Padmé padding (app.js
 * _padBlob) and created_at is snapped to the hour, so exact lengths and the
 * per-photo upload burst are blurred — but the blob COUNT itself is still visible,
 * from which photo count and rough per-photo face count remain inferable. No
 * content, name or location leaks.
 */
class GalleryBlobController extends Controller
{
    private function disk()
    {
        return BlobStore::disk();
    }

    /** Current storage usage for the user (live blob bytes vs quota). */
    public function usage(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;

        return response()->json(['used' => $this->usedBytes($uid), 'quota' => $this->quotaBytes()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Reclaim blobs the sealed gallery index no longer references. The server
     * can't see the (sealed) reference graph, so the client sends the set of blob
     * ids its index still points at (originals + derived renditions/crops); any
     * of the caller's OWN blobs not in that set — and older than the grace
     * window, so an in-flight upload not yet saved into the index is never reaped
     * — are freed. This is how deleted photos release their quota under ZK.
     */
    public function reconcile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'blobs' => ['present', 'array', 'max:100000'],
            'blobs.*' => ['uuid'],
        ]);

        $uid = (int) $request->user()->id;
        $live = array_flip($data['blobs']);
        $grace = Carbon::now()->subHours((int) config('gallery.blob_orphan_grace_hours', 24));
        $disk = $this->disk();

        // Serialize against the user's uploads so a blob registered mid-reconcile
        // isn't judged against a stale live set.
        $this->withUserLock($uid, function () use ($uid, $live, $grace, $disk): void {
            GalleryBlob::where('user_id', $uid)
                ->where('created_at', '<', $grace)
                ->orderBy('blob')
                ->chunkById(500, function ($rows) use ($live, $disk): void {
                    foreach ($rows as $row) {
                        if (isset($live[$row->blob])) {
                            continue;
                        }
                        $disk->delete('gallery/'.$row->blob);
                        $disk->delete('thumbs/'.$row->blob.'.jpg');
                        $row->delete();
                    }
                }, 'blob');
        });

        return response()->json(['used' => $this->usedBytes($uid), 'quota' => $this->quotaBytes()]);
    }

    /** Store one uploaded (already encrypted) blob and return its id. */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.((int) config('gallery.max_upload_mb', 512) * 1024)],
        ]);

        $uid = (int) $request->user()->id;
        // Best-effort quota check (NOT under the per-user write lock): holding the
        // lock across the slow object-storage write serialised all concurrent
        // uploads and timed them out into 429s during a bulk upload. A minor
        // quota overshoot from concurrent uploads is acceptable; blocking bulk
        // uploads is not.
        $incoming = (int) $request->file('file')->getSize();
        abort_if($this->quotaExceeded($uid, $incoming), 413, __('files.quota_exceeded'));

        $id = (string) Str::uuid();
        // Fail loudly on a failed/short storage write instead of recording a
        // valid-looking blob id whose bytes are missing.
        abort_if($this->disk()->putFileAs('gallery', $request->file('file'), $id) === false, 500, __('files.upload_failed'));
        abort_unless($this->disk()->exists('gallery/'.$id), 500, __('files.upload_failed'));
        // Record the uploader + stored byte size: this is the permanent blob
        // ownership ledger (quota + access control + orphan reclaim). created_at is
        // snapped to the hour so the per-photo blob cluster (original/thumb/medium/
        // meta/crops uploaded within seconds) can't be grouped by upload time.
        GalleryBlob::create(['blob' => $id, 'user_id' => $uid, 'size' => (int) $request->file('file')->getSize(), 'created_at' => now()->startOfHour()]);

        return response()->json(['id' => $id], 201);
    }

    // ---- Chunked (S3 multipart) upload for large files ----
    // Each chunk is a small request, so it sidesteps the nginx/PHP body-size
    // limits, and the parts stream straight to object storage — GB files work.

    /** Part size the client should slice with (S3 requires >= 5 MiB per part). */
    private const CHUNK_PART_SIZE = 8 * 1024 * 1024;

    /** Begin a multipart upload; returns an opaque session token + the blob id. */
    public function chunkInit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:1024'],
            // max_upload_mb bounds a single POST body — irrelevant here since the
            // file arrives in small parts. Bound by the S3 multipart ceiling
            // instead (10 000 parts) so multi-GB uploads are allowed.
            'size' => ['required', 'integer', 'min:1', 'max:'.(10000 * self::CHUNK_PART_SIZE)],
        ]);
        abort_if($this->quotaExceeded((int) $request->user()->id, (int) $data['size']), 413, __('files.quota_exceeded'));

        $id = (string) Str::uuid();
        $key = 'gallery/'.$id;
        $res = $this->s3()->createMultipartUpload(['Bucket' => $this->bucket(), 'Key' => $key]);

        $token = (string) Str::uuid();
        Cache::put($this->chunkKey($token), [
            'uploadId' => $res['UploadId'], 'key' => $key, 'id' => $id, 'user' => (int) $request->user()->id,
        ], now()->addHours(12));

        return response()->json(['token' => $token, 'id' => $id, 'partSize' => self::CHUNK_PART_SIZE]);
    }

    /** Upload one part; returns its ETag for the completion call. */
    public function chunkPart(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'part' => ['required', 'integer', 'min:1', 'max:10000'],
            'chunk' => ['required', 'file', 'max:'.((int) (self::CHUNK_PART_SIZE / 1024) + 1024)],
        ]);
        $s = $this->chunkSession($request);
        $res = $this->s3()->uploadPart([
            'Bucket' => $this->bucket(), 'Key' => $s['key'], 'UploadId' => $s['uploadId'],
            'PartNumber' => (int) $request->input('part'),
            'Body' => fopen($request->file('chunk')->getRealPath(), 'r'),
        ]);

        return response()->json(['part' => (int) $request->input('part'), 'etag' => $res['ETag']]);
    }

    /** Finish the upload and register the blob (same contract as upload()). */
    public function chunkComplete(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'parts' => ['required', 'array', 'min:1', 'max:10000'],
            'parts.*.part' => ['required', 'integer', 'min:1'],
            'parts.*.etag' => ['required', 'string'],
        ]);
        // Keep the session until completion SUCCEEDS: only forget it after S3
        // confirms, so a transient S3 error doesn't strand a fully-uploaded
        // multi-GB file with no retry/abort path. Both completeMultipartUpload
        // (idempotent for identical parts) and GalleryBlob::firstOrCreate dedupe a
        // replayed completion, so leaving the session in place is safe.
        $token = (string) $request->input('token');
        $s = $this->chunkSession($request);

        $parts = collect($request->input('parts'))
            ->map(fn ($p) => ['PartNumber' => (int) $p['part'], 'ETag' => $p['etag']])
            ->sortBy('PartNumber')->values()->all();

        $this->s3()->completeMultipartUpload([
            'Bucket' => $this->bucket(), 'Key' => $s['key'], 'UploadId' => $s['uploadId'],
            'MultipartUpload' => ['Parts' => $parts],
        ]);
        // Authoritative size of the ASSEMBLED object (never the client's declared
        // size, which would let a caller understate a large upload to beat the
        // quota). One HEAD per completed multipart upload — rare (>64 MB files).
        $size = (int) ($this->s3()->headObject(['Bucket' => $this->bucket(), 'Key' => $s['key']])['ContentLength'] ?? 0);
        GalleryBlob::firstOrCreate(['blob' => $s['id']], ['user_id' => $s['user'], 'size' => $size, 'created_at' => now()->startOfHour()]);
        Cache::forget($this->chunkKey($token));

        return response()->json(['id' => $s['id']], 201);
    }

    /** Abort a multipart upload and drop the staged parts. */
    public function chunkAbort(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);
        $token = (string) $request->input('token');
        // Tolerate an already-gone session (nothing to abort) so aborting is
        // always safe/idempotent and never a dead-end 404.
        $s = Cache::get($this->chunkKey($token));
        if (is_array($s) && (int) ($s['user'] ?? 0) === (int) $request->user()->id) {
            try {
                $this->s3()->abortMultipartUpload(['Bucket' => $this->bucket(), 'Key' => $s['key'], 'UploadId' => $s['uploadId']]);
            } catch (\Throwable) {
                // best effort — the object-storage lifecycle rule reclaims stale parts
            }
            Cache::forget($this->chunkKey($token));
        }

        return response()->json(['ok' => true]);
    }

    private function s3(): S3Client
    {
        return $this->disk()->getClient();
    }

    private function bucket(): string
    {
        return (string) config('filesystems.disks.'.config('files.disk').'.bucket');
    }

    private function chunkKey(string $token): string
    {
        return 'chunk-upload:'.$token;
    }

    /** Load + authorise a chunk session (must belong to the current user). */
    private function chunkSession(Request $request): array
    {
        $s = Cache::get($this->chunkKey((string) $request->input('token')));
        abort_if(! is_array($s) || (int) ($s['user'] ?? 0) !== (int) $request->user()->id, 404);

        return $s;
    }

    /** Bytes the user currently occupies (every blob in their ledger). */
    private function usedBytes(int $userId): int
    {
        return (int) GalleryBlob::where('user_id', $userId)->sum('size');
    }

    /** Per-user quota in bytes (0 / null = unlimited). */
    private function quotaBytes(): int
    {
        return (int) config('gallery.quota_mb', 0) * 1024 * 1024;
    }

    private function quotaExceeded(int $userId, int $incoming): bool
    {
        $quota = $this->quotaBytes();

        return $quota > 0 && ($this->usedBytes($userId) + $incoming) > $quota;
    }

    /**
     * Serialize a user's storage-mutating operation so concurrent uploads and a
     * reconcile can't each read a stale ledger baseline and collectively
     * overshoot the quota or reap a just-referenced blob.
     */
    private function withUserLock(int $userId, \Closure $fn)
    {
        // Long TTL so the lock can't auto-expire mid-operation and admit a second
        // writer; a genuine contention timeout becomes a clean 429 (retryable).
        try {
            return Cache::lock('gallery-write:'.$userId, 120)->block(20, $fn);
        } catch (LockTimeoutException $e) {
            abort(429, __('files.busy'));
        }
    }

    /** Stream a stored blob's ciphertext back to the browser (owner only). */
    public function raw(string $blob): StreamedResponse
    {
        abort_unless(Str::isUuid($blob), 404);
        // Only serve bytes the current user owns in the blob ledger — otherwise
        // any authenticated user could fetch any blob by guessing its UUID.
        abort_unless(GalleryBlob::where('blob', $blob)->where('user_id', auth()->id())->exists(), 404);
        $path = 'gallery/'.$blob;
        abort_unless($this->disk()->exists($path), 404);

        // The bytes are ciphertext, but force download + a script-less sandbox as
        // defense in depth (the client decrypts in memory; nothing renders here).
        return $this->disk()->response($path, 'file', [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'attachment');
    }

    /**
     * Delete an owned blob's bytes + ledger row. The client calls this when its
     * sealed index stops referencing the blob (photo permanently deleted, or a
     * rendition replaced). Owner-scoped; unknown blob = already gone (idempotent).
     */
    public function deleteBlob(string $blob): JsonResponse
    {
        abort_unless(Str::isUuid($blob), 404);

        $row = GalleryBlob::where('blob', $blob)->first();
        if ($row === null) {
            return response()->json(['deleted' => true]);
        }
        abort_if((int) $row->user_id !== (int) auth()->id(), 403);

        $this->disk()->delete('gallery/'.$blob);
        $this->disk()->delete('thumbs/'.$blob.'.jpg');
        $row->delete();

        return response()->json(['deleted' => true]);
    }
}
