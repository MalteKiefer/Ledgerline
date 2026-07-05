<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\BuildExport;
use App\Models\Export;
use App\Models\FileFolder;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Support\Tags;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        return Storage::disk(config('files.disk'));
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
            'folders' => FileFolder::withoutGlobalScopes()->where('user_id', $uid)->get(['id', 'parent_id', 'name'])
                ->map(fn (FileFolder $f) => ['id' => $f->id, 'name' => $f->name, 'parent' => $f->parent_id])
                ->all(),
            'files' => StoredFile::withoutGlobalScopes()->where('user_id', $uid)->withTrashed()->get()->map(fn (StoredFile $f) => [
                'id' => $f->id,
                'blob' => $f->blob,
                'name' => $f->name,
                'mime' => $f->mime,
                'size' => $f->size,
                'folder' => $f->file_folder_id,
                'trashed' => $f->deleted_at?->toIso8601String(),
                'created' => $f->created_at?->toIso8601String(),
                'tags' => $f->tags ?? [],
            ])->all(),
        ]);
    }

    /** Replace the tree from the client's manifest (upsert + delete missing). */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folders' => ['array'],
            'folders.*.id' => ['required', 'uuid'],
            'folders.*.name' => ['required', 'string', 'max:255'],
            'folders.*.parent' => ['nullable', 'uuid'],
            'files' => ['array'],
            'files.*.id' => ['required', 'uuid'],
            'files.*.blob' => ['required', 'uuid'],
            'files.*.name' => ['required', 'string', 'max:255'],
            'files.*.mime' => ['nullable', 'string', 'max:255'],
            'files.*.size' => ['nullable', 'integer', 'min:0'],
            'files.*.folder' => ['nullable', 'uuid'],
            'files.*.tags' => ['array'],
            'files.*.trashed' => ['nullable'],
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
        $owned = fn () => StoredFile::withoutGlobalScopes()->where('user_id', $uid);
        $ownedFolders = fn () => FileFolder::withoutGlobalScopes()->where('user_id', $uid);

        $removedBlobs = DB::transaction(function () use ($folders, $files, $uid, $owned, $ownedFolders): array {
            $folderIds = [];
            foreach ($folders as $f) {
                $ownedFolders()->updateOrCreate(['id' => $f['id']], ['user_id' => $uid, 'parent_id' => $f['parent'] ?? null, 'name' => $f['name']]);
                $folderIds[] = $f['id'];
            }
            $ownedFolders()->when($folderIds !== [], fn ($q) => $q->whereNotIn('id', $folderIds))->delete();

            $fileIds = [];
            $prunedBlobs = [];
            foreach ($files as $f) {
                // withTrashed: the manifest keeps trashed files, so a matching
                // row may be soft-deleted; find it (or build a new one) and let
                // the manifest's `trashed` timestamp drive deleted_at directly.
                $file = $owned()->withTrashed()->firstOrNew(['id' => $f['id']]);
                $oldBlob = $file->exists ? $file->blob : null;
                $oldMeta = ['name' => $file->name, 'mime' => $file->mime, 'size' => (int) $file->size];
                $file->fill([
                    'user_id' => $uid,
                    'file_folder_id' => $f['folder'] ?? null,
                    'name' => $f['name'],
                    'mime' => $f['mime'] ?? 'application/octet-stream',
                    'size' => (int) ($f['size'] ?? 0),
                    'blob' => $f['blob'],
                    'tags' => Tags::normalize($f['tags'] ?? null),
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
                    $prunedBlobs = array_merge($prunedBlobs, $this->capVersions($file->id));
                }
            }

            // Reclaim the bytes of the user's own rows the manifest dropped —
            // both the file blobs and any version blobs they kept.
            $droppedFiles = $owned()->withTrashed()->when($fileIds !== [], fn ($q) => $q->whereNotIn('id', $fileIds));
            $droppedIds = $droppedFiles->pluck('id')->all();
            $removed = $droppedFiles->pluck('blob')->all();
            if ($droppedIds !== []) {
                $removed = array_merge($removed, FileVersion::whereIn('file_id', $droppedIds)->pluck('blob')->all());
                FileVersion::whereIn('file_id', $droppedIds)->delete();
            }
            $owned()->withTrashed()->when($fileIds !== [], fn ($q) => $q->whereNotIn('id', $fileIds))->forceDelete();

            return array_merge($removed, $prunedBlobs);
        });

        foreach ($removedBlobs as $blob) {
            if (is_string($blob) && Str::isUuid($blob)) {
                $this->disk()->delete('files/'.$blob);
            }
        }

        return response()->json(['ok' => true]);
    }

    /** Store one uploaded file and return its blob id. */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.((int) config('files.max_upload_mb', 2048) * 1024)],
        ]);

        $incoming = (int) $request->file('file')->getSize();
        abort_if($this->quotaExceeded($request->user()->id, $incoming), 413, __('files.quota_exceeded'));

        $id = (string) Str::uuid();
        $this->disk()->putFileAs('files', $request->file('file'), $id);

        return response()->json(['id' => $id], 201);
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

    /** Keep only the newest N versions of a file; return the pruned blobs. */
    private function capVersions(string $fileId): array
    {
        $keep = max(1, (int) config('files.max_versions', 20));
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
                'id' => $v->id, 'name' => $v->name, 'mime' => $v->mime,
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

        return $this->disk()->response($path, $version->name, [
            'Content-Type' => $version->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Import an uploaded file straight into Files as a row (used by mail "save to Files"). */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.((int) config('files.max_upload_mb', 2048) * 1024)],
            'folder_id' => ['nullable', 'uuid', 'exists:file_folders,id'],
        ]);

        $file = $request->file('file');
        abort_if($this->quotaExceeded($request->user()->id, (int) $file->getSize()), 413, __('files.quota_exceeded'));
        $blob = (string) Str::uuid();
        $this->disk()->putFileAs('files', $file, $blob);

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
        ]);

        // Keep only ids the current user actually OWNS (not merely shared with
        // them) so an export can never exfiltrate another user's file bytes.
        $uid = $request->user()->id;
        $fileIds = StoredFile::withoutGlobalScopes()->where('user_id', $uid)->withTrashed()
            ->whereIn('id', array_values($validated['file_ids'] ?? []))->pluck('id')->all();
        $folderIds = FileFolder::withoutGlobalScopes()->where('user_id', $uid)
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
            'item_count' => $count,
            'payload' => ['file_ids' => $fileIds, 'folder_ids' => $folderIds],
        ]);

        BuildExport::dispatch($export->id);

        return response()->json(['queued' => true, 'export_id' => $export->id], 202);
    }

    /** Delete a stored file's bytes (after its row was removed via sync). */
    public function deleteBlob(string $blob): JsonResponse
    {
        $path = $this->path($blob);

        // Never destroy bytes still owned by a live row — of ANY user, so one
        // user can't delete another's blob by guessing its UUID.
        abort_if(StoredFile::withoutGlobalScopes()->withTrashed()->where('blob', $blob)->exists(), 409);

        $this->disk()->delete($path);

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
        $known = array_fill_keys(array_keys($parent), true);

        foreach ($folders as $f) {
            $p = $f['parent'] ?? null;
            abort_if($p !== null && ! isset($known[$p]), 422, 'Folder parent does not resolve within the manifest.');
        }
        foreach ($files as $f) {
            $folder = $f['folder'] ?? null;
            abort_if($folder !== null && ! isset($known[$folder]), 422, 'File folder does not resolve within the manifest.');
        }

        // Walk each folder to the root; a chain longer than the folder count
        // means a cycle.
        $count = count($parent);
        foreach (array_keys($parent) as $start) {
            $steps = 0;
            for ($cur = $parent[$start]; $cur !== null; $cur = $parent[$cur] ?? null) {
                abort_if(++$steps > $count, 422, 'Folder hierarchy contains a cycle.');
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
