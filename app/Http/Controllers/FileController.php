<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileFolder;
use App\Models\StoredFile;
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
            'files' => StoredFile::get()->map(fn (StoredFile $f) => [
                'id' => $f->id,
                'blob' => $f->blob,
                'name' => $f->name,
                'mime' => $f->mime,
                'size' => $f->size,
                'folder' => $f->file_folder_id,
                'tags' => $f->tags ?? [],
                'trashed' => $f->trashed_at?->toIso8601String(),
                'created' => $f->created_at?->toIso8601String(),
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

        DB::transaction(function () use ($data): void {
            $folderIds = [];
            foreach ($data['folders'] ?? [] as $f) {
                FileFolder::updateOrCreate(['id' => $f['id']], ['parent_id' => $f['parent'] ?? null, 'name' => $f['name']]);
                $folderIds[] = $f['id'];
            }
            FileFolder::when($folderIds !== [], fn ($q) => $q->whereNotIn('id', $folderIds))->delete();

            $fileIds = [];
            foreach ($data['files'] ?? [] as $f) {
                StoredFile::updateOrCreate(['id' => $f['id']], [
                    'file_folder_id' => $f['folder'] ?? null,
                    'name' => $f['name'],
                    'mime' => $f['mime'] ?? 'application/octet-stream',
                    'size' => (int) ($f['size'] ?? 0),
                    'blob' => $f['blob'],
                    'tags' => array_values($f['tags'] ?? []),
                    'trashed_at' => ! empty($f['trashed']) ? Carbon::parse($f['trashed']) : null,
                ]);
                $fileIds[] = $f['id'];
            }
            StoredFile::when($fileIds !== [], fn ($q) => $q->whereNotIn('id', $fileIds))->delete();
        });

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
        $this->disk()->delete($this->path($blob));

        return response()->json(['deleted' => true]);
    }

    /** Only plain UUIDs, so the id can never traverse outside the prefix. */
    private function path(string $id): string
    {
        abort_unless(Str::isUuid($id), 404);

        return 'files/'.$id;
    }
}
