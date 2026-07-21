<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\BlobStore;
use Aws\S3\S3Client;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared zero-knowledge blob store. The files and gallery modules both keep the
 * whole structure/metadata sealed inside their opaque index and expose only the
 * OPAQUE CONTENT BLOBS here: ciphertext bytes stored at "{module}/{blob}" plus an
 * ownership ledger ({module}_blobs) for quota + access control. The server cannot
 * read a blob's contents, its metadata, or which index entry references it.
 *
 * Everything below — upload (whole + S3 multipart), quota, per-user write lock,
 * reconcile, orphan-safe raw stream and owner-scoped delete — is identical across
 * the two modules; a concrete subclass only supplies its ledger model and module
 * name (and, for the gallery, the hour-snapped created_at stamp).
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
abstract class BlobStoreController extends Controller
{
    /**
     * Fully-qualified ownership-ledger model (FileBlob / GalleryBlob).
     *
     * @return class-string<TModel>
     */
    abstract protected function blobModel(): string;

    /** Module slug — the disk prefix, config namespace and lock-key stem. */
    abstract protected function module(): string;

    /** Whole-upload body cap (MiB). Gallery caps tighter than files. */
    protected function maxUploadMb(): int
    {
        return (int) config($this->module().'.max_upload_mb', 512);
    }

    /**
     * Ledger timestamp for a newly stored blob. Files record the real time; the
     * gallery snaps to the hour so the per-photo blob cluster (original/thumb/
     * medium/meta/crops uploaded within seconds) can't be grouped by upload time.
     */
    protected function stampedAt(): Carbon
    {
        return now();
    }

    protected function disk(): Filesystem
    {
        return BlobStore::disk();
    }

    // ---- Ownership strategy hooks ----
    // Subclasses override these to redirect ownership to a shared folder owner
    // instead of the acting caller. For the personal subclasses (FileController /
    // GalleryBlobController / ContactBlobController) the defaults reproduce the
    // current behavior exactly, so no call-site changes are needed there.

    /** Id stamped on new ledger rows and used for quota. Default: the caller. */
    protected function ownerId(Request $request): int
    {
        $user = $this->requireUser($request);

        return (int) $user->id;
    }

    /**
     * Base ledger query the access/usage/reconcile paths are scoped through.
     *
     * @return Builder<TModel>
     */
    protected function scopeLedger(Request $request): Builder
    {
        $user = $this->requireUser($request);
        $model = $this->blobModel();

        /** @var Builder<TModel> $query */
        $query = $model::query();

        return $query->where('user_id', (int) $user->id);
    }

    /**
     * Columns (besides blob/size/created_at) written when registering a blob.
     *
     * @return array<string, mixed>
     */
    protected function stampAttributes(Request $request): array
    {
        $user = $this->requireUser($request);

        return ['user_id' => (int) $user->id];
    }

    /** Owner id recorded in a chunk session's ledger row. Default: the caller. */
    protected function chunkOwnerId(Request $request): int
    {
        $user = $this->requireUser($request);

        return (int) $user->id;
    }

    /** Hook for subclasses that need extra authorization on raw reads (default: none). */
    protected function authorizeRaw(Request $request, string $blob): void {}

    /** Hook for subclasses that need extra authorization on mutations (default: none). */
    protected function authorizeMutation(Request $request): void {}

    /**
     * Lock id used to serialize concurrent reconcile calls. The default uses
     * the acting user id, preserving personal-blob behavior exactly. Subclasses
     * that attribute blobs to a shared owner (e.g. a vault owner) override this
     * to return the owner id so all members of the same shared scope share the
     * same lock key and concurrent reconciles are serialized correctly.
     */
    protected function reconcileLockId(Request $request): int
    {
        $user = $this->requireUser($request);

        return (int) $user->id;
    }

    /**
     * Extra key→value pairs to merge into the chunk session cache when a
     * multipart upload is initialised. Subclasses use this to thread context
     * (e.g. vault_id) that is needed at completion time without re-authorising
     * a route-bound model from the stale cached session.
     *
     * @return array<string, mixed>
     */
    protected function chunkSessionExtra(Request $request): array
    {
        return [];
    }

    /**
     * Ledger columns (besides blob/size/created_at) written when a multipart
     * upload completes. The default stamps `user_id` from `$session['owner']`,
     * preserving the existing personal-blob behavior exactly.
     *
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    protected function chunkLedgerAttributes(array $session): array
    {
        return ['user_id' => $session['owner']];
    }

    // ---- End ownership strategy hooks ----

    /** Current storage usage for the user (live blob bytes vs quota). */
    public function usage(Request $request): JsonResponse
    {
        return response()->json(['used' => $this->usedBytesFor($request), 'quota' => $this->quotaBytes()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /** Bytes occupied by the ledger scope for this request (live blob bytes vs quota). */
    protected function usedBytesFor(Request $request): int
    {
        return (int) $this->scopeLedger($request)->sum('size');
    }

    /**
     * Reclaim blobs the sealed index no longer references. The server can't see
     * the (sealed) reference graph, so the client sends the set of blob ids its
     * index still points at; any of the caller's OWN blobs not in that set — and
     * older than the grace window, so an in-flight upload not yet saved into the
     * index is never reaped — are freed. This is how removed content releases its
     * quota under zero-knowledge.
     */
    public function reconcile(Request $request): JsonResponse
    {
        $this->authorizeMutation($request);

        $data = $request->validate([
            'blobs' => ['present', 'array', 'max:100000'],
            'blobs.*' => ['uuid'],
        ]);

        $lockId = $this->reconcileLockId($request);
        $live = array_flip($data['blobs']);
        $grace = Carbon::now()->subHours((int) config($this->module().'.blob_orphan_grace_hours', 24));
        $disk = $this->disk();
        $prefix = $this->module();

        // Serialize against the ledger scope's lock so concurrent reconciles from
        // different members of the same shared scope don't race against each other.
        $this->withUserLock($lockId, function () use ($request, $live, $grace, $disk, $prefix): void {
            (clone $this->scopeLedger($request))
                ->where('created_at', '<', $grace)
                ->orderBy('blob')
                ->chunkById(500, function (Collection $rows) use ($live, $disk, $prefix): void {
                    foreach ($rows as $row) {
                        // The blob id is the row's primary key ($primaryKey = 'blob').
                        $blob = (string) $row->getKey();
                        if (isset($live[$blob])) {
                            continue;
                        }
                        $disk->delete($prefix.'/'.$blob);
                        $row->delete();
                    }
                }, 'blob');
        });

        return response()->json(['used' => $this->usedBytesFor($request), 'quota' => $this->quotaBytes()]);
    }

    /** Store one uploaded (already encrypted) blob and return its id. */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.($this->maxUploadMb() * 1024)],
        ]);

        $uid = $this->ownerId($request);
        // Best-effort quota check (NOT under the per-user write lock): holding the
        // lock across the slow object-storage write serialised all concurrent
        // uploads and timed them out into 429s during a bulk upload. A minor
        // quota overshoot from concurrent uploads is acceptable; blocking bulk
        // uploads is not.
        $incoming = (int) $request->file('file')->getSize();
        abort_if($this->quotaExceeded($uid, $incoming), 413, __('files.quota_exceeded'));

        $id = (string) Str::uuid();
        $prefix = $this->module();
        // Fail loudly on a failed/short storage write instead of recording a
        // valid-looking blob id whose bytes are missing.
        abort_if($this->disk()->putFileAs($prefix, $request->file('file'), $id) === false, 500, __('files.upload_failed'));
        abort_unless($this->disk()->exists($prefix.'/'.$id), 500, __('files.upload_failed'));
        // Record the uploader + stored byte size: this is the permanent blob
        // ownership ledger (quota + access control + orphan reclaim).
        $model = $this->blobModel();
        $model::create(array_merge($this->stampAttributes($request), [
            'blob' => $id,
            'size' => (int) $request->file('file')->getSize(),
            'created_at' => $this->stampedAt(),
        ]));

        return response()->json(['id' => $id], 201);
    }

    // ---- Chunked (S3 multipart) upload for large files ----
    // Each chunk is a small request, so it sidesteps the nginx/PHP body-size
    // limits, and the parts stream straight to object storage — GB files work.

    /** Part size the client should slice with (S3 requires >= 5 MiB per part). */
    protected const CHUNK_PART_SIZE = 8 * 1024 * 1024;

    /** Begin a multipart upload; returns an opaque session token + the blob id. */
    public function chunkInit(Request $request): JsonResponse
    {
        $data = $request->validate([
            // max_upload_mb bounds a single POST body — irrelevant here since the
            // file arrives in small parts. Bound by the S3 multipart ceiling
            // instead (10 000 parts) so multi-GB uploads are allowed.
            'size' => ['required', 'integer', 'min:1', 'max:'.(10000 * self::CHUNK_PART_SIZE)],
        ]);
        $user = $this->requireUser($request);
        abort_if($this->quotaExceeded($this->chunkOwnerId($request), (int) $data['size']), 413, __('files.quota_exceeded'));

        $id = (string) Str::uuid();
        $key = $this->module().'/'.$id;
        $res = $this->s3()->createMultipartUpload(['Bucket' => $this->bucket(), 'Key' => $key]);

        $token = (string) Str::uuid();
        Cache::put($this->chunkKey($token), array_merge([
            'uploadId' => $res['UploadId'], 'key' => $key, 'id' => $id,
            'actor' => (int) $user->id,
            'owner' => $this->chunkOwnerId($request),
        ], $this->chunkSessionExtra($request)), now()->addHours(12));

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
        // (idempotent for identical parts) and firstOrCreate dedupe a replayed
        // completion, so leaving the session in place is safe.
        $token = (string) $request->input('token');
        $s = $this->chunkSession($request);

        /** @var array<int, array{part: int|string, etag: string}> $inputParts */
        $inputParts = $request->input('parts');
        $parts = collect($inputParts)
            ->map(fn (array $p): array => ['PartNumber' => (int) $p['part'], 'ETag' => $p['etag']])
            ->sortBy('PartNumber')->values()->all();

        $this->s3()->completeMultipartUpload([
            'Bucket' => $this->bucket(), 'Key' => $s['key'], 'UploadId' => $s['uploadId'],
            'MultipartUpload' => ['Parts' => $parts],
        ]);
        // Authoritative size of the ASSEMBLED object (never the client's declared
        // size, which would let a caller understate a large upload to beat the
        // quota). One HEAD per completed multipart upload — rare (>64 MB files).
        $size = (int) ($this->s3()->headObject(['Bucket' => $this->bucket(), 'Key' => $s['key']])['ContentLength'] ?? 0);
        $model = $this->blobModel();
        $model::firstOrCreate(
            ['blob' => $s['id']],
            array_merge($this->chunkLedgerAttributes($s), ['size' => $size, 'created_at' => $this->stampedAt()]),
        );
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
        $user = $this->requireUser($request);
        $s = Cache::get($this->chunkKey($token));
        if (is_array($s) && (int) ($s['actor'] ?? 0) === (int) $user->id) {
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
        // The blob disk is always an S3-compatible adapter (files/gallery/shared
        // folders all use S3/B2); narrow so the multipart client is well-typed.
        $disk = $this->disk();
        abort_unless($disk instanceof AwsS3V3Adapter, 500);

        return $disk->getClient();
    }

    private function bucket(): string
    {
        return (string) config('filesystems.disks.'.config('files.disk').'.bucket');
    }

    private function chunkKey(string $token): string
    {
        return 'chunk-upload:'.$token;
    }

    /**
     * Load + authorise a chunk session (must belong to the acting user).
     *
     * @return array<string, mixed>
     */
    private function chunkSession(Request $request): array
    {
        $user = $this->requireUser($request);
        $s = Cache::get($this->chunkKey((string) $request->input('token')));
        abort_if(! is_array($s) || (int) ($s['actor'] ?? 0) !== (int) $user->id, 404);

        return $s;
    }

    /** Bytes the user currently occupies (every blob in their ledger). */
    protected function usedBytes(int $userId): int
    {
        $model = $this->blobModel();

        return (int) $model::where('user_id', $userId)->sum('size');
    }

    /** Per-user quota in bytes (0 / null = unlimited). */
    protected function quotaBytes(): int
    {
        return (int) config($this->module().'.quota_mb', 0) * 1024 * 1024;
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
     *
     * @template T
     *
     * @param  \Closure(): T  $fn
     * @return T
     */
    private function withUserLock(int $userId, \Closure $fn)
    {
        // Long TTL so the lock can't auto-expire mid-operation and admit a second
        // writer; a genuine contention timeout becomes a clean 429 (retryable).
        try {
            return Cache::lock($this->module().'-write:'.$userId, 120)->block(20, $fn);
        } catch (LockTimeoutException $e) {
            abort(429, __('files.busy'));
        }
    }

    /** Stream a stored blob's ciphertext back to the browser (owner only). */
    public function raw(Request $request, string $blob): StreamedResponse
    {
        // Use the named route parameter directly so subclasses whose routes have
        // extra segments (e.g. {vault}/{blob}) don't receive the wrong value when
        // Laravel's positional dependency resolver maps the first string route
        // parameter to $blob instead of the one actually named "blob".
        $blob = (string) ($request->route('blob') ?? $blob);
        abort_unless(Str::isUuid($blob), 404);
        $this->authorizeRaw($request, $blob);
        // Only serve bytes the caller is authorized to see in the blob ledger —
        // otherwise any authenticated user could fetch any blob by guessing its UUID.
        abort_unless((clone $this->scopeLedger($request))->where('blob', $blob)->exists(), 404);
        $path = $this->module().'/'.$blob;
        abort_unless($this->disk()->exists($path), 404);

        // The bytes are ciphertext, but force download + a script-less sandbox as
        // defense in depth (the client decrypts in memory; nothing renders here).
        //
        // Blobs are content-addressed: a UUID's bytes never change (a new version
        // is a new blob; deletion 404s). So the ciphertext is safe to cache hard
        // in the browser — this is what lets a second gallery/files visit skip
        // re-downloading every thumbnail. `private` keeps it out of shared proxies;
        // the bytes are ciphertext regardless, so a local disk cache stays ZK-safe.
        return BlobStore::immutableResponse(
            $this->disk()->response($path, 'file', ['Content-Type' => 'application/octet-stream'], 'attachment'),
            $blob,
        );
    }

    /**
     * Delete an owned blob's bytes + ledger row. The client calls this when its
     * sealed index stops referencing the blob (permanent delete, version-cap
     * overflow, rendition swap). Owner-scoped; unknown blob = already gone
     * (idempotent).
     */
    public function deleteBlob(Request $request, string $blob): JsonResponse
    {
        // Use the named route parameter for the same reason as raw() above.
        $blob = (string) ($request->route('blob') ?? $blob);
        abort_unless(Str::isUuid($blob), 404);
        $this->authorizeMutation($request);

        // Owner-scope the lookup and always answer identically (idempotent), so a
        // foreign or missing blob is indistinguishable — no 403-vs-200 ownership
        // oracle, mirroring raw()'s uniform-404 pattern.
        $row = (clone $this->scopeLedger($request))->where('blob', $blob)->first();
        if ($row !== null) {
            $this->disk()->delete($this->module().'/'.$blob);
            $row->delete();
        }

        return response()->json(['deleted' => true]);
    }
}
