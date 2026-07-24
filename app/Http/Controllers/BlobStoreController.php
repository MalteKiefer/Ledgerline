<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\BlobAudit;
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
        return $this->configInt($this->module().'.max_upload_mb', 512);
    }

    /**
     * Read a config value as an int, tolerating scalar/numeric-string values
     * (env-sourced config often arrives as a string). Narrows the `mixed` config
     * value before casting; identical result to `(int) config(...)` for scalars,
     * falls back to the default for non-scalar (null/array) values.
     */
    private function configInt(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_scalar($value) ? (int) $value : $default;
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

        $request->validate([
            'blobs' => ['present', 'array', 'max:100000'],
            'blobs.*' => ['uuid'],
        ]);

        $lockId = $this->reconcileLockId($request);
        $live = array_flip($request->collect('blobs')->map(static fn ($b): string => is_scalar($b) ? (string) $b : '')->all());
        $grace = Carbon::now()->subHours($this->configInt($this->module().'.blob_orphan_grace_hours', 24));
        $disk = $this->disk();
        $prefix = $this->module();

        // Serialize against the ledger scope's lock so concurrent reconciles from
        // different members of the same shared scope don't race against each other.
        $this->withUserLock($lockId, function () use ($request, $live, $grace, $disk, $prefix, $lockId): void {
            (clone $this->scopeLedger($request))
                ->where('created_at', '<', $grace)
                ->orderBy('blob')
                ->chunkById(500, function (Collection $rows) use ($live, $disk, $prefix, $lockId): void {
                    foreach ($rows as $row) {
                        // The blob id is the row's primary key ($primaryKey = 'blob'),
                        // a UUID string; narrow the mixed key before use as a string.
                        $key = $row->getKey();
                        $blob = is_scalar($key) ? (string) $key : '';
                        if ($blob === '' || isset($live[$blob])) {
                            continue;
                        }
                        $size = $row->getAttribute('size');
                        $disk->delete($prefix.'/'.$blob);
                        $row->delete();
                        // Forensic trail: a reconcile freed this grace-expired,
                        // no-longer-referenced blob. This is the path that reclaimed
                        // the missing shard in the incident — every deletion is logged
                        // with the actor so it is always attributable.
                        BlobAudit::record('reconcile_delete', $prefix, [
                            'blob' => $blob,
                            'size' => is_numeric($size) ? (int) $size : null,
                            'user_id' => $lockId,
                            'reason' => 'reconcile',
                        ]);
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
        $size = (int) $request->file('file')->getSize();
        $model::create(array_merge($this->stampAttributes($request), [
            'blob' => $id,
            'size' => $size,
            'created_at' => $this->stampedAt(),
        ]));

        BlobAudit::record('create', $this->module(), [
            'blob' => $id,
            'size' => $size,
            'sha256' => BlobAudit::hashSmallFile((string) $request->file('file')->getRealPath(), $size),
            'user_id' => $uid,
            'reason' => 'upload',
        ]);

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
        $request->validate([
            // max_upload_mb bounds a single POST body — irrelevant here since the
            // file arrives in small parts. Bound by the S3 multipart ceiling
            // instead (10 000 parts) so multi-GB uploads are allowed.
            'size' => ['required', 'integer', 'min:1', 'max:'.(10000 * self::CHUNK_PART_SIZE)],
        ]);
        $user = $this->requireUser($request);
        abort_if($this->quotaExceeded($this->chunkOwnerId($request), $request->integer('size')), 413, __('files.quota_exceeded'));

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
        $part = $request->integer('part');
        $res = $this->s3()->uploadPart([
            'Bucket' => $this->bucket(), 'Key' => $s['key'], 'UploadId' => $s['uploadId'],
            'PartNumber' => $part,
            'Body' => fopen($request->file('chunk')->getRealPath(), 'r'),
        ]);

        return response()->json(['part' => $part, 'etag' => $res['ETag']]);
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
        $token = $request->string('token')->toString();
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
        $contentLength = $this->s3()->headObject(['Bucket' => $this->bucket(), 'Key' => $s['key']])['ContentLength'] ?? 0;
        $size = is_scalar($contentLength) ? (int) $contentLength : 0;
        $model = $this->blobModel();
        $blobId = is_scalar($s['id']) ? (string) $s['id'] : '';
        $model::firstOrCreate(
            ['blob' => $blobId],
            array_merge($this->chunkLedgerAttributes($s), ['size' => $size, 'created_at' => $this->stampedAt()]),
        );
        Cache::forget($this->chunkKey($token));

        // Large (chunked) blobs are not hashed here — reading a multi-GB object back
        // would be prohibitive; the ref + size + actor still give full traceability.
        BlobAudit::record('create', $this->module(), [
            'blob' => $blobId,
            'size' => $size,
            'user_id' => $this->chunkOwnerId($request),
            'reason' => 'upload_chunked',
        ]);

        return response()->json(['id' => $s['id']], 201);
    }

    /** Abort a multipart upload and drop the staged parts. */
    public function chunkAbort(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);
        $token = $request->string('token')->toString();
        // Tolerate an already-gone session (nothing to abort) so aborting is
        // always safe/idempotent and never a dead-end 404.
        $user = $this->requireUser($request);
        $s = Cache::get($this->chunkKey($token));
        $actor = is_array($s) ? ($s['actor'] ?? 0) : null;
        if (is_array($s) && is_scalar($actor) && (int) $actor === (int) $user->id) {
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
        $disk = config('files.disk');
        $diskName = is_scalar($disk) ? (string) $disk : '';
        $bucket = config('filesystems.disks.'.$diskName.'.bucket');

        return is_scalar($bucket) ? (string) $bucket : '';
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
        $s = Cache::get($this->chunkKey($request->string('token')->toString()));
        abort_unless(is_array($s), 404);
        $actor = $s['actor'] ?? 0;
        abort_if(! is_scalar($actor) || (int) $actor !== (int) $user->id, 404);

        // The session was stored (chunkInit) as a string-keyed array; re-key on the
        // string cast so the mixed-keyed cache read matches the declared shape
        // without an inline type override. Values are preserved verbatim.
        $session = [];
        foreach ($s as $k => $v) {
            $session[(string) $k] = $v;
        }

        return $session;
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
        return $this->configInt($this->module().'.quota_mb', 0) * 1024 * 1024;
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
     * Batch raw fetch (Store v3 §10.4/A4): stream many owned ciphertext blobs in a
     * single response so a cold shard/thumbnail load at 18k needs one round-trip
     * instead of hundreds. Owner-scoped exactly like raw() — only blobs present in
     * the caller's ledger are emitted; unknown/foreign/missing ids are silently
     * skipped (404-hiding, the client tolerates gaps and falls back to raw()).
     *
     * Wire format (self-describing, streamable): for each returned blob, in request
     * order, a frame `[u32le idLen][id utf8][u32le dataLen][ciphertext]`. The bytes
     * are the same framed secretstream ciphertext raw() serves. Never any plaintext.
     */
    public function rawBatch(Request $request): StreamedResponse
    {
        $request->validate([
            'blobs' => ['required', 'array', 'max:512'],
            'blobs.*' => ['required', 'string', 'uuid'],
        ]);

        // Only ids that are BOTH owned (in the ledger) AND present on disk, keeping
        // the caller's request order for deterministic client-side splitting.
        $requested = array_values(array_unique(
            $request->collect('blobs')->map(static fn ($b): string => is_scalar($b) ? (string) $b : '')->all()
        ));
        $owned = (clone $this->scopeLedger($request))
            ->whereIn('blob', $requested)
            ->pluck('blob')
            ->all();
        $ownedSet = array_flip(array_values(array_filter($owned, 'is_string')));

        $disk = $this->disk();
        $prefix = $this->module();
        $ids = array_values(array_filter($requested, static fn (string $b): bool => isset($ownedSet[$b])));

        return response()->stream(function () use ($ids, $disk, $prefix): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            foreach ($ids as $blob) {
                $path = $prefix.'/'.$blob;
                if (! $disk->exists($path)) {
                    continue;
                }
                $stream = $disk->readStream($path);
                if ($stream === false || $stream === null) {
                    continue;
                }
                $size = (int) $disk->size($path);
                fwrite($out, pack('V', strlen($blob)).$blob.pack('V', $size));
                stream_copy_to_stream($stream, $out);
                fclose($stream);
                flush();
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
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
            $size = $row->getAttribute('size');
            $this->disk()->delete($this->module().'/'.$blob);
            $row->delete();
            BlobAudit::record('delete', $this->module(), [
                'blob' => $blob,
                'size' => is_numeric($size) ? (int) $size : null,
                'user_id' => $this->ownerId($request),
                'reason' => 'client_delete',
            ]);
        }

        return response()->json(['deleted' => true]);
    }
}
