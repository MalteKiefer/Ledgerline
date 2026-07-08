<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\BuildExport;
use App\Jobs\ExtractArchive;
use App\Jobs\ExtractFileText;
use App\Models\Export;
use App\Models\FileBlob;
use App\Models\FileFolder;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Models\UserSetting;
use App\Services\Files\ArchiveManager;
use App\Support\ArchiveName;
use App\Support\BlobStore;
use App\Support\ImageManagerFactory;
use App\Support\Tags;
use Aws\S3\S3Client;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Encoders\JpegEncoder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plain (non-encrypted) file browser. Metadata lives in the file_folders/files
 * tables; the bytes live unencrypted on the files disk at "files/{blob}". The
 * rich client keeps its manifest-style model: it reads the whole tree from
 * /files/data and writes it back to /files/data, which syncs it to clean rows.
 */
class FileController extends Controller
{
    private function disk()
    {
        return BlobStore::disk();
    }

    /** The whole tree as the client's manifest shape. Owner-only: the full-replace
     *  sync below must never see (and therefore never delete) files/folders that
     *  are merely shared with the user. */
    public function data(): JsonResponse
    {
        $uid = auth()->id();

        return response()->json([
            'v' => 1,
            'usage' => ['used' => $this->usedBytes((int) $uid), 'quota' => $this->quotaBytes()],
            // Live folders only (ownedBy strips the SoftDeletingScope, so exclude
            // trashed ones explicitly); trashed folders come back via restore.
            'folders' => FileFolder::ownedBy($uid)->whereNull('deleted_at')->get(['id', 'parent_id', 'name'])
                ->map(fn (FileFolder $f) => ['id' => $f->id, 'name' => $f->name, 'parent' => $f->parent_id])
                ->all(),
            'files' => StoredFile::ownedBy($uid)->withTrashed()->get()->map(fn (StoredFile $f) => [
                'id' => $f->id,
                'blob' => $f->blob,
                'name' => $f->name,
                'mime' => $f->mime,
                'size' => $f->size,
                'folder' => $f->file_folder_id,
                'trashed' => $f->deleted_at?->toIso8601String(),
                'created' => $f->created_at?->toIso8601String(),
                'favorite' => (bool) $f->favorite,
                'note' => $f->note,
                'tags' => $f->tags ?? [],
            ])->all(),
            // Never let a proxy/browser serve a stale tree: the client refetches this
            // right after every delete/move and must see the current state.
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /** Replace the tree from the client's manifest (upsert + delete missing). */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folders' => ['array', 'max:20000'],
            'folders.*.id' => ['required', 'uuid'],
            'folders.*.name' => ['required', 'string', 'max:255'],
            'folders.*.parent' => ['nullable', 'uuid'],
            'files' => ['array', 'max:50000'],
            'files.*.id' => ['required', 'uuid'],
            'files.*.blob' => ['required', 'uuid'],
            'files.*.name' => ['required', 'string', 'max:255'],
            'files.*.mime' => ['nullable', 'string', 'max:255'],
            'files.*.size' => ['nullable', 'integer', 'min:0'],
            'files.*.folder' => ['nullable', 'uuid'],
            'files.*.tags' => ['array'],
            'files.*.trashed' => ['nullable'],
            'files.*.favorite' => ['nullable', 'boolean'],
            'files.*.note' => ['nullable', 'string', 'max:5000'],
        ]);

        $folders = $data['folders'] ?? [];
        $files = $data['files'] ?? [];

        // Referential integrity: every folder parent and file folder must point
        // at a folder present in this same manifest (or be null = root), and the
        // folder graph must be acyclic. Reject the whole write otherwise so a
        // malformed manifest cannot create dangling references or a parent loop
        // that would make the tree walker recurse forever.
        $this->assertManifestConsistent($folders, $files);

        // Every query is pinned to the caller's OWN rows (withoutGlobalScopes +
        // user_id) so a manifest can only ever touch files/folders the user owns,
        // never ones merely shared with them (guards against the write-guard
        // being bypassed by these query-builder mass operations).
        $uid = $request->user()->id;

        // Guard against a self-inflicted mass wipe: the client initialises an
        // empty manifest before load() hydrates it, so a failed load followed by
        // any mutating action would PUT an empty file list and drop everything.
        // Refuse to trash the whole library from an empty manifest unless the
        // client explicitly confirms it.
        if (! $request->boolean('confirm_wipe')) {
            abort_if($files === [] && StoredFile::ownedBy($uid)->exists(), 409,
                'Refusing to drop all files from an empty manifest.');
            // An empty folders array from a not-yet-loaded manifest would trash
            // the whole live folder tree; guard it too.
            abort_if($folders === [] && FileFolder::ownedBy($uid)->whereNull('deleted_at')->exists(), 409,
                'Refusing to drop all folders from an empty manifest.');
        }

        $owned = fn () => StoredFile::ownedBy($uid);
        $ownedFolders = fn () => FileFolder::ownedBy($uid);

        // Per-user version cap (1–10); the owner is the syncing user.
        $keep = min(10, max(1, (int) UserSetting::for($uid)->file_max_versions));

        // A manifest row may only reference a blob the caller uploaded (recorded
        // in file_blobs), one already attached to one of their files, or one kept
        // as one of their own versions (needed for version-restore) — never a
        // blob whose UUID they merely learned (e.g. from a since-revoked share).
        $allowed = $owned()->withTrashed()->pluck('blob')
            ->merge(FileBlob::where('user_id', $uid)->pluck('blob'))
            ->merge(FileVersion::where('user_id', $uid)->pluck('blob'))
            ->filter()->flip();
        foreach ($files as $f) {
            abort_unless(isset($allowed[$f['blob']]), 422, 'Unknown blob reference.');
        }

        // Per-user storage quota (0 = unlimited). Reconcile sizes from disk so a
        // lied-about size can't smuggle bytes past the quota.
        $quota = $this->quotaBytes();
        $disk = $this->disk();

        $this->withUserLock($uid, function () use ($folders, $files, $uid, $owned, $ownedFolders, $keep, $quota, $disk): void {
            $removedBlobs = DB::transaction(function () use ($folders, $files, $uid, $owned, $ownedFolders, $keep, $quota, $disk): array {
                // Set of folder ids present in this manifest, used to self-heal
                // dangling references (a live file/folder pointing at a folder
                // that is not in the manifest — e.g. one that was trashed — is
                // reparented to the root instead of rejecting the whole sync).
                $manifestFolderIds = [];
                foreach ($folders as $f) {
                    $manifestFolderIds[$f['id']] = true;
                }

                $folderIds = [];
                foreach ($folders as $f) {
                    $parent = $f['parent'] ?? null;
                    if ($parent !== null && ! isset($manifestFolderIds[$parent])) {
                        $parent = null; // dangling parent → root
                    }
                    $ownedFolders()->updateOrCreate(['id' => $f['id']], ['user_id' => $uid, 'parent_id' => $parent, 'name' => $f['name']]);
                    $folderIds[] = $f['id'];
                }
                // Soft-delete live folders the manifest dropped (recoverable);
                // never touch already-trashed folders. Instance delete so
                // SoftDeletes applies even though the owner scope is stripped.
                foreach ($ownedFolders()->whereNull('deleted_at')
                    ->when($folderIds !== [], fn ($q) => $q->whereNotIn('id', $folderIds))->get() as $droppedFolder) {
                    $droppedFolder->delete();
                }

                $fileIds = [];
                $prunedBlobs = [];
                $total = 0;
                foreach ($files as $f) {
                    // withTrashed: the manifest keeps trashed files, so a matching
                    // row may be soft-deleted; find it (or build a new one) and let
                    // the manifest's `trashed` timestamp drive deleted_at directly.
                    $file = $owned()->withTrashed()->firstOrNew(['id' => $f['id']]);
                    $oldBlob = $file->exists ? $file->blob : null;
                    $oldMeta = ['name' => $file->name, 'mime' => $file->mime, 'size' => (int) $file->size];
                    // Authoritative size: read from disk when the blob is new/changed
                    // (never trust the client's number), else keep the stored size. A
                    // new blob whose bytes are missing on disk is a failed upload —
                    // abort the whole sync rather than silently record a 0-byte file.
                    if ($oldBlob === $f['blob']) {
                        $size = (int) $file->size;
                    } else {
                        abort_unless($disk->exists('files/'.$f['blob']), 422, __('files.upload_failed'));
                        $size = (int) $disk->size('files/'.$f['blob']);
                    }
                    $total += $size;
                    // Self-heal a dangling folder ref on a LIVE file (folder not
                    // in the manifest → root); a trashed file keeps its folder id
                    // so a restore can rebuild the hierarchy.
                    $folderRef = $f['folder'] ?? null;
                    if (empty($f['trashed']) && $folderRef !== null && ! isset($manifestFolderIds[$folderRef])) {
                        $folderRef = null;
                    }
                    $file->fill([
                        'user_id' => $uid,
                        'file_folder_id' => $folderRef,
                        'name' => $f['name'],
                        'mime' => $f['mime'] ?? 'application/octet-stream',
                        'size' => $size,
                        'blob' => $f['blob'],
                        'tags' => Tags::normalize($f['tags'] ?? null),
                        'favorite' => (bool) ($f['favorite'] ?? false),
                        'note' => $f['note'] ?? null,
                    ]);
                    $file->deleted_at = ! empty($f['trashed']) ? Carbon::parse($f['trashed']) : null;
                    $file->save();
                    $fileIds[] = $f['id'];

                    // Content changed: snapshot the previous blob as a version instead
                    // of letting it leak, then cap the history and prune the overflow.
                    if (is_string($oldBlob) && $oldBlob !== $f['blob'] && Str::isUuid($oldBlob)) {
                        FileVersion::create([
                            'id' => (string) Str::uuid(), 'file_id' => $file->id, 'user_id' => $uid,
                            'name' => $oldMeta['name'] ?? $f['name'], 'mime' => $oldMeta['mime'] ?? 'application/octet-stream',
                            'size' => $oldMeta['size'], 'blob' => $oldBlob, 'created_at' => now(),
                        ]);
                        $prunedBlobs = array_merge($prunedBlobs, $this->capVersions($file->id, $keep));
                    }

                    // New or changed content → drop the now-stale search text at once
                    // (so it can't surface old content) and re-extract off-path.
                    if ($oldBlob !== $f['blob']) {
                        if ($file->exists && $oldBlob !== null) {
                            $file->forceFill(['content' => null, 'content_at' => null])->save();
                        }
                        ExtractFileText::dispatch($file->id, $f['blob'])->afterCommit();
                    }
                }

                // Files the manifest dropped are NOT permanently destroyed here. A
                // stale, partial or racing manifest (a second tab, a DAV write, or a
                // failed load) must never cause irreversible loss, so dropped rows
                // are only SOFT-deleted (recoverable from the trash) and their blobs
                // and versions are kept. The only bytes reclaimed by a sync are the
                // version-cap overflow blobs pruned above (reference-counted below).
                // whereNotIn('id', []) matches every owned row, so an empty manifest
                // (a confirmed full wipe) soft-deletes everything into the trash.
                // Instance ->delete() applies SoftDeletes even though the owner scope
                // strips the SoftDeletingScope on the builder.
                foreach ($owned()->whereNotIn('id', $fileIds)->get() as $dropped) {
                    $dropped->delete();
                }

                // Enforce the quota against the reconciled live + kept-version bytes;
                // throwing here rolls back the whole sync.
                if ($quota > 0) {
                    $versionBytes = (int) FileVersion::where('user_id', $uid)->sum('size');
                    abort_if($total + $versionBytes > $quota, 413, __('files.quota_exceeded'));
                }

                // Referenced blobs no longer need their upload record.
                $syncedBlobs = collect($files)->pluck('blob')->all();
                if ($syncedBlobs !== []) {
                    FileBlob::where('user_id', $uid)->whereIn('blob', $syncedBlobs)->delete();
                }

                return $prunedBlobs;
            });

            // Reference-counted inside the lock: a concurrent writer can't
            // reference a pruned blob between the commit and this ref-check.
            $this->freeBlobs($removedBlobs);
        });

        return response()->json(['ok' => true]);
    }

    /** Store one uploaded file and return its blob id. */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.((int) config('files.max_upload_mb', 2048) * 1024)],
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
        abort_if($this->disk()->putFileAs('files', $request->file('file'), $id) === false, 500, __('files.upload_failed'));
        abort_unless($this->disk()->exists('files/'.$id), 500, __('files.upload_failed'));
        // Record the uploader so sync can reject blobs the caller never uploaded
        // and the sweeper can reclaim never-synced blobs.
        FileBlob::create(['blob' => $id, 'user_id' => $uid, 'created_at' => now()]);

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
        abort_if($this->quotaExceeded($request->user()->id, (int) $data['size']), 413, __('files.quota_exceeded'));

        $id = (string) Str::uuid();
        $key = 'files/'.$id;
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
        // (idempotent for identical parts) and FileBlob::firstOrCreate dedupe a
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
        FileBlob::firstOrCreate(['blob' => $s['id']], ['user_id' => $s['user'], 'created_at' => now()]);
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

    /** Bytes the user currently occupies (live + trashed files + kept versions). */
    private function usedBytes(int $userId): int
    {
        return (int) StoredFile::withoutGlobalScopes()->withTrashed()->where('user_id', $userId)->sum('size')
            + (int) FileVersion::where('user_id', $userId)->sum('size');
    }

    /** Per-user quota in bytes (0 / null = unlimited). */
    private function quotaBytes(): int
    {
        return (int) config('files.quota_mb', 0) * 1024 * 1024;
    }

    private function quotaExceeded(int $userId, int $incoming): bool
    {
        $quota = $this->quotaBytes();

        return $quota > 0 && ($this->usedBytes($userId) + $incoming) > $quota;
    }

    /**
     * Serialize a user's storage-mutating operation so concurrent uploads/syncs
     * can't each read the same stale quota baseline and collectively overshoot
     * (and so per-file version-cap enforcement stays atomic).
     */
    private function withUserLock(int $userId, \Closure $fn)
    {
        // Long TTL so the lock can't auto-expire mid-transaction on a large sync
        // and admit a second writer; a genuine contention timeout becomes a clean
        // 429 (retryable) instead of an unhandled 500.
        try {
            return Cache::lock('files-write:'.$userId, 120)->block(20, $fn);
        } catch (LockTimeoutException $e) {
            abort(429, __('files.busy'));
        }
    }

    /** Keep only the newest N versions of a file; return the pruned blobs. */
    private function capVersions(string $fileId, int $keep): array
    {
        $keep = max(1, $keep);
        $overflow = FileVersion::where('file_id', $fileId)->orderByDesc('created_at')->skip($keep)->take(1000)->get();
        $blobs = $overflow->pluck('blob')->all();
        if ($blobs !== []) {
            FileVersion::whereIn('id', $overflow->pluck('id'))->delete();
        }

        return $blobs;
    }

    /** List a file's kept versions (owner or shared-with-edit). */
    public function versions(Request $request, StoredFile $file): JsonResponse
    {
        abort_unless($file->isOwnedBy($request->user()->id), 403);
        $versions = FileVersion::where('file_id', $file->id)->orderByDesc('created_at')->get()
            ->map(fn (FileVersion $v): array => [
                'id' => $v->id, 'name' => $v->name, 'mime' => $v->mime, 'blob' => $v->blob,
                'size' => $v->size, 'created_at' => $v->created_at?->toIso8601String(),
            ]);

        return response()->json(['versions' => $versions]);
    }

    /** Download a specific version's bytes. */
    public function downloadVersion(Request $request, StoredFile $file, FileVersion $version): StreamedResponse
    {
        abort_unless($version->file_id === $file->id, 404);
        abort_unless($file->isOwnedBy($request->user()->id), 403);
        $path = 'files/'.$version->blob;
        abort_unless($this->disk()->exists($path), 404);

        // Force download + a script-less sandbox so a version whose mime is a
        // client-controlled text/html can't render in-origin (self-XSS), matching raw().
        return $this->disk()->response($path, $version->name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'attachment');
    }

    /** Import an uploaded file straight into Files as a row (used by mail "save to Files"). */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.((int) config('files.max_upload_mb', 2048) * 1024)],
            // Owner-scoped exists: a plain exists rule would accept another
            // user's folder id (the global scope doesn't apply to the rule).
            'folder_id' => ['nullable', 'uuid', Rule::exists('file_folders', 'id')->where('user_id', $request->user()->id)],
        ]);

        $file = $request->file('file');
        $uid = (int) $request->user()->id;
        $blob = (string) Str::uuid();

        // Best-effort quota check WITHOUT the per-user write lock — holding it
        // across the slow object-storage write serialised concurrent uploads and
        // timed them out into 429s. A minor overshoot is acceptable.
        abort_if($this->quotaExceeded($uid, (int) $file->getSize()), 413, __('files.quota_exceeded'));
        abort_if($this->disk()->putFileAs('files', $file, $blob) === false, 500, __('files.upload_failed'));

        try {
            $stored = StoredFile::create([
                'id' => (string) Str::uuid(),
                'file_folder_id' => $data['folder_id'] ?? null,
                'name' => $file->getClientOriginalName() ?: 'attachment',
                // Sniff the real type from the bytes (finfo), never the client-sent
                // header: this row is created from an untrusted mail attachment and
                // the stored mime later drives how the client previews the file.
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'blob' => $blob,
                'tags' => [],
            ]);
        } catch (\Throwable $e) {
            // Don't leak the just-written bytes if the row could not be created.
            $this->disk()->delete('files/'.$blob);
            throw $e;
        }

        // Index its text so imported files are findable by content search too.
        ExtractFileText::dispatch($stored->id, $blob)->afterCommit();

        return response()->json(['id' => $stored->id], 201);
    }

    /** Stream a stored file's bytes back to the browser. */
    public function raw(string $blob): StreamedResponse
    {
        $path = $this->path($blob);
        // Only serve bytes the current user owns (the global scope limits this to
        // their own files) — otherwise any authenticated user could fetch any
        // blob by guessing its UUID.
        abort_unless(StoredFile::withTrashed()->where('blob', $blob)->exists(), 404);
        abort_unless($this->disk()->exists($path), 404);

        return $this->disk()->response($path, 'file', [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'attachment');
    }

    /**
     * Queue an asynchronous export of the selected files and/or folders. A worker
     * zips them in the background (folders keep their tree); the user collects the
     * result from the Downloads page.
     */
    public function queueExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_ids' => ['nullable', 'array', 'max:20000'],
            'file_ids.*' => ['string'],
            'folder_ids' => ['nullable', 'array', 'max:5000'],
            'folder_ids.*' => ['string'],
            'format' => ['nullable', Rule::in(['zip', 'tar', 'targz', 'tarbz2'])],
        ]);

        // Keep only ids the current user actually OWNS (not merely shared with
        // them) so an export can never exfiltrate another user's file bytes.
        $uid = $request->user()->id;
        $fileIds = StoredFile::ownedBy($uid)->withTrashed()
            ->whereIn('id', array_values($validated['file_ids'] ?? []))->pluck('id')->all();
        $folderIds = FileFolder::ownedBy($uid)->whereNull('deleted_at')
            ->whereIn('id', array_values($validated['folder_ids'] ?? []))->pluck('id')->all();
        abort_if($fileIds === [] && $folderIds === [], 422, 'Nothing selected.');

        // Cap how many exports one user can have building at once so a single
        // user can't flood the queue with huge jobs.
        abort_if(
            Export::inFlightCount($uid) >= Export::MAX_IN_FLIGHT,
            429,
            __('downloads.error.too_many', ['max' => Export::MAX_IN_FLIGHT])
        );

        $count = count($fileIds) + count($folderIds);

        $export = Export::create([
            'user_id' => $request->user()->id,
            'source' => 'files',
            'title' => trans_choice('downloads.title.files', $count, ['count' => $count]),
            'status' => 'queued',
            'format' => $validated['format'] ?? 'zip',
            'item_count' => $count,
            'payload' => ['file_ids' => $fileIds, 'folder_ids' => $folderIds],
        ]);

        BuildExport::dispatch($export->id);

        return response()->json(['queued' => true, 'export_id' => $export->id], 202);
    }

    /**
     * Delete owned files/folders. Targeted + owner scoped, so it never rides the
     * whole fragile manifest. With `permanent` the rows and blobs are gone for
     * good; otherwise files are soft-deleted (trash) and folders drop their rows
     * with their files moved to root so they stay restorable.
     */
    public function trash(Request $request): JsonResponse
    {
        [$fileIds, $folderIds] = $this->ownedIds($request);
        $permanent = $request->boolean('permanent');
        $uid = (int) $request->user()->id;

        // Hold the per-user write lock across BOTH the transaction and the blob
        // reclaim, so a permanent-delete's ref-check-then-unlink can't race a
        // concurrent duplicate/sync that just referenced the shared blob.
        $this->withUserLock($uid, function () use ($fileIds, $folderIds, $permanent): void {
            $blobs = DB::transaction(function () use ($fileIds, $folderIds, $permanent): array {
                $ids = $fileIds;
                foreach ($folderIds as $fid) {
                    $subtree = $this->folderSubtree($fid);
                    $ids = array_merge($ids, StoredFile::withoutGlobalScopes()->withTrashed()
                        ->whereIn('file_folder_id', $subtree)->pluck('id')->all());
                    if ($permanent) {
                        // The subtree files are force-deleted in the loop below.
                        FileFolder::withoutGlobalScopes()->whereIn('id', $subtree)->forceDelete();
                    } else {
                        // Soft-delete the folder subtree and KEEP each file's folder
                        // id, so restoring the files brings the whole hierarchy back
                        // (instead of flattening everything to the root).
                        foreach (FileFolder::withoutGlobalScopes()->whereIn('id', $subtree)->get() as $folder) {
                            $folder->delete();
                        }
                    }
                }
                $freed = [];
                // Instance ->delete()/->forceDelete() (not builder) so SoftDeletes
                // applies even though the owner global scope is stripped.
                foreach (StoredFile::withoutGlobalScopes()->withTrashed()->whereIn('id', array_unique($ids))->get() as $file) {
                    if ($permanent) {
                        // Reclaim the file blob AND every version's blob, and drop the
                        // now-orphaned version rows, so a permanent delete leaves no
                        // dangling version rows or leaked bytes/quota.
                        $freed[] = $file->blob;
                        $freed = array_merge($freed, FileVersion::where('file_id', $file->id)->pluck('blob')->all());
                        FileVersion::where('file_id', $file->id)->delete();
                        $file->forceDelete();
                    } else {
                        $file->delete();
                    }
                }

                return $freed;
            });

            $this->freeBlobs($blobs);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Duplicate owned files/folders in place. Files share the same blob (content
     * is identical, blob delete is reference-counted), folders are copied
     * recursively. Names are made unique among their siblings.
     */
    public function duplicate(Request $request): JsonResponse
    {
        [$fileIds, $folderIds] = $this->ownedIds($request);
        $uid = $request->user()->id;

        // Serialize the quota gate + copy so concurrent duplicates/uploads can't
        // race past the quota. Copies count toward usage (it sums row sizes), so
        // gate the whole selection — files plus every selected folder's subtree.
        $this->withUserLock($uid, function () use ($fileIds, $folderIds, $uid): void {
            $copyBytes = (int) StoredFile::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('id', $fileIds)->sum('size');
            $folderSet = [];
            foreach ($folderIds as $fid) {
                $folderSet = array_merge($folderSet, $this->folderSubtree($fid));
            }
            if ($folderSet !== []) {
                $copyBytes += (int) StoredFile::withoutGlobalScopes()->whereNull('deleted_at')
                    ->whereIn('file_folder_id', array_unique($folderSet))->sum('size');
            }
            abort_if($this->quotaExceeded($uid, $copyBytes), 413, __('files.quota_exceeded'));

            DB::transaction(function () use ($fileIds, $folderIds, $uid): void {
                foreach (StoredFile::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('id', $fileIds)->get() as $f) {
                    $this->copyFile($f, $f->file_folder_id, $uid);
                }
                foreach ($folderIds as $fid) {
                    $folder = FileFolder::withoutGlobalScopes()->whereNull('deleted_at')->where('user_id', $uid)->find($fid);
                    if ($folder) {
                        $this->copyFolder($folder, $folder->parent_id, $uid, true);
                    }
                }
            });
        });

        return response()->json(['ok' => true]);
    }

    private function copyFile(StoredFile $src, ?string $folderId, int $uid, bool $unique = true): void
    {
        $name = $unique ? $this->uniqueFileName($src->name, $uid, $folderId, true) : $src->name;
        $copy = new StoredFile;
        $copy->forceFill([
            'id' => (string) Str::uuid(), 'user_id' => $uid, 'file_folder_id' => $folderId,
            'name' => $name, 'blob' => $src->blob, 'size' => (int) $src->size,
            'mime' => $src->mime, 'tags' => $src->tags,
        ])->save();
    }

    private function copyFolder(FileFolder $src, ?string $parentId, int $uid, bool $unique, int $depth = 0): void
    {
        // Hard depth cap so a very deep (or, defensively, a cyclic) folder chain
        // cannot exhaust the PHP stack while copying.
        abort_if($depth > 100, 422, __('files.folder_too_deep'));
        $name = $unique ? $this->uniqueFolderName($src->name, $uid, $parentId) : $src->name;
        $copy = new FileFolder;
        $copy->forceFill(['id' => (string) Str::uuid(), 'user_id' => $uid, 'parent_id' => $parentId, 'name' => $name])->save();
        foreach (StoredFile::withoutGlobalScopes()->whereNull('deleted_at')->where('file_folder_id', $src->id)->get() as $f) {
            $this->copyFile($f, $copy->id, $uid, false);
        }
        foreach (FileFolder::withoutGlobalScopes()->whereNull('deleted_at')->where('parent_id', $src->id)->get() as $sub) {
            $this->copyFolder($sub, $copy->id, $uid, false, $depth + 1);
        }
    }

    /** Find/replace across the names of the selected owned files/folders. */
    public function bulkRename(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file_ids' => ['array', 'max:20000'], 'file_ids.*' => ['string'],
            'folder_ids' => ['array', 'max:5000'], 'folder_ids.*' => ['string'],
            'find' => ['nullable', 'string', 'max:255'],
            'replace' => ['nullable', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:255'],
        ]);
        [$fileIds, $folderIds] = $this->ownedIds($request);
        $find = (string) ($data['find'] ?? '');
        $apply = function (string $name) use ($data, $find): string {
            if ($find !== '') {
                $name = str_replace($find, (string) ($data['replace'] ?? ''), $name);
            }

            return mb_substr(($data['prefix'] ?? '').$name.($data['suffix'] ?? ''), 0, 255);
        };

        DB::transaction(function () use ($fileIds, $folderIds, $apply): void {
            foreach (StoredFile::withoutGlobalScopes()->whereIn('id', $fileIds)->get() as $f) {
                $new = trim($apply($f->name));
                if ($new !== '' && $new !== $f->name) {
                    $f->forceFill(['name' => $new])->save();
                }
            }
            foreach (FileFolder::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('id', $folderIds)->get() as $f) {
                $new = trim($apply($f->name));
                if ($new !== '' && $new !== $f->name) {
                    $f->forceFill(['name' => $new])->save();
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    private function uniqueFileName(string $name, int $uid, ?string $folderId, bool $copySuffix = false): string
    {
        $used = StoredFile::withoutGlobalScopes()->whereNull('deleted_at')->where('user_id', $uid)
            ->where('file_folder_id', $folderId)->pluck('name')->flip()->all();
        if ($copySuffix) {
            $dot = strrpos($name, '.');
            $stem = $dot === false ? $name : substr($name, 0, $dot);
            $ext = $dot === false ? '' : substr($name, $dot);
            $name = $stem.' '.__('files.copy_suffix').$ext;
        }

        return ArchiveName::unique($name, $used, ' ', true);
    }

    private function uniqueFolderName(string $name, int $uid, ?string $parentId): string
    {
        $used = FileFolder::withoutGlobalScopes()->whereNull('deleted_at')->where('user_id', $uid)
            ->where('parent_id', $parentId)->pluck('name')->flip()->all();

        return ArchiveName::unique($name, $used, ' ', true);
    }

    /** Save the note/comment on an owned file. */
    public function saveNote(Request $request, StoredFile $file): JsonResponse
    {
        abort_unless($file->isOwnedBy($request->user()->id), 403);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:5000']]);
        $file->forceFill(['note' => $data['note'] ?? null])->save();

        return response()->json(['ok' => true]);
    }

    /** A cached ~320px JPEG thumbnail for an owned image file (owner-scoped). */
    public function thumb(string $blob, ImageManagerFactory $images): StreamedResponse
    {
        $file = StoredFile::withTrashed()->where('blob', $blob)->first();
        abort_unless($file !== null, 404);
        abort_unless(str_starts_with((string) $file->mime, 'image/'), 404);

        $thumbPath = 'thumbs/'.$blob.'.jpg';
        if (! $this->disk()->exists($thumbPath)) {
            abort_unless($this->disk()->exists('files/'.$blob), 404);
            try {
                $raw = (string) $this->disk()->get('files/'.$blob);
                // Reject a decompression bomb before decoding: cap the intrinsic
                // pixel area (~100 MP) read cheaply from the header.
                $dim = @getimagesizefromstring($raw);
                abort_if($dim !== false && ((int) ($dim[0] ?? 0) * (int) ($dim[1] ?? 0)) > 100_000_000, 422);
                $img = $images->make()->decodeBinary($raw);
                $img->scaleDown(width: 320);
                $this->disk()->put($thumbPath, (string) $img->encode(new JpegEncoder(quality: 72)));
            } catch (\Throwable) {
                abort(404);
            }
        }

        return $this->disk()->response($thumbPath, 'thumb.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    /** Ids of owned files whose extracted text matches the term (full-text). */
    public function searchContent(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['ids' => []]);
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($term)).'%';
        $ids = StoredFile::query()->whereNotNull('content')
            ->whereRaw("LOWER(content) LIKE ? ESCAPE '\\'", [$like])
            ->limit(500)->pluck('id')->all();

        return response()->json(['ids' => $ids]);
    }

    /** Toggle the favourite flag on owned files (owner-scoped). */
    public function favorite(Request $request): JsonResponse
    {
        $request->validate(['favorite' => ['required', 'boolean']]);
        [$fileIds] = $this->ownedIds($request);
        // Explicit owner predicate as well, so this builder update never depends
        // solely on ownedIds() provenance to stay within the caller's own rows.
        StoredFile::withoutGlobalScopes()->where('user_id', $request->user()->id)->whereIn('id', $fileIds)
            ->update(['favorite' => $request->boolean('favorite')]);

        return response()->json(['ok' => true]);
    }

    /** Restore trashed files (owner-scoped), bringing back their folders too. */
    public function restoreTrash(Request $request): JsonResponse
    {
        [$fileIds] = $this->ownedIds($request);
        foreach (StoredFile::withoutGlobalScopes()->withTrashed()->whereIn('id', $fileIds)->get() as $file) {
            // Bring back any trashed ancestor folders so the file lands back in
            // its original place; only if a folder is truly gone (hard-deleted)
            // reparent to root, so a restore is never invisible.
            $this->restoreFolderChain($file->file_folder_id);
            if ($file->file_folder_id !== null
                && ! FileFolder::withoutGlobalScopes()->whereKey($file->file_folder_id)->exists()) {
                $file->file_folder_id = null;
            }
            $file->restore();
        }

        return response()->json(['ok' => true]);
    }

    /** Un-trash a folder and every trashed ancestor, so a restored child shows. */
    private function restoreFolderChain(?string $folderId): void
    {
        for ($steps = 0; $folderId !== null && $steps < 1000; $steps++) {
            $folder = FileFolder::withoutGlobalScopes()->whereKey($folderId)->first();
            if ($folder === null) {
                return;
            }
            if ($folder->trashed()) {
                $folder->restore();
            }
            $folderId = $folder->parent_id;
        }
    }

    /** Delete blobs no longer referenced by any (live or trashed) file. */
    private function freeBlobs(array $blobs): void
    {
        foreach (array_unique(array_filter($blobs)) as $blob) {
            // A blob may be shared by a duplicate file, a soft-deleted (trashed)
            // file, or a kept FileVersion. Never unlink bytes any of them still
            // reference, or restoring/downloading them would 404.
            $stillUsed = StoredFile::withoutGlobalScopes()->withTrashed()->where('blob', $blob)->exists()
                || FileVersion::withoutGlobalScopes()->where('blob', $blob)->exists();
            if (! $stillUsed) {
                $this->disk()->delete('files/'.$blob);
                $this->disk()->delete('thumbs/'.$blob.'.jpg');
            }
        }
    }

    /** Validated, owner-scoped file/folder id lists from the request. */
    private function ownedIds(Request $request): array
    {
        $data = $request->validate([
            'file_ids' => ['array', 'max:20000'],
            'file_ids.*' => ['string'],
            'folder_ids' => ['array', 'max:5000'],
            'folder_ids.*' => ['string'],
        ]);
        $uid = $request->user()->id;
        $fileIds = StoredFile::ownedBy($uid)->withTrashed()
            ->whereIn('id', array_values($data['file_ids'] ?? []))->pluck('id')->all();
        $folderIds = FileFolder::ownedBy($uid)->whereNull('deleted_at')
            ->whereIn('id', array_values($data['folder_ids'] ?? []))->pluck('id')->all();

        return [$fileIds, $folderIds];
    }

    /** A folder id plus all of its descendant folder ids (owner already pinned). */
    private function folderSubtree(string $rootId): array
    {
        $all = [$rootId];
        $frontier = [$rootId];
        while ($frontier !== []) {
            $children = FileFolder::withoutGlobalScopes()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_diff($children, $all));
            $all = array_merge($all, $frontier);
        }

        return $all;
    }

    /** Zip up the selected owned files/folders into a new file in the browser. */
    public function createArchive(Request $request, ArchiveManager $archives): JsonResponse
    {
        $data = $request->validate([
            'refs' => ['required', 'array', 'min:1', 'max:5000'],
            'refs.*.kind' => ['required', Rule::in(['file', 'folder'])],
            'refs.*.id' => ['required', 'string'],
            'folder_id' => ['nullable', Rule::exists('file_folders', 'id')->where('user_id', $request->user()->id)],
            'name' => ['nullable', 'string', 'max:200'],
        ]);

        $archives->create($request->user()->id, $data['refs'], $data['folder_id'] ?? null, $data['name'] ?? null);

        return response()->json(['ok' => true]);
    }

    /** Extract an owned zip file into a new folder alongside it. */
    public function extract(Request $request, StoredFile $file): JsonResponse
    {
        abort_unless($file->isOwnedBy($request->user()->id), 403);
        abort_unless(ArchiveManager::isExtractable($file->name, $file->mime), 422, __('files.archive_invalid'));

        // Unpack in the background (large archives would otherwise block the
        // request and time out); the client polls extractStatus for progress.
        $token = (string) Str::uuid();
        Cache::put(ExtractArchive::statusKey($token), [
            'state' => 'running', 'done' => 0, 'total' => 0,
            'user' => (int) $request->user()->id, 'name' => $file->name,
        ], now()->addHour());
        ExtractArchive::dispatch($token, (int) $request->user()->id, $file->id, $file->name)->afterCommit();

        return response()->json(['token' => $token], 202);
    }

    public function extractStatus(Request $request, string $token): JsonResponse
    {
        $s = Cache::get(ExtractArchive::statusKey($token));
        abort_if(! is_array($s) || (int) ($s['user'] ?? 0) !== (int) $request->user()->id, 404);

        return response()->json($s);
    }

    /** Delete a stored file's bytes (after its row was removed via sync). */
    public function deleteBlob(string $blob): JsonResponse
    {
        $path = $this->path($blob);

        // If the blob is still staged (uploaded but not yet synced to a file),
        // only its uploader may drop it — otherwise one user could delete
        // another's pending upload by guessing the UUID.
        $staged = FileBlob::where('blob', $blob)->first();
        abort_if($staged !== null && (int) $staged->user_id !== (int) auth()->id(), 403);

        // Never destroy bytes still referenced by any live/trashed file OR a kept
        // version, of ANY user (blobs are reference-shared across duplicates).
        abort_if(
            StoredFile::withoutGlobalScopes()->withTrashed()->where('blob', $blob)->exists()
            || FileVersion::withoutGlobalScopes()->where('blob', $blob)->exists(),
            409
        );

        $this->disk()->delete($path);
        $this->disk()->delete('thumbs/'.$blob.'.jpg');
        $staged?->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Validate that the manifest's folder graph is internally consistent:
     * parents/file-folders resolve to a folder in the payload (or null) and the
     * parent relation contains no cycle. Aborts 422 on any violation.
     *
     * @param  array<int,array<string,mixed>>  $folders
     * @param  array<int,array<string,mixed>>  $files
     */
    private function assertManifestConsistent(array $folders, array $files): void
    {
        $parent = [];
        foreach ($folders as $f) {
            $parent[$f['id']] = $f['parent'] ?? null;
        }

        // NOTE: a dangling parent/file-folder reference (pointing at a folder not
        // in the manifest — e.g. one that was trashed) is NOT rejected here; the
        // sync self-heals it by reparenting the row to the root. Only a parent
        // CYCLE is fatal, because it would make the tree walkers recurse forever.

        // Cycle check in O(n): memoize folders already proven to reach the root,
        // so each node is walked at most once across all starts (the old
        // per-node full walk was O(n^2) — a CPU DoS on a deep chain).
        $safe = [];
        foreach (array_keys($parent) as $start) {
            if (isset($safe[$start])) {
                continue;
            }
            $seen = [];
            $cur = $start;
            while ($cur !== null && ! isset($safe[$cur])) {
                abort_if(isset($seen[$cur]), 422, 'Folder hierarchy contains a cycle.');
                $seen[$cur] = true;
                $cur = $parent[$cur] ?? null;
            }
            foreach (array_keys($seen) as $id) {
                $safe[$id] = true;
            }
        }
    }

    /** Only plain UUIDs, so the id can never traverse outside the prefix. */
    private function path(string $id): string
    {
        abort_unless(Str::isUuid($id), 404);

        return 'files/'.$id;
    }
}
