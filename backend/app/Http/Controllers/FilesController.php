<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FileType;
use App\Models\AuditLog;
use App\Models\FileActivity;
use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\FileLabel;
use App\Models\FileShare;
use App\Models\FileUploadLink;
use App\Models\FileVersion;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\DiskTempFile;
use App\Support\FileActivityLog;
use App\Support\ImageManagerFactory;
use App\Support\UploadLimits;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Encoders\WebpEncoder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    /** Largest source image the on-demand thumbnailer will decode in-request (40 MiB). */
    private const THUMB_MAX_SRC_BYTES = 40 * 1024 * 1024;

    /** Reject thumbnail sources over this pixel budget (decompression bomb, ~100 MP). */
    private const THUMB_MAX_PIXELS = 100 * 1000 * 1000;

    /** ZIP export caps: abort 413 over either the file-count or cumulative-byte budget. */
    private const ZIP_MAX_FILES = 5000;

    private const ZIP_MAX_BYTES = 2 * 1024 * 1024 * 1024;

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
        $configured = (is_numeric($mb) ? (int) $mb : 2048) * 1024;

        // Never validate above what PHP will actually accept for one request, so
        // an over-generous app limit degrades to a clean 413 instead of a broken
        // upload at the PHP layer. (Files >8 MiB use the chunked path anyway.)
        return UploadLimits::clampKb($configured);
    }

    private function maxVersions(int $uid): int
    {
        return min(200, max(1, (int) UserSetting::for($uid)->file_max_versions));
    }

    /** Effective quota in bytes, or null when unlimited (0/unset). Versions count too. */
    private function quotaBytes(int $uid): ?int
    {
        $mb = config('files.quota_mb');
        $mb = is_numeric($mb) ? (int) $mb : 0;

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

        return view('spa', [
            'folders' => FileFolder::query()->orderBy('name')->get(),
            'files' => FileEntry::query()->with('labels')->orderByDesc('updated_at')->get(),
            'maxVersions' => $this->maxVersions($uid),
            'usage' => $this->usage($uid),
            'labels' => FileLabel::query()->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        return response()->json([
            'folders' => FileFolder::query()->orderBy('name')->get(),
            'files' => FileEntry::query()->with('labels')->orderByDesc('updated_at')->get(),
            'usage' => $this->usage($uid),
            'labels' => FileLabel::query()->orderBy('name')->get(),
        ]);
    }

    public function trashed(): JsonResponse
    {
        return response()->json([
            'files' => FileEntry::onlyTrashed()->with('labels')->orderByDesc('deleted_at')->get(),
            'folders' => FileFolder::onlyTrashed()->orderByDesc('deleted_at')->get(),
        ]);
    }

    // ---- Activity feed ----

    /** Recent Files activity for the current user (owner-scoped, newest first). */
    public function activity(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $rows = FileActivity::query()->orderByDesc('id')->limit(100)->get();

        return response()->json(['activity' => $this->activityView($rows)]);
    }

    /** Activity history for one file (owner-scoped via route binding). */
    public function fileActivity(Request $request, FileEntry $file): JsonResponse
    {
        $this->requireUser($request);
        $rows = FileActivity::query()->where('file_id', $file->id)->orderByDesc('id')->limit(50)->get();

        return response()->json(['activity' => $this->activityView($rows)]);
    }

    /**
     * @param  Collection<int, FileActivity>  $rows
     * @return array<int, array<string,mixed>>
     */
    private function activityView(Collection $rows): array
    {
        $fileIds = $rows->pluck('file_id')->filter()->unique()->all();
        $names = FileEntry::withTrashed()->whereIn('id', $fileIds)->pluck('name', 'id');
        $actorIds = $rows->pluck('actor_id')->filter()->unique()->all();
        $actors = User::query()->whereIn('id', $actorIds)->pluck('name', 'id');

        return $rows->map(fn (FileActivity $a): array => [
            'id' => $a->id,
            'action' => $a->action,
            'file_id' => $a->file_id,
            'file_name' => $a->file_id !== null ? ($names[$a->file_id] ?? null) : null,
            'file_folder_id' => $a->file_folder_id,
            'actor' => $a->actor_id !== null ? ($actors[$a->actor_id] ?? null) : $a->actor_name,
            'meta' => $a->meta,
            'created_at' => $a->created_at->toIso8601String(),
        ])->values()->all();
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
     * Pass $withTrashed to traverse a soft-deleted subtree (restore/force paths),
     * where the default scope would hide the trashed children.
     *
     * @return list<int>
     */
    private function descendantFolderIds(int $rootId, bool $withTrashed = false): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];
        $guard = 0;
        while ($frontier !== [] && $guard++ < 10000) {
            $children = ($withTrashed ? FileFolder::withTrashed() : FileFolder::query())
                ->whereIn('parent_id', $frontier)->pluck('id')->all();
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

        $owner = (int) $result->user_id;
        if (array_key_exists('name', $patch) && $patch['name'] !== $file->name) {
            FileActivityLog::record($owner, 'rename', $result, ['from' => $file->name, 'to' => $result->name]);
        }
        if (array_key_exists('file_folder_id', $patch) && $patch['file_folder_id'] !== $file->file_folder_id) {
            FileActivityLog::record($owner, 'move', $result);
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

        FileActivityLog::record($uid, 'upload', $file);

        return response()->json(['file' => $file], 201);
    }

    /** Duplicate a file (new blob + row) into a target folder (or its own). */
    public function copy(Request $request, FileEntry $file): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'file_folder_id' => ['nullable', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
        ]);

        $src = $file->storage_path;
        // storage_path is always server-assigned under files/; guard defensively.
        if (! is_string($src) || ! str_starts_with($src, 'files/') || ! $this->fs()->exists($src)) {
            abort(404);
        }
        if ($over = $this->overQuota($uid, (int) $file->size)) {
            return $over;
        }

        $path = 'files/'.Str::uuid()->toString();
        $this->fs()->copy($src, $path);
        $folderId = $request->has('file_folder_id')
            ? ($request->filled('file_folder_id') ? $request->integer('file_folder_id') : null)
            : $file->file_folder_id;

        $copy = DB::transaction(fn (): FileEntry => $this->persistFile(
            name: $this->copyName($file->name),
            folderId: $folderId,
            path: $path,
            size: (int) $file->size,
            mime: $file->mime,
            sha: $file->sha256,
        ));

        return response()->json(['file' => $copy], 201);
    }

    /** "report.pdf" → "report (copy).pdf" (keeps the extension). */
    private function copyName(string $name): string
    {
        $dot = strrpos($name, '.');
        if ($dot === false || $dot === 0) {
            return $name.' (copy)';
        }

        return substr($name, 0, $dot).' (copy)'.substr($name, $dot);
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

        FileActivityLog::record($uid, 'version', $fresh, ['name' => $fresh->name, 'version' => $fresh->version]);

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
            // Cap each part so an attacker can't stream unbounded bytes to disk
            // (the part is 8 MiB + a little slack for multipart overhead).
            'file' => ['required', 'file', 'max:'.(int) ceil(self::PART_SIZE / 1024) + 64],
        ]);
        $id = $request->string('id')->value();
        $key = $this->sessionKey($uid, $id);
        /** @var array{name:string, size:int, file_folder_id:int|null, received?:int}|null $session */
        $session = Cache::get($key);
        if ($session === null) {
            abort(404);
        }
        $part = $request->file('file');
        if (! $part instanceof UploadedFile) {
            abort(422);
        }

        // Enforce the quota against bytes ACTUALLY received (not just the client's
        // declared init size): a lying/omitted `size` can't slip past chunkInit's
        // gate and fill the disk before the final chunkComplete quota check.
        $received = (int) ($session['received'] ?? 0) + (int) $part->getSize();
        if ($over = $this->overQuota($uid, $received)) {
            $this->fs()->deleteDirectory($this->tmpDir($uid, $id));
            Cache::forget($key);

            return $over;
        }
        $session['received'] = $received;
        Cache::put($key, $session, now()->addHours(6));

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

        // Sniff the assembled bytes server-side (finfo) rather than trusting the
        // client filename extension — the served Content-Type must not be
        // attacker-controlled. Fall back to the extension map only if unsniffable.
        $mime = $this->sniffMime($tmp->path()) ?? $this->guessMime($name);

        $file = DB::transaction(fn (): FileEntry => $this->persistFile(
            name: $name,
            folderId: $session['file_folder_id'],
            path: $path,
            size: $size,
            mime: $mime,
            sha: $sha,
        ));

        FileActivityLog::record($uid, 'upload', $file);

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

    /** Content-sniffed MIME of an assembled file (finfo), or null when indeterminate. */
    private function sniffMime(string $path): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' && $mime !== 'application/x-empty' ? $mime : null;
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
        FileActivityLog::record((int) $file->user_id, 'trash', $file, ['name' => $file->name]);
        $file->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        $file = FileEntry::onlyTrashed()->findOrFail($id);
        $file->restore();
        FileActivityLog::record((int) $file->user_id, 'restore', $file, ['name' => $file->name]);

        return response()->json(['file' => $file]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $file = FileEntry::onlyTrashed()->findOrFail($id);
        $name = $file->name;
        $owner = (int) $file->user_id;
        DB::transaction(function () use ($file): void {
            foreach ($file->versions()->get() as $v) {
                $this->fs()->delete($v->storage_path);
            }
            $this->fs()->delete($file->storage_path);
            $file->forceDelete(); // cascades file_versions rows via FK
        });
        FileActivityLog::record($owner, 'delete', null, ['name' => $name]);

        return response()->json(['ok' => true]);
    }

    /** Restore a trashed folder and its whole (co-trashed) subtree + files. */
    public function restoreFolder(int $id): JsonResponse
    {
        $folder = FileFolder::onlyTrashed()->findOrFail($id);
        DB::transaction(function () use ($folder): void {
            $ids = $this->descendantFolderIds($folder->id, withTrashed: true);
            FileFolder::withTrashed()->whereIn('id', $ids)->restore();
            FileEntry::onlyTrashed()->whereIn('file_folder_id', $ids)->restore();
        });

        return response()->json(['folder' => $folder->fresh()]);
    }

    /** Permanently delete a trashed folder subtree (folders + files + blobs). */
    public function forceDeleteFolder(int $id): JsonResponse
    {
        $folder = FileFolder::onlyTrashed()->findOrFail($id);
        DB::transaction(function () use ($folder): void {
            $ids = $this->descendantFolderIds($folder->id, withTrashed: true);
            FileEntry::withTrashed()->whereIn('file_folder_id', $ids)->with('versions')
                ->chunkById(100, function ($chunk): void {
                    foreach ($chunk as $file) {
                        foreach ($file->versions as $v) {
                            $this->fs()->delete($v->storage_path);
                        }
                        $this->fs()->delete($file->storage_path);
                        $file->forceDelete();
                    }
                });
            FileFolder::withTrashed()->whereIn('id', $ids)->forceDelete();
        });

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(): JsonResponse
    {
        $n = 0;
        FileEntry::onlyTrashed()->with('versions')->chunkById(100, function ($chunk) use (&$n): void {
            foreach ($chunk as $file) {
                foreach ($file->versions as $v) {
                    $this->fs()->delete($v->storage_path);
                }
                $this->fs()->delete($file->storage_path);
                $file->forceDelete();
                $n++;
            }
        });
        // Purge trashed folders too — emptyTrash previously left them behind.
        FileFolder::onlyTrashed()->forceDelete();

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

    /**
     * Serve a cached square WebP thumbnail for an image file (generated on first
     * request, keyed by file id + version so a content-replace regenerates it).
     * Non-images / undecodable files → 404 (the client falls back to a type icon).
     */
    public function thumb(Request $request, FileEntry $file, ImageManagerFactory $images): StreamedResponse
    {
        $mime = (string) $file->mime;
        abort_unless(str_starts_with($mime, 'image/'), 404);

        // Don't decode arbitrarily large images in-request (memory / decompression
        // bomb): cap the thumbnail SOURCE size — a real photo is well under this,
        // and larger images just fall back to a type icon on the client (404).
        abort_if((int) $file->size > self::THUMB_MAX_SRC_BYTES, 404);

        $thumbPath = 'files/thumb/'.$file->id.'-'.$file->version.'.webp';
        if (! $this->fs()->exists($thumbPath)) {
            $src = (string) $file->storage_path; // server-owned (files/{uuid})
            abort_if($src === '' || ! $this->fs()->exists($src), 404);
            try {
                // Stream the source bytes to a temp file (RAII-unlinked) and decode
                // from a path — the same pattern the avatar re-encoder uses (no
                // full-file (string) read into memory).
                $tmp = DiskTempFile::create('llthumb')->withExtension('img');
                $in = $this->fs()->readStream($src);
                $dst = fopen($tmp->path(), 'wb');
                if (! is_resource($in) || $dst === false) {
                    abort(404);
                }
                stream_copy_to_stream($in, $dst);
                fclose($in);
                fclose($dst);
                // Pixel-budget guard (decompression bomb): reject > ~100 MP from the
                // image header BEFORE decoding, independent of the driver — the GD
                // fallback ignores ImageMagick's policy.xml, so the byte cap alone
                // would let a highly-compressed huge image OOM the request.
                $dims = @getimagesize($tmp->path());
                if (is_array($dims) && (int) $dims[0] * (int) $dims[1] > self::THUMB_MAX_PIXELS) {
                    abort(404);
                }
                $webp = (string) $images->make()->decodePath($tmp->path())
                    ->cover(400, 400)->encode(new WebpEncoder(quality: 78));
                $this->fs()->put($thumbPath, $webp);
            } catch (\Throwable $e) {
                abort(404);
            }
        }

        return $this->fs()->response($thumbPath, 'thumb.webp', [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    /**
     * Stream a ZIP of a selection of files and/or a folder subtree. Names are
     * de-duplicated + path-safe; bytes are read from the files disk. Owner-scoped
     * through the model global scope.
     */
    public function downloadZip(Request $request): BinaryFileResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'ids' => ['nullable', 'array', 'max:'.self::ZIP_MAX_FILES],
            'ids.*' => ['integer'],
            // Owner-scope the folder explicitly (defense-in-depth, matches every
            // other folder param) rather than relying only on downstream scopes.
            'folder_id' => ['nullable', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
        ]);

        $ids = array_values(array_filter($request->array('ids'), 'is_numeric'));
        $query = FileEntry::query();
        if ($request->filled('folder_id')) {
            $folderIds = $this->descendantFolderIds($request->integer('folder_id'));
            $query->whereIn('file_folder_id', $folderIds);
        } elseif ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            abort(422);
        }
        /** @var Collection<int, FileEntry> $files */
        $files = $query->get();
        abort_if($files->isEmpty(), 404);

        // DoS caps (metadata-only, before touching any bytes): the folder subtree
        // branch has no validation count-cap, and a huge selection would otherwise
        // OOM the worker. Abort 413 over either budget.
        abort_if($files->count() > self::ZIP_MAX_FILES, 413);
        abort_if($files->sum(fn (FileEntry $f): int => (int) $f->size) > self::ZIP_MAX_BYTES, 413);

        // RAII temp: any throw before the success-path rename unlinks it (no
        // plaintext-PII residue in the system temp dir). On success we rename it
        // out so it survives until the framework deletes it post-send.
        $tmp = DiskTempFile::create('llzip');
        $zipPath = $tmp->path();
        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            abort(500);
        }

        // Remote-disk per-entry temp files must outlive $zip->close() (ZipArchive
        // reads added files lazily at close), so hold their RAII handles here.
        /** @var list<DiskTempFile> $entryTemps */
        $entryTemps = [];
        $used = [];
        foreach ($files as $f) {
            $path = (string) $f->storage_path;
            if ($path === '' || ! $this->fs()->exists($path)) {
                continue;
            }
            // Path-safe, collision-free entry name.
            $name = $this->safeName((string) $f->name);
            $entry = $name;
            $n = 1;
            while (isset($used[$entry])) {
                $dot = strrpos($name, '.');
                $entry = $dot === false ? $name.' ('.$n.')' : substr($name, 0, $dot).' ('.$n.')'.substr($name, $dot);
                $n++;
            }
            $used[$entry] = true;

            // Stream from disk instead of reading the whole file into a PHP string:
            // local disk → add the file path directly (ZipArchive streams it at
            // close); remote disk → stream the blob to a temp file and add that.
            $local = $this->localDiskPath($path);
            if ($local !== null) {
                $zip->addFile($local, $entry);
            } elseif (($t = $this->streamBlobToTemp($path)) !== null) {
                $entryTemps[] = $t;
                $zip->addFile($t->path(), $entry);
            }
        }
        if (! $zip->close()) {
            abort(500);
        }

        // Detach from RAII: move the finished archive to a sibling path the
        // framework owns (deleted post-send). $tmp's stored path no longer exists
        // → its destructor is a harmless no-op.
        $final = $zipPath.'.zip';
        abort_unless(@rename($zipPath, $final), 500);

        return response()->download($final, 'files-'.now()->format('Ymd-His').'.zip', [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    /** Absolute local filesystem path for a stored blob, or null on a remote disk. */
    private function localDiskPath(string $relative): ?string
    {
        try {
            $abs = $this->fs()->path($relative);
        } catch (\Throwable) {
            return null;
        }

        return is_string($abs) && is_file($abs) ? $abs : null;
    }

    /** Stream a remote-disk blob to a RAII temp file (bounded chunks), or null on failure. */
    private function streamBlobToTemp(string $relative): ?DiskTempFile
    {
        $in = $this->fs()->readStream($relative);
        if (! is_resource($in)) {
            return null;
        }
        $tmp = DiskTempFile::create('llzipe');
        $out = fopen($tmp->path(), 'wb');
        if ($out === false) {
            fclose($in);

            return null;
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        return $tmp;
    }

    /**
     * Storage stats: size-by-category breakdown + suspected duplicates (same
     * sha256 across ≥2 live files). Owner-scoped.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $files = FileEntry::query()->get(['id', 'name', 'mime', 'size', 'sha256', 'file_folder_id']);

        $byType = [];
        foreach ($files as $f) {
            $cat = FileType::fromMime((string) $f->mime)->value;
            $byType[$cat] = ($byType[$cat] ?? 0) + (int) $f->size;
        }

        // Folder id → [name, parent_id] for building each file's full path.
        $folders = FileFolder::query()->get(['id', 'name', 'parent_id'])
            ->keyBy('id');
        $pathOf = function (?int $folderId) use ($folders): string {
            $parts = [];
            $guard = 0;
            while ($folderId !== null && isset($folders[$folderId]) && $guard++ < 100) {
                $node = $folders[$folderId];
                array_unshift($parts, (string) $node->name);
                $folderId = $node->parent_id !== null ? (int) $node->parent_id : null;
            }

            return '/'.implode('/', $parts);
        };

        $groups = [];
        foreach ($files as $f) {
            $h = (string) $f->sha256;
            // Skip empty (0-byte) files: they all share the empty-content hash and
            // would flood the "possible duplicates" list with meaningless matches.
            if ($h === '' || (int) $f->size === 0) {
                continue;
            }
            $dir = $pathOf($f->file_folder_id !== null ? (int) $f->file_folder_id : null);
            $groups[$h][] = [
                'id' => $f->id,
                'name' => $f->name,
                'size' => (int) $f->size,
                'path' => rtrim($dir, '/').'/'.$f->name,
            ];
        }
        $dupes = array_values(array_filter($groups, fn (array $g): bool => count($g) >= 2));

        return response()->json([
            'used' => $this->usedBytes((int) $this->requireUser($request)->id),
            'by_type' => $byType,
            'duplicates' => $dupes,
        ]);
    }

    // ---- Labels (coloured, user-defined taxonomy) ----

    public function labels(): JsonResponse
    {
        return response()->json(['labels' => FileLabel::query()->orderBy('name')->get()]);
    }

    public function storeLabel(Request $request): JsonResponse
    {
        $request->validate($this->labelRules());
        $label = FileLabel::create([
            'name' => $request->string('name')->value(),
            'color' => $request->filled('color') ? $request->string('color')->value() : '#6b7280',
        ]);

        return response()->json(['label' => $label], 201);
    }

    public function updateLabel(Request $request, FileLabel $label): JsonResponse
    {
        $request->validate($this->labelRules());
        $label->update([
            'name' => $request->string('name')->value(),
            'color' => $request->filled('color') ? $request->string('color')->value() : $label->color,
        ]);

        return response()->json(['label' => $label]);
    }

    public function destroyLabel(FileLabel $label): JsonResponse
    {
        $label->delete(); // pivot rows cascade

        return response()->json(['ok' => true]);
    }

    /** Replace a file's label set (owner-scoped ids only). */
    public function setFileLabels(Request $request, FileEntry $file): JsonResponse
    {
        $request->validate([
            'label_ids' => ['present', 'array', 'max:100'],
            'label_ids.*' => ['integer'],
        ]);
        $ids = FileLabel::query()->whereIn('id', $request->array('label_ids'))->pluck('id')->all();
        $file->labels()->sync($ids);
        $file->load('labels');

        return response()->json(['file' => $file]);
    }

    /**
     * @return array<string, mixed>
     */
    private function labelRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
    // ---- Public share links (owner side) ----

    // ---- Public inbound upload links (owner side) ----

    /** List the owner's upload links (with target folder name). */
    public function uploadLinks(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $rows = FileUploadLink::query()->orderByDesc('id')->get();
        $names = FileFolder::query()->whereIn('id', $rows->pluck('file_folder_id')->filter()->all())->pluck('name', 'id');

        return response()->json(['links' => $rows->map(fn (FileUploadLink $l): array => [
            ...$this->uploadLinkView($l),
            'folder_name' => is_string($names[$l->file_folder_id] ?? null) ? $names[$l->file_folder_id] : null,
        ])->all()]);
    }

    public function storeUploadLink(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        // A target folder + an expiry are required; a password is optional.
        $request->validate([
            'file_folder_id' => ['required', 'integer', Rule::exists('file_folders', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'label' => ['nullable', 'string', 'max:200'],
            'expires_at' => ['required', 'date', 'after:now'],
            'password' => ['nullable', 'string', 'max:200'],
        ]);
        $link = new FileUploadLink;
        $link->forceFill([
            'user_id' => $uid,
            'token' => Str::random(48),
            'file_folder_id' => $request->integer('file_folder_id'),
            'label' => $request->filled('label') ? $request->string('label')->value() : null,
            'password_hash' => $request->filled('password') ? Hash::make($request->string('password')->value()) : null,
            'expires_at' => $request->date('expires_at'),
        ])->save();

        return response()->json(['link' => $this->uploadLinkView($link)], 201);
    }

    public function destroyUploadLink(Request $request, int $link): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        FileUploadLink::query()->where('id', $link)->where('user_id', $uid)->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array{id:int,token:string,label:?string,file_folder_id:?int,needs_password:bool,expires_at:?string} */
    private function uploadLinkView(FileUploadLink $l): array
    {
        return [
            'id' => $l->id,
            'token' => $l->token,
            'label' => $l->label,
            'file_folder_id' => $l->file_folder_id,
            'needs_password' => $l->needsPassword(),
            'expires_at' => $l->expires_at?->toIso8601String(),
        ];
    }

    // ---- Public inbound upload links (anonymous side) ----

    /** Public metadata for an upload link (no listing/download). */
    public function uploadLinkMeta(string $token): JsonResponse
    {
        $link = FileUploadLink::query()->withoutGlobalScopes()->where('token', $token)->first();
        if (! $link instanceof FileUploadLink || $link->isExpired()) {
            abort(404);
        }

        return response()->json([
            'label' => $link->label,
            'owner' => (string) ($link->owner->name ?? ''),
            'needs_password' => $link->needsPassword(),
        ]);
    }

    /** Accept one file from an anonymous uploader into the owner's folder. */
    public function uploadLinkStore(Request $request, string $token): JsonResponse
    {
        $link = FileUploadLink::query()->withoutGlobalScopes()->where('token', $token)->first();
        if (! $link instanceof FileUploadLink || $link->isExpired()) {
            abort(404);
        }
        $request->validate([
            'file' => ['required', 'file', 'max:'.$this->maxUploadKb()],
            'password' => ['nullable', 'string', 'max:200'],
        ]);
        // Password-gated link: the uploader must supply the correct password.
        if ($link->needsPassword() && ! Hash::check((string) $request->string('password')->value(), (string) $link->password_hash)) {
            return response()->json(['error' => 'password'], 403);
        }
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }

        $uid = (int) $link->user_id;
        if ($this->overQuota($uid, (int) $upload->getSize())) {
            return response()->json(['error' => 'quota'], 413);
        }

        $sha = $this->hashUpload($upload);
        $path = 'files/'.Str::uuid()->toString();
        $this->fs()->putFileAs('files', $upload, basename($path));
        $name = $upload->getClientOriginalName();
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();

        $created = DB::transaction(function () use ($link, $uid, $name, $path, $mime, $sha): FileEntry {
            $file = new FileEntry;
            $file->forceFill([
                'user_id' => $uid,
                'file_folder_id' => $link->file_folder_id,
                'name' => $name !== '' ? $name : 'file',
                'storage_path' => $path,
                'size' => (int) $this->fs()->size($path),
                'mime' => $mime !== '' ? $mime : null,
                'sha256' => $sha,
            ]);
            $file->save();

            return $file;
        });

        AuditLog::record('files.upload_link_used', null, ['link_id' => $link->id, 'user_id' => $uid]);
        // Anonymous contributor — actor_id stays null; label it with the link name.
        FileActivityLog::record($uid, 'external_upload', $created, ['name' => $created->name], null, $link->label);

        return response()->json(['ok' => true], 201);
    }

    /** List the current user's public share links (with the target's name). */
    public function shares(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $rows = FileShare::query()->where('user_id', $uid)->orderByDesc('id')->get();
        $fileNames = FileEntry::query()->whereIn('id', $rows->pluck('file_id')->filter()->all())->pluck('name', 'id');
        $folderNames = FileFolder::query()->whereIn('id', $rows->pluck('file_folder_id')->filter()->all())->pluck('name', 'id');

        $shares = $rows->map(function (FileShare $s) use ($fileNames, $folderNames): array {
            $name = $s->kind === 'file' ? ($fileNames[$s->file_id] ?? null) : ($folderNames[$s->file_folder_id] ?? null);

            return [...$this->shareView($s), 'name' => is_string($name) ? $name : ''];
        })->all();

        return response()->json(['shares' => $shares]);
    }

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

        $sharedFile = $kind === 'file' ? FileEntry::query()->find($request->integer('file_id')) : null;
        FileActivityLog::record($uid, 'share', $sharedFile, ['kind' => $kind], $kind === 'folder' ? $request->integer('file_folder_id') : null);

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
