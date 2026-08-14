<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Support\Archiver;
use App\Support\BlobStore;
use App\Support\FilesUsage;
use App\Support\Redactor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Extract an uploaded archive into a destination folder — WORKER-ONLY, because it
 * decodes untrusted input (a decompression bomb must never block a web/Octane
 * worker). Reconstructs the archive's folder tree under $destFolderId, streams each
 * extracted file to a fresh blob, and enforces the owner's storage quota. The
 * Archiver hardens zip-slip + entry/byte caps; this job adds the quota + timeout.
 */
class ExtractArchive implements ShouldQueue
{
    use Queueable;

    /** A large archive over a remote disk can take a while; bomb defence is in Archiver + the quota. */
    public int $timeout = 2400;

    public int $tries = 1;

    public function __construct(
        public int $fileId,
        public int $userId,
        public ?int $destFolderId,
        public ?string $password = null,
    ) {}

    public function handle(): void
    {
        $archive = FileEntry::query()->withoutGlobalScopes()
            ->where('user_id', $this->userId)->whereKey($this->fileId)->first();
        if ($archive === null) {
            return;
        }
        $disk = BlobStore::disk();
        $src = (string) $archive->storage_path;
        if ($src === '' || ! $disk->exists($src)) {
            return;
        }

        // Stage the archive to a local temp file that keeps the original name (the
        // Archiver detects the format from the extension).
        $tmpArc = sys_get_temp_dir().'/llarc-in-'.Str::uuid()->toString().'-'.$this->safe((string) $archive->name);
        $in = $disk->readStream($src);
        $out = @fopen($tmpArc, 'wb');
        if (! is_resource($in) || ! is_resource($out)) {
            if (is_resource($in)) {
                fclose($in);
            }
            @unlink($tmpArc);

            return;
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $root = null;
        try {
            $result = Archiver::extract($tmpArc, $this->password === '' ? null : $this->password);
            $root = $result['root'];
            $files = $result['files'];
            if ($files === []) {
                AppNotification::record($this->userId, 'warning', __('files.archive_extract_empty', ['name' => $archive->name]), null, 'files');

                return;
            }

            // Quota: total extracted bytes must fit the owner's remaining quota.
            $incoming = array_sum(array_map(fn (string $p): int => (int) @filesize($p), $files));
            $quota = $this->quotaBytes();
            if ($quota !== null && FilesUsage::forUser($this->userId) + $incoming > $quota) {
                AppNotification::record($this->userId, 'error', __('files.archive_extract_quota', ['name' => $archive->name]), null, 'files');

                return;
            }

            /** @var array<string, ?int> $folderCache  relDir → folder id */
            $folderCache = ['' => $this->destFolderId];
            $written = 0;
            foreach ($files as $rel => $abs) {
                $rel = str_replace('\\', '/', $rel);
                $dir = trim(dirname($rel), '.');
                $dir = $dir === '/' ? '' : ltrim($dir, '/');
                $name = basename($rel);
                if ($name === '' || $name === '.' || $name === '..') {
                    continue;
                }
                $parentId = $this->ensureFolderPath($dir, $folderCache);

                $blobPath = 'files/'.Str::uuid()->toString();
                $fh = @fopen($abs, 'rb');
                if (! is_resource($fh)) {
                    continue;
                }
                $disk->writeStream($blobPath, $fh);
                fclose($fh);
                $size = (int) @filesize($abs);
                $mime = $this->mimeOf($abs);
                $sha = @hash_file('sha256', $abs) ?: null;

                $entry = new FileEntry;
                $entry->forceFill([
                    'user_id' => $this->userId,
                    'file_folder_id' => $parentId,
                    'name' => $name,
                    'storage_path' => $blobPath,
                    'size' => $size,
                    'mime' => $mime,
                    'sha256' => $sha,
                ])->save();
                $written++;
            }

            AppNotification::record($this->userId, 'info', __('files.archive_extract_done', ['name' => $archive->name, 'count' => $written]), null, 'files');
        } catch (\Throwable $e) {
            AppNotification::record($this->userId, 'error', __('files.archive_extract_failed', ['name' => $archive->name]), Redactor::redact($e->getMessage()), 'files');
        } finally {
            if ($root !== null) {
                Archiver::rmrf($root);
            }
            @unlink($tmpArc);
        }
    }

    /**
     * Create (or reuse) the folder chain for a relative dir under the destination.
     *
     * @param array<string, int|null> $cache
     */
    private function ensureFolderPath(string $dir, array &$cache): ?int
    {
        if (array_key_exists($dir, $cache)) {
            return $cache[$dir];
        }
        $parent = $this->destFolderId;
        $accum = '';
        foreach (explode('/', $dir) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $accum = $accum === '' ? $segment : $accum.'/'.$segment;
            if (array_key_exists($accum, $cache)) {
                $parent = $cache[$accum];

                continue;
            }
            $existing = FileFolder::query()->withoutGlobalScopes()
                ->where('user_id', $this->userId)
                ->where('parent_id', $parent)
                ->where('name', $segment)
                ->whereNull('deleted_at')
                ->first();
            if ($existing !== null) {
                $parent = (int) $existing->id;
            } else {
                $folder = new FileFolder;
                $folder->forceFill(['user_id' => $this->userId, 'parent_id' => $parent, 'name' => $segment])->save();
                $parent = (int) $folder->id;
            }
            $cache[$accum] = $parent;
        }
        $cache[$dir] = $parent;

        return $parent;
    }

    private function quotaBytes(): ?int
    {
        $mb = config('files.quota_mb');
        $mb = is_numeric($mb) ? (int) $mb : 0;

        return $mb > 0 ? $mb * 1024 * 1024 : null;
    }

    private function mimeOf(string $path): ?string
    {
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if ($f === false) {
            return null;
        }
        $m = @finfo_file($f, $path);
        finfo_close($f);

        return is_string($m) && $m !== '' ? $m : null;
    }

    private function safe(string $name): string
    {
        $c = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);

        return is_string($c) && $c !== '' ? substr($c, 0, 120) : 'archive';
    }
}
