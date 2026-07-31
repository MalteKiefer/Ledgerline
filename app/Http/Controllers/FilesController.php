<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\FileShare;
use App\Models\FileVersion;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\DiskTempFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plaintext-relational Files core (pivot). Personal files + folders as
 * owner-scoped rows; bytes live plaintext on the file disk. Whole + chunked
 * upload, download, rename/move/trash/restore/force, version history and a
 * per-user storage quota. No public shares / cross-user shared folders here —
 * that is a later sub-phase.
 *
 * Every write is a single-row INSERT/UPDATE in a transaction — no whole-blob
 * re-serialize, so the opaque last-writer-wins loss class cannot occur.
 */
class FilesController extends Controller
{
    /** Chunk size handed to the client for chunked uploads (8 MiB). */
    private const PART_SIZE = 8 * 1024 * 1024;

    // ---- Storage helpers ----

    private function disk(): string
    {
        $d = config('files.disk');

        return is_string($d) ? $d : 'files';
    }

    private function fs(): Filesystem
    {
        return Storage::disk($this->disk());
    }

    private function maxUploadKb(): int
    {
        $mb = config('files.max_upload_mb', 2048);

        return (is_numeric($mb) ? (int) $mb : 2048) * 1024;
    }

    private function maxVersions(int $uid): int
    {
        return min(200, max(1, (int) UserSetting::for($uid)->file_max_versions));
    }

    /** Effective quota in bytes, or null when unlimited (0 MB). Versions count too. */
    private function quotaBytes(int $uid): ?int
    {
        $mb = User::query()->findOrFail($uid)->effectiveFilesQuotaMb();

        return $mb <= 0 ? null : $mb * 1024 * 1024;
    }

    /** Bytes the user currently occupies: live + trashed files plus every version blob. */
    private function usedBytes(int $uid): int
    {
        $fileIds = FileEntry::withTrashed()->pluck('id');
        $files = (int) FileEntry::withTrashed()->sum('size');
        $versions = (int) FileVersion::query()->whereIn('file_id', $fileIds)->sum('size');

        return $files + $versions;
    }

    /**
     * The current usage snapshot for API/page payloads.
     *
     * @return array{used: int, quota: int|null}
     */
    private function usage(int $uid): array
    {
        return ['used' => $this->usedBytes($uid), 'quota' => $this->quotaBytes($uid)];
    }

    /** Filesystem-safe download filename (strips path separators + control chars). */
    private function safeName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'file' : $clean;
    }

    // ---- Page / listings ----

    public function page(Request $request): View
    {
        $uid = (int) $this->requireUser($request)->id;

        return view('files.index', [
            'folders' => FileFolder::query()->orderBy('name')->get(),
            'files' => FileEntry::query()->orderByDesc('updated_at')->get(),
            'maxVersions' => $this->maxVersions($uid),
            'usage' => $this->usage($uid),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        return response()->json([
            'folders' => FileFolder::query()->orderBy('name')->get(),
            'files' => FileEntry::query()->orderByDesc('updated_at')->get(),
            'usage' => $this->usage($uid),
        ]);
    }

    public function trashed(): JsonResponse
    {
        return response()->json([
            'files' => FileEntry::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'folders' => FileFolder::onlyTrashed()->orderByDesc('deleted_at')->get(),
        ]);
    }

    // ---- Folders ----

    public function folders(): JsonResponse
    {
        return response()->json(['folders' => FileFolder::query()->orderBy('name')->get()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function folderRules(Request $request): array
    {
        $uid = (int) $this->requireUser($request)->id;

        return [
            'name' => ['required', 'string', 'max:500'],
            'parent_id' => ['nullable', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
        ];
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $request->validate($this->folderRules($request));
        $folder = DB::transaction(fn (): FileFolder => FileFolder::create([
            'name' => $request->string('name')->value(),
            'parent_id' => $request->filled('parent_id') ? $request->integer('parent_id') : null,
        ]));

        return response()->json(['folder' => $folder], 201);
    }

    public function renameFolder(Request $request, FileFolder $folder): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:500']]);
        $folder->update(['name' => $request->string('name')->value()]);

        return response()->json(['folder' => $folder]);
    }

    public function moveFolder(Request $request, FileFolder $folder): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['parent_id' => ['nullable', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')]]);
        $parentId = $request->filled('parent_id') ? $request->integer('parent_id') : null;
        if ($parentId !== null && $this->wouldCycle($folder->id, $parentId)) {
            return response()->json(['error' => 'cycle'], 422);
        }
        $folder->update(['parent_id' => $parentId]);

        return response()->json(['folder' => $folder]);
    }

    private function wouldCycle(int $folderId, int $newParentId): bool
    {
        $cursor = $newParentId;
        $guard = 0;
        while ($cursor !== null && $guard++ < 1000) {
            if ($cursor === $folderId) {
                return true;
            }
            $parent = FileFolder::query()->whereKey($cursor)->value('parent_id');
            $cursor = is_numeric($parent) ? (int) $parent : null;
        }

        return false;
    }

    /** Soft-delete a folder and its whole subtree (folders + files) in one transaction. */
    public function destroyFolder(FileFolder $folder): JsonResponse
    {
        DB::transaction(function () use ($folder): void {
            $ids = $this->descendantFolderIds($folder->id);
            FileEntry::query()->whereIn('file_folder_id', $ids)->delete();
            FileFolder::query()->whereIn('id', $ids)->delete();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * The folder plus every descendant folder id (owner-scoped through the model).
     *
     * @return list<int>
     */
    private function descendantFolderIds(int $rootId): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];
        $guard = 0;
        while ($frontier !== [] && $guard++ < 10000) {
            $children = FileFolder::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $frontier = [];
            foreach ($children as $child) {
                if (! is_numeric($child)) {
                    continue;
                }
                $cid = (int) $child;
                $ids[] = $cid;
                $frontier[] = $cid;
            }
        }

        return array_values(array_unique($ids));
    }

    // ---- File metadata mutations ----

    /**
     * @return array<string, mixed>
     */
    private function fileRules(Request $request): array
    {
        $uid = (int) $this->requireUser($request)->id;

        return [
            'name' => ['sometimes', 'string', 'max:500'],
            'file_folder_id' => ['nullable', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'tags' => ['nullable', 'array', 'max:100'],
            'tags.*' => ['string', 'max:100'],
            'note' => ['nullable', 'string', 'max:100000'],
            'favorite' => ['sometimes', 'boolean'],
        ];
    }

    public function update(Request $request, FileEntry $file): JsonResponse
    {
        $request->validate($this->fileRules($request) + ['version' => ['sometimes', 'integer', 'min:0']]);
        $expected = $request->has('version') ? $request->integer('version') : null;

        /** @var list<string> $tags */
        $tags = array_values(array_filter($request->array('tags'), static fn ($t): bool => is_string($t)));

        $patch = [];
        if ($request->has('name')) {
            $patch['name'] = $request->string('name')->value();
        }
        if ($request->has('file_folder_id')) {
            $patch['file_folder_id'] = $request->filled('file_folder_id') ? $request->integer('file_folder_id') : null;
        }
        if ($request->has('tags')) {
            $patch['tags'] = $tags !== [] ? $tags : null;
        }
        if ($request->has('note')) {
            $patch['note'] = $request->filled('note') ? $request->string('note')->value() : null;
        }
        if ($request->has('favorite')) {
            $patch['favorite'] = $request->boolean('favorite');
        }

        $result = DB::transaction(function () use ($file, $patch, $expected): FileEntry|bool|null {
            $fresh = FileEntry::query()->lockForUpdate()->find($file->id);
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false;
            }
            $fresh->fill($patch);
            $fresh->version = $fresh->version + 1;
            $fresh->save();

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = FileEntry::query()->find($file->id);

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['file' => $result]);
    }

    public function toggle(Request $request, FileEntry $file): JsonResponse
    {
        $request->validate(['field' => [Rule::in(['favorite'])], 'value' => ['required', 'boolean']]);
        $file->update(['favorite' => $request->boolean('value')]);

        return response()->json(['file' => $file]);
    }

    // ---- Whole upload ----

    public function upload(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'file' => ['required', 'file', 'max:'.$this->maxUploadKb()],
            'file_folder_id' => ['nullable', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'name' => ['nullable', 'string', 'max:500'],
        ]);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }

        $incoming = (int) $upload->getSize();
        if ($over = $this->overQuota($uid, $incoming)) {
            return $over;
        }

        $sha = $this->hashUpload($upload);
        $path = 'files/'.Str::uuid()->toString();
        $this->fs()->putFileAs('files', $upload, basename($path));

        $name = $request->filled('name') ? $request->string('name')->value() : $upload->getClientOriginalName();
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();
        $folderId = $request->filled('file_folder_id') ? $request->integer('file_folder_id') : null;

        $file = DB::transaction(fn (): FileEntry => $this->persistFile(
            name: $name !== '' ? $name : 'file',
            folderId: $folderId,
            path: $path,
            size: (int) $this->fs()->size($path),
            mime: $mime !== '' ? $mime : null,
            sha: $sha,
        ));

        return response()->json(['file' => $file], 201);
    }

    /** Replace a file's bytes with a new revision (pushes current into history). */
    public function replaceContent(Request $request, FileEntry $file): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['file' => ['required', 'file', 'max:'.$this->maxUploadKb()]]);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }

        $incoming = (int) $upload->getSize();
        if ($over = $this->overQuota($uid, $incoming)) {
            return $over;
        }

        $sha = $this->hashUpload($upload);
        $path = 'files/'.Str::uuid()->toString();
        $this->fs()->putFileAs('files', $upload, basename($path));
        $size = (int) $this->fs()->size($path);
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();

        $fresh = DB::transaction(function () use ($file, $path, $size, $mime, $sha, $uid): FileEntry {
            $current = FileEntry::query()->lockForUpdate()->findOrFail($file->id);
            $this->archiveVersion($current);

            $current->forceFill([
                'storage_path' => $path,
                'size' => $size,
                'mime' => $mime !== '' ? $mime : null,
                'sha256' => $sha,
            ]);
            $current->version = $current->version + 1;
            $current->save();
            $this->pruneVersions($current, $uid);

            return $current;
        });

        return response()->json(['file' => $fresh]);
    }

    /** Delete version rows (and their blobs) beyond the per-user cap, oldest first. */
    private function pruneVersions(FileEntry $file, int $uid): void
    {
        $cap = $this->maxVersions($uid);
        $stale = $file->versions()->orderByDesc('id')->skip($cap)->take(1000)->get();
        foreach ($stale as $v) {
            $this->fs()->delete($v->storage_path);
            $v->delete();
        }
    }

    // ---- Chunked upload ----

    public function chunkInit(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'name' => ['required', 'string', 'max:500'],
            'size' => ['required', 'integer', 'min:0'],
            'file_folder_id' => ['nullable', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
        ]);

        if ($over = $this->overQuota($uid, $request->integer('size'))) {
            return $over;
        }

        $id = Str::uuid()->toString();
        Cache::put($this->sessionKey($uid, $id), [
            'name' => $request->string('name')->value(),
            'size' => $request->integer('size'),
            'file_folder_id' => $request->filled('file_folder_id') ? $request->integer('file_folder_id') : null,
        ], now()->addHours(6));

        return response()->json(['id' => $id, 'partSize' => self::PART_SIZE], 201);
    }

    public function chunkPart(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'id' => ['required', 'string'],
            'index' => ['required', 'integer', 'min:0'],
            'file' => ['required', 'file'],
        ]);
        $id = $request->string('id')->value();
        if (Cache::get($this->sessionKey($uid, $id)) === null) {
            abort(404);
        }
        $part = $request->file('file');
        if (! $part instanceof UploadedFile) {
            abort(422);
        }
        $part->storeAs($this->tmpDir($uid, $id), (string) $request->integer('index'), ['disk' => $this->disk()]);

        return response()->json(['ok' => true, 'index' => $request->integer('index')]);
    }

    public function chunkComplete(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['id' => ['required', 'string']]);
        $id = $request->string('id')->value();
        /** @var array{name:string, size:int, file_folder_id:int|null}|null $session */
        $session = Cache::get($this->sessionKey($uid, $id));
        if ($session === null) {
            abort(404);
        }

        $tmpDir = $this->tmpDir($uid, $id);
        $parts = $this->fs()->files($tmpDir);
        // Assemble parts in numeric index order.
        usort($parts, fn (string $a, string $b): int => (int) basename($a) <=> (int) basename($b));

        $tmp = DiskTempFile::create('llfup');
        $out = fopen($tmp->path(), 'wb');
        if ($out === false) {
            abort(500);
        }
        $ctx = hash_init('sha256');
        $size = 0;
        foreach ($parts as $partPath) {
            $in = $this->fs()->readStream($partPath);
            if (! is_resource($in)) {
                continue;
            }
            while (! feof($in)) {
                $buf = fread($in, 1 << 20);
                if ($buf === false) {
                    break;
                }
                fwrite($out, $buf);
                hash_update($ctx, $buf);
                $size += strlen($buf);
            }
            fclose($in);
        }
        fclose($out);
        $sha = hash_final($ctx);

        if ($over = $this->overQuota($uid, $size)) {
            $this->fs()->deleteDirectory($tmpDir);
            Cache::forget($this->sessionKey($uid, $id));

            return $over;
        }

        $path = 'files/'.Str::uuid()->toString();
        $stream = fopen($tmp->path(), 'rb');
        if ($stream === false) {
            abort(500);
        }
        $this->fs()->writeStream($path, $stream);
        fclose($stream);

        $this->fs()->deleteDirectory($tmpDir);
        Cache::forget($this->sessionKey($uid, $id));

        $name = $session['name'] !== '' ? $session['name'] : 'file';

        $file = DB::transaction(fn (): FileEntry => $this->persistFile(
            name: $name,
            folderId: $session['file_folder_id'],
            path: $path,
            size: $size,
            mime: $this->guessMime($name),
            sha: $sha,
        ));

        return response()->json(['file' => $file], 201);
    }

    public function chunkAbort(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['id' => ['required', 'string']]);
        $id = $request->string('id')->value();
        $this->fs()->deleteDirectory($this->tmpDir($uid, $id));
        Cache::forget($this->sessionKey($uid, $id));

        return response()->json(['ok' => true]);
    }

    private function sessionKey(int $uid, string $id): string
    {
        return 'files.chunk.'.$uid.'.'.$id;
    }

    private function tmpDir(int $uid, string $id): string
    {
        return 'files-tmp/'.$uid.'/'.preg_replace('/[^A-Za-z0-9\-]/', '', $id);
    }

    private function guessMime(string $name): ?string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $map = [
            'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain', 'zip' => 'application/zip',
            'mp4' => 'video/mp4', 'json' => 'application/json',
        ];

        return $map[$ext] ?? null;
    }

    // ---- Download ----

    public function raw(Request $request, FileEntry $file): StreamedResponse
    {
        if (! $this->fs()->exists($file->storage_path)) {
            abort(404);
        }
        $download = $request->boolean('download');
        $filename = $this->safeName($file->name);
        $mime = $file->mime ?? 'application/octet-stream';

        return $this->fs()->response($file->storage_path, $filename, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $download ? 'attachment' : 'inline');
    }

    // ---- Trash lifecycle ----

    public function destroy(FileEntry $file): JsonResponse
    {
        $file->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        $file = FileEntry::onlyTrashed()->findOrFail($id);
        $file->restore();

        return response()->json(['file' => $file]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $file = FileEntry::onlyTrashed()->findOrFail($id);
        DB::transaction(function () use ($file): void {
            foreach ($file->versions()->get() as $v) {
                $this->fs()->delete($v->storage_path);
            }
            $this->fs()->delete($file->storage_path);
            $file->forceDelete(); // cascades file_versions rows via FK
        });

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(): JsonResponse
    {
        $n = 0;
        FileEntry::onlyTrashed()->chunkById(100, function ($chunk) use (&$n): void {
            foreach ($chunk as $file) {
                foreach ($file->versions()->get() as $v) {
                    $this->fs()->delete($v->storage_path);
                }
                $this->fs()->delete($file->storage_path);
                $file->forceDelete();
                $n++;
            }
        });

        return response()->json(['deleted' => $n]);
    }

    // ---- Version history ----

    public function versions(FileEntry $file): JsonResponse
    {
        return response()->json(['versions' => $file->versions()->orderByDesc('id')->get()]);
    }

    public function versionRaw(Request $request, FileEntry $file, int $version): StreamedResponse
    {
        $v = $file->versions()->findOrFail($version);
        if (! $this->fs()->exists($v->storage_path)) {
            abort(404);
        }

        return $this->fs()->response($v->storage_path, $this->safeName($file->name), [
            'Content-Type' => $v->mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    /** Promote a prior revision to current (pushing the current bytes into history). */
    public function restoreVersion(Request $request, FileEntry $file, int $version): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        $fresh = DB::transaction(function () use ($file, $version, $uid): FileEntry {
            $current = FileEntry::query()->lockForUpdate()->findOrFail($file->id);
            $target = $current->versions()->lockForUpdate()->findOrFail($version);

            // Archive the current bytes as a version.
            $this->archiveVersion($current);

            // Promote the target's bytes to current; drop its (now-current) version row.
            $current->forceFill([
                'storage_path' => $target->storage_path,
                'size' => $target->size,
                'mime' => $target->mime,
                'sha256' => $target->sha256,
            ]);
            $current->version = $current->version + 1;
            $current->save();
            $target->delete();
            $this->pruneVersions($current, $uid);

            return $current;
        });

        return response()->json(['file' => $fresh]);
    }

    // ---- Shared helpers ----

    /** 413 quota response if used+incoming exceeds the cap, else null. */
    private function overQuota(int $uid, int $incoming): ?JsonResponse
    {
        $quota = $this->quotaBytes($uid);
        if ($quota !== null && $this->usedBytes($uid) + $incoming > $quota) {
            return response()->json(['error' => 'quota'], 413);
        }

        return null;
    }

    /** Persist a new file row with server-set byte metadata (never mass-assigned). */
    private function persistFile(string $name, ?int $folderId, string $path, int $size, ?string $mime, ?string $sha): FileEntry
    {
        $file = new FileEntry;
        $file->fill(['name' => $name, 'file_folder_id' => $folderId]);
        $file->forceFill([
            'storage_path' => $path,
            'size' => $size,
            'mime' => $mime,
            'sha256' => $sha,
        ]);
        $file->save();

        return $file;
    }

    /** Snapshot a file's current bytes into its version history. */
    private function archiveVersion(FileEntry $file): void
    {
        $v = new FileVersion;
        $v->forceFill([
            'file_id' => $file->id,
            'storage_path' => $file->storage_path,
            'size' => $file->size,
            'mime' => $file->mime,
            'sha256' => $file->sha256,
            'created_at' => Carbon::now(),
        ]);
        $v->save();
    }

    private function hashUpload(UploadedFile $upload): ?string
    {
        $real = $upload->getRealPath();
        if (! is_string($real)) {
            return null;
        }
        $hash = hash_file('sha256', $real);

        return $hash !== false ? $hash : null;
    }

    // ---- Public share links (owner side) ----

    public function storeShare(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'kind' => ['required', Rule::in(['file', 'folder'])],
            'file_id' => ['required_if:kind,file', 'nullable', 'integer', Rule::exists('files', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'file_folder_id' => ['required_if:kind,folder', 'nullable', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'password' => ['nullable', 'string', 'max:200'],
            'allow_download' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $kind = $request->string('kind')->value();
        $share = DB::transaction(function () use ($request, $uid, $kind): FileShare {
            $share = new FileShare;
            $share->forceFill([
                'user_id' => $uid,
                'token' => Str::random(48),
                'kind' => $kind,
                'file_id' => $kind === 'file' ? $request->integer('file_id') : null,
                'file_folder_id' => $kind === 'folder' ? $request->integer('file_folder_id') : null,
                'password_hash' => $request->filled('password') ? Hash::make($request->string('password')->value()) : null,
                'allow_download' => $request->has('allow_download') ? $request->boolean('allow_download') : true,
                'expires_at' => $request->filled('expires_at') ? $request->date('expires_at') : null,
                'version' => 0,
            ]);
            $share->save();

            return $share;
        });

        return response()->json(['share' => $this->shareView($share)], 201);
    }

    public function updateShare(Request $request, int $share): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'password' => ['nullable', 'string', 'max:200'],
            'remove_password' => ['sometimes', 'boolean'],
            'allow_download' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);
        $expected = $request->has('version') ? $request->integer('version') : null;

        $result = DB::transaction(function () use ($request, $share, $uid, $expected): FileShare|bool|null {
            $fresh = FileShare::query()->where('id', $share)->where('user_id', $uid)->lockForUpdate()->first();
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false;
            }
            if ($request->boolean('remove_password')) {
                $fresh->password_hash = null;
            } elseif ($request->filled('password')) {
                $fresh->password_hash = Hash::make($request->string('password')->value());
            }
            if ($request->has('allow_download')) {
                $fresh->allow_download = $request->boolean('allow_download');
            }
            if ($request->has('expires_at')) {
                $fresh->expires_at = $request->filled('expires_at') ? $request->date('expires_at') : null;
            }
            $fresh->version = $fresh->version + 1;
            $fresh->save();

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = FileShare::query()->where('id', $share)->where('user_id', $uid)->first();

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['share' => $this->shareView($result)]);
    }

    public function destroyShare(Request $request, int $share): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        FileShare::query()->where('id', $share)->where('user_id', $uid)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * The owner-visible share representation (never leaks the password hash).
     *
     * @return array<string, mixed>
     */
    private function shareView(FileShare $share): array
    {
        return [
            'id' => $share->id,
            'token' => $share->token,
            'kind' => $share->kind,
            'file_id' => $share->file_id,
            'file_folder_id' => $share->file_folder_id,
            'needs_password' => $share->needsPassword(),
            'allow_download' => $share->allow_download,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'version' => $share->version,
        ];
    }
}
