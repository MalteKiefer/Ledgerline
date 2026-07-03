<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileFolder;
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

    /** The whole tree as the client's manifest shape. */
    public function data(): JsonResponse
    {
        return response()->json([
            'v' => 1,
            'folders' => FileFolder::get(['id', 'parent_id', 'name'])
                ->map(fn (FileFolder $f) => ['id' => $f->id, 'name' => $f->name, 'parent' => $f->parent_id])
                ->all(),
            'files' => StoredFile::withTrashed()->get()->map(fn (StoredFile $f) => [
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

        $removedBlobs = DB::transaction(function () use ($folders, $files): array {
            $folderIds = [];
            foreach ($folders as $f) {
                FileFolder::updateOrCreate(['id' => $f['id']], ['parent_id' => $f['parent'] ?? null, 'name' => $f['name']]);
                $folderIds[] = $f['id'];
            }
            FileFolder::when($folderIds !== [], fn ($q) => $q->whereNotIn('id', $folderIds))->delete();

            $fileIds = [];
            foreach ($files as $f) {
                // withTrashed: the manifest keeps trashed files, so a matching
                // row may be soft-deleted; find it (or build a new one) and let
                // the manifest's `trashed` timestamp drive deleted_at directly.
                $file = StoredFile::withTrashed()->firstOrNew(['id' => $f['id']]);
                $file->fill([
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
            }

            // Reclaim the bytes of rows the manifest dropped in the same write
            // (trashed rows included), so a deleted file never leaves an
            // orphaned blob on the disk.
            $removed = StoredFile::withTrashed()->when($fileIds !== [], fn ($q) => $q->whereNotIn('id', $fileIds))
                ->pluck('blob')->all();
            StoredFile::withTrashed()->when($fileIds !== [], fn ($q) => $q->whereNotIn('id', $fileIds))->forceDelete();

            return $removed;
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

        $id = (string) Str::uuid();
        $this->disk()->putFileAs('files', $request->file('file'), $id);

        return response()->json(['id' => $id], 201);
    }

    /** Import an uploaded file straight into Files as a row (used by mail "save to Files"). */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.((int) config('files.max_upload_mb', 2048) * 1024)],
            'folder_id' => ['nullable', 'uuid', 'exists:file_folders,id'],
        ]);

        $file = $request->file('file');
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
        abort_unless($this->disk()->exists($path), 404);

        return $this->disk()->response($path, 'file', [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'attachment');
    }

    /** Delete a stored file's bytes (after its row was removed via sync). */
    public function deleteBlob(string $blob): JsonResponse
    {
        $path = $this->path($blob);

        // Never destroy bytes still owned by a live row: a racing or forged
        // delete could otherwise leave a dangling file that 404s on open.
        abort_if(StoredFile::where('blob', $blob)->exists(), 409);

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
