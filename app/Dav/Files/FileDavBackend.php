<?php

declare(strict_types=1);

namespace App\Dav\Files;

use App\Models\DavCredential;
use App\Models\FileBlob;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Models\UserSetting;
use App\Support\BlobStore;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Sabre\DAV\Exception\InsufficientStorage;

/**
 * Shared helpers for the WebDAV file tree: principal→user resolution, blob
 * read/write on the files disk, MIME sniffing and quota enforcement. The nodes
 * stay thin; all storage concerns live here. Every query is owner-scoped by the
 * resolved user id (DAV runs without an Auth context, so no global scope).
 */
class FileDavBackend
{
    public function disk(): Filesystem
    {
        return BlobStore::disk();
    }

    /** The user id behind a principal uri (principals/<username>). */
    public function userId(string $principalUri): int
    {
        return (int) DavCredential::where('username', basename($principalUri))->value('user_id');
    }

    /** Store bytes as a new blob and return its uuid. Enforces the per-user quota. */
    public function storeBlob(int $userId, $data): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'davput');
        $out = fopen($tmp, 'w');
        if (is_resource($data)) {
            stream_copy_to_stream($data, $out);
        } else {
            fwrite($out, (string) $data);
        }
        fclose($out);
        $size = (int) (filesize($tmp) ?: 0);

        if ($this->overQuota($userId, $size)) {
            @unlink($tmp);
            throw new InsufficientStorage('Storage quota exceeded.');
        }

        $blob = (string) Str::uuid();
        $stream = fopen($tmp, 'r');
        $this->disk()->writeStream('files/'.$blob, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        @unlink($tmp);

        // Record the uploader so this blob is recognised by the web full-replace
        // sync and reclaimable by the orphan sweep if never attached.
        FileBlob::create(['blob' => $blob, 'user_id' => $userId, 'created_at' => now()]);

        return $blob;
    }

    /**
     * Snapshot a file's previous content as a version before a DAV overwrite,
     * then cap the per-user history and free any pruned (unreferenced) blobs —
     * so overwriting over WebDAV keeps the same recoverable history as the web
     * client, instead of silently discarding the prior content.
     */
    public function snapshotVersion(StoredFile $file, string $oldBlob, int $size, string $mime, string $name): array
    {
        FileVersion::create([
            'id' => (string) Str::uuid(), 'file_id' => $file->id, 'user_id' => (int) $file->user_id,
            'name' => $name !== '' ? $name : $file->name, 'mime' => $mime !== '' ? $mime : 'application/octet-stream',
            'size' => $size, 'blob' => $oldBlob, 'created_at' => now(),
        ]);
        $keep = min(10, max(1, (int) UserSetting::for((int) $file->user_id)->file_max_versions));
        $overflow = FileVersion::where('file_id', $file->id)->orderByDesc('created_at')->skip($keep)->take(1000)->get();
        // Drop the overflow version ROWS here (in the caller's transaction) but
        // return their blobs so the disk unlink happens AFTER commit — a rollback
        // must never leave a live version row pointing at already-erased bytes.
        $blobs = $overflow->pluck('blob')->all();
        if ($blobs !== []) {
            FileVersion::whereIn('id', $overflow->pluck('id'))->delete();
        }

        return $blobs;
    }

    /** Best-effort MIME from the filename, then the bytes. */
    public function guessMime(string $name, string $blob): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $byExt = [
            'pdf' => 'application/pdf', 'txt' => 'text/plain', 'md' => 'text/markdown',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'heic' => 'image/heic',
            'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'mp3' => 'audio/mpeg',
            'zip' => 'application/zip', 'json' => 'application/json', 'csv' => 'text/csv',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        if (isset($byExt[$ext])) {
            return $byExt[$ext];
        }
        try {
            $head = $this->disk()->read('files/'.$blob) ?: '';
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer(substr($head, 0, 4096));
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        } catch (\Throwable) {
        }

        return 'application/octet-stream';
    }

    /** Delete a blob only when no file (live OR trashed) and no kept version
     *  still references it — blobs are reference-shared across duplicates. */
    public function releaseBlob(?string $blob): void
    {
        if (! $blob) {
            return;
        }
        $stillUsed = StoredFile::withoutGlobalScopes()->withTrashed()->where('blob', $blob)->exists()
            || FileVersion::withoutGlobalScopes()->where('blob', $blob)->exists();
        if (! $stillUsed) {
            $this->disk()->delete('files/'.$blob);
            $this->disk()->delete('thumbs/'.$blob.'.jpg');
        }
    }

    private function overQuota(int $userId, int $incoming): bool
    {
        $quota = (int) config('files.quota_mb', 0) * 1024 * 1024;
        if ($quota <= 0) {
            return false;
        }
        $used = (int) StoredFile::withoutGlobalScopes()->withTrashed()->where('user_id', $userId)->sum('size')
            + (int) FileVersion::where('user_id', $userId)->sum('size');

        return ($used + $incoming) > $quota;
    }
}
