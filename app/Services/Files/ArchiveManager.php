<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\FileFolder;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Support\ArchiveName;
use App\Support\BlobStore;
use App\Support\DiskTempFile;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Create and extract zip archives inside the file browser. Owner-scoped and
 * bounded (entry count + uncompressed size + per-user quota) to blunt zip bombs,
 * with a strict zip-slip guard on every extracted entry name.
 */
class ArchiveManager
{
    private function disk()
    {
        return BlobStore::disk();
    }

    private int $maxEntries;

    private int $maxBytes;

    public function __construct()
    {
        $this->maxEntries = (int) config('files.archive_max_entries', 5000);
        $this->maxBytes = (int) config('files.archive_max_mb', 2048) * 1024 * 1024;
    }

    /**
     * Build a zip from the given owned files/folders and store it as a new file
     * in $folderId (null = root). Returns the created StoredFile.
     *
     * @param  array<int, array{kind:string, id:string}>  $refs
     */
    public function create(int $userId, array $refs, ?string $folderId, ?string $name): StoredFile
    {
        // Flatten the selection into [zip-relative-path => StoredFile].
        $entries = [];
        foreach ($refs as $ref) {
            if (($ref['kind'] ?? '') === 'folder') {
                $folder = FileFolder::withoutGlobalScopes()->where('user_id', $userId)->find($ref['id']);
                if ($folder) {
                    $this->collectFolder($userId, $folder, $folder->name, $entries);
                }
            } else {
                $file = StoredFile::withoutGlobalScopes()->whereNull('deleted_at')
                    ->where('user_id', $userId)->find($ref['id']);
                if ($file) {
                    $entries[$this->dedupePath($file->name, $entries)] = $file;
                }
            }
        }

        abort_if($entries === [], 422, __('files.archive_empty'));
        abort_if(count($entries) > $this->maxEntries, 422, __('files.archive_too_many'));
        $total = array_sum(array_map(fn (StoredFile $f) => (int) $f->size, $entries));
        abort_if($total > $this->maxBytes, 413, __('files.archive_too_large'));
        $this->assertQuota($userId, $total); // zip is <= uncompressed total

        $tmp = tempnam(sys_get_temp_dir(), 'llzip');
        $pulled = [];
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new RuntimeException('Unable to create the archive.');
        }
        try {
            foreach ($entries as $path => $file) {
                $local = DiskTempFile::pull($this->disk(), 'files/'.$file->blob, 'llzsrc');
                $pulled[] = $local;
                $zip->addFile($local, $path);
            }
            $zip->close();

            $size = (int) (filesize($tmp) ?: 0);
            $this->assertQuota($userId, $size);

            $blob = (string) Str::uuid();
            $stream = fopen($tmp, 'r');
            $this->disk()->writeStream('files/'.$blob, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $zipName = $this->uniqueName($this->archiveName($name, $refs, $entries), $userId, $folderId);
            $file = new StoredFile;
            $file->forceFill([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'file_folder_id' => $folderId,
                'name' => $zipName,
                'blob' => $blob,
                'size' => $size,
                'mime' => 'application/zip',
            ])->save();

            return $file;
        } finally {
            @unlink($tmp);
            foreach ($pulled as $p) {
                @unlink($p);
            }
        }
    }

    /** Extensions this extractor understands. */
    public const EXTRACTABLE = ['zip', 'tar', 'gz', 'tgz', 'bz2', 'tbz2'];

    public static function isExtractable(string $name, ?string $mime = null): bool
    {
        if ($mime === 'application/zip') {
            return true;
        }
        $lower = strtolower($name);
        foreach (['.zip', '.tar', '.tar.gz', '.tgz', '.tar.bz2', '.tbz2'] as $ext) {
            if (str_ends_with($lower, $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract an archive (zip/tar/tar.gz/tar.bz2) into a new folder named after
     * it, in the same folder. Returns the number of files extracted.
     */
    public function extract(int $userId, StoredFile $zip): int
    {
        $lower = strtolower($zip->name);
        $isTar = str_ends_with($lower, '.tar') || str_ends_with($lower, '.tar.gz')
            || str_ends_with($lower, '.tgz') || str_ends_with($lower, '.tar.bz2') || str_ends_with($lower, '.tbz2');

        return $isTar ? $this->extractTar($userId, $zip) : $this->extractZip($userId, $zip);
    }

    private function extractZip(int $userId, StoredFile $zip): int
    {
        $local = DiskTempFile::pull($this->disk(), 'files/'.$zip->blob, 'llzx');
        $za = new ZipArchive;
        try {
            if ($za->open($local, ZipArchive::RDONLY) !== true) {
                abort(422, __('files.archive_invalid'));
            }
            abort_if($za->numFiles > $this->maxEntries, 422, __('files.archive_too_many'));

            // Zip-bomb guard: sum uncompressed sizes before writing anything.
            $total = 0;
            for ($i = 0; $i < $za->numFiles; $i++) {
                $stat = $za->statIndex($i);
                $total += (int) ($stat['size'] ?? 0);
            }
            abort_if($total > $this->maxBytes, 413, __('files.archive_too_large'));
            $this->assertQuota($userId, $total);

            // Root folder for the extraction, named after the archive.
            $base = ArchiveName::sanitize(preg_replace('/\.zip$/i', '', $zip->name) ?: 'archive');
            $rootName = $this->uniqueFolderName($base, $userId, $zip->file_folder_id);
            $root = $this->makeFolder($userId, $rootName, $zip->file_folder_id);

            $dirCache = ['' => $root->id]; // relative-dir => folder id
            $count = 0;
            for ($i = 0; $i < $za->numFiles; $i++) {
                $raw = (string) $za->getNameIndex($i);
                $segments = $this->safeSegments($raw); // throws on traversal
                if ($segments === null) {
                    continue; // skip . / empty
                }
                $isDir = str_ends_with($raw, '/');

                $dirSegs = $isDir ? $segments : array_slice($segments, 0, -1);
                $folderId = $this->ensureDir($userId, $dirSegs, $dirCache);
                if ($isDir) {
                    continue;
                }

                $name = end($segments);
                $bytes = $za->getFromIndex($i);
                if ($bytes === false) {
                    continue;
                }
                $blob = (string) Str::uuid();
                $this->disk()->put('files/'.$blob, $bytes);
                $file = new StoredFile;
                $file->forceFill([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'file_folder_id' => $folderId,
                    'name' => $name,
                    'blob' => $blob,
                    'size' => strlen($bytes),
                    'mime' => $this->guessMime($name),
                ])->save();
                $count++;
            }
            $za->close();

            return $count;
        } finally {
            @unlink($local);
        }
    }

    private function extractTar(int $userId, StoredFile $arc): int
    {
        // PharData detects the compression from the file extension, so give the
        // pulled temp copy the archive's real extension.
        $pulled = DiskTempFile::pull($this->disk(), 'files/'.$arc->blob, 'lltar');
        preg_match('/(\.tar\.gz|\.tgz|\.tar\.bz2|\.tbz2|\.tar)$/i', $arc->name, $m);
        $local = $pulled.($m[1] ?? '.tar');
        rename($pulled, $local);
        $dest = sys_get_temp_dir().'/llxtar-'.bin2hex(random_bytes(6));

        try {
            // Use the system tar (never PharData, which could unserialize phar
            // metadata from attacker bytes). List headers first — no extraction —
            // to enforce the entry/size caps, then extract without owner/perms.
            $list = new Process(['tar', '-tvf', $local]);
            $list->setTimeout(60);
            $list->run();
            abort_unless($list->isSuccessful(), 422, __('files.archive_invalid'));

            $lines = array_values(array_filter(explode("\n", trim($list->getOutput()))));
            abort_if(count($lines) > $this->maxEntries, 422, __('files.archive_too_many'));
            $total = 0;
            foreach ($lines as $line) {
                // GNU tar -tvf: "perms owner/group SIZE date time name".
                if (preg_match('/^\S+\s+\S+\s+(\d+)\s/', $line, $mm)) {
                    $total += (int) $mm[1];
                }
            }
            abort_if($total > $this->maxBytes, 413, __('files.archive_too_large'));
            $this->assertQuota($userId, $total);

            @mkdir($dest, 0700, true);
            $extract = new Process(
                ['tar', '-xf', $local, '-C', $dest, '--no-same-owner', '--no-same-permissions']
            );
            $extract->setTimeout(120);
            $extract->run();
            abort_unless($extract->isSuccessful(), 422, __('files.archive_invalid'));

            $base = ArchiveName::sanitize(preg_replace('/(\.tar\.gz|\.tgz|\.tar\.bz2|\.tbz2|\.tar)$/i', '', $arc->name) ?: 'archive');
            $root = $this->makeFolder($userId, $this->uniqueFolderName($base, $userId, $arc->file_folder_id), $arc->file_folder_id);
            $dirCache = ['' => $root->id];

            $written = 0;
            $rii = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dest, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($rii as $item) {
                $rel = substr($item->getPathname(), strlen($dest) + 1);
                $segments = $this->safeSegments($rel);
                if ($segments === null) {
                    continue;
                }
                if ($item->isDir()) {
                    $this->ensureDir($userId, $segments, $dirCache);

                    continue;
                }
                $folderId = $this->ensureDir($userId, array_slice($segments, 0, -1), $dirCache);
                $bytes = file_get_contents($item->getPathname());
                if ($bytes === false) {
                    continue;
                }
                $blob = (string) Str::uuid();
                $this->disk()->put('files/'.$blob, $bytes);
                $file = new StoredFile;
                $file->forceFill([
                    'id' => (string) Str::uuid(), 'user_id' => $userId, 'file_folder_id' => $folderId,
                    'name' => end($segments), 'blob' => $blob, 'size' => strlen($bytes), 'mime' => $this->guessMime(end($segments)),
                ])->save();
                $written++;
            }

            return $written;
        } finally {
            @unlink($local);
            $this->rrmdir($dest);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    /** Recursively add a folder's files to $entries under $prefix. */
    private function collectFolder(int $userId, FileFolder $folder, string $prefix, array &$entries): void
    {
        foreach (StoredFile::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('user_id', $userId)->where('file_folder_id', $folder->id)->get() as $file) {
            $entries[$this->dedupePath($prefix.'/'.$file->name, $entries)] = $file;
        }
        foreach (FileFolder::withoutGlobalScopes()->where('user_id', $userId)
            ->where('parent_id', $folder->id)->get() as $sub) {
            $this->collectFolder($userId, $sub, $prefix.'/'.$sub->name, $entries);
        }
    }

    /**
     * Split a zip entry name into safe path segments, or null to skip it. Rejects
     * absolute paths, backslashes and any '..' traversal (zip-slip).
     *
     * @return array<int, string>|null
     */
    private function safeSegments(string $name): ?array
    {
        $name = str_replace('\\', '/', $name);
        abort_if(str_starts_with($name, '/'), 422, __('files.archive_invalid'));
        $out = [];
        foreach (explode('/', $name) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            abort_if($seg === '..', 422, __('files.archive_invalid'));
            $out[] = ArchiveName::sanitize($seg);
        }

        return $out === [] ? null : $out;
    }

    /** Ensure the nested directory chain exists, returning the leaf folder id. */
    private function ensureDir(int $userId, array $segments, array &$cache): string
    {
        $path = '';
        $parentId = $cache[''];
        foreach ($segments as $seg) {
            $path = $path === '' ? $seg : $path.'/'.$seg;
            if (isset($cache[$path])) {
                $parentId = $cache[$path];

                continue;
            }
            $folder = $this->makeFolder($userId, $seg, $parentId);
            $cache[$path] = $folder->id;
            $parentId = $folder->id;
        }

        return $parentId;
    }

    private function makeFolder(int $userId, string $name, ?string $parentId): FileFolder
    {
        $folder = new FileFolder;
        $folder->forceFill([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'parent_id' => $parentId,
            'name' => $name,
        ])->save();

        return $folder;
    }

    private function assertQuota(int $userId, int $incoming): void
    {
        $quota = (int) config('files.quota_mb', 0) * 1024 * 1024;
        if ($quota <= 0) {
            return;
        }
        $used = (int) StoredFile::withoutGlobalScopes()->withTrashed()->where('user_id', $userId)->sum('size')
            + (int) FileVersion::where('user_id', $userId)->sum('size');
        if ($used + $incoming > $quota) {
            throw new HttpException(413, __('files.quota_exceeded'));
        }
    }

    /** Avoid duplicate zip entry paths by suffixing (1), (2)… */
    private function dedupePath(string $path, array $entries): string
    {
        if (! isset($entries[$path])) {
            return $path;
        }
        $dot = strrpos($path, '.');
        $stem = $dot === false ? $path : substr($path, 0, $dot);
        $ext = $dot === false ? '' : substr($path, $dot);
        $n = 1;
        while (isset($entries[$stem.' ('.$n.')'.$ext])) {
            $n++;
        }

        return $stem.' ('.$n.')'.$ext;
    }

    private function archiveName(?string $name, array $refs, array $entries): string
    {
        if ($name !== null && trim($name) !== '') {
            $name = ArchiveName::sanitize(trim($name));

            return str_ends_with(strtolower($name), '.zip') ? $name : $name.'.zip';
        }
        // Single item: name after it; otherwise a generic name.
        if (count($refs) === 1) {
            $only = array_key_first($entries);

            return ArchiveName::sanitize(pathinfo((string) $only, PATHINFO_FILENAME) ?: 'archive').'.zip';
        }

        return 'archive.zip';
    }

    private function uniqueName(string $name, int $userId, ?string $folderId): string
    {
        $used = StoredFile::withoutGlobalScopes()->whereNull('deleted_at')->where('user_id', $userId)
            ->where('file_folder_id', $folderId)->pluck('name')->flip()->all();

        return ArchiveName::unique($name, $used);
    }

    private function uniqueFolderName(string $name, int $userId, ?string $parentId): string
    {
        $used = FileFolder::withoutGlobalScopes()->where('user_id', $userId)
            ->where('parent_id', $parentId)->pluck('name')->flip()->all();

        return ArchiveName::unique($name, $used);
    }

    private function guessMime(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return [
            'pdf' => 'application/pdf', 'txt' => 'text/plain', 'md' => 'text/markdown',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'zip' => 'application/zip',
            'json' => 'application/json', 'csv' => 'text/csv', 'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg',
        ][$ext] ?? 'application/octet-stream';
    }
}
