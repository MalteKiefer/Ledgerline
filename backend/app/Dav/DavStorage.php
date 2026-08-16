<?php

declare(strict_types=1);

namespace App\Dav;

use App\Models\FileEntry;
use App\Models\FileVersion;
use App\Support\BlobStore;
use App\Support\StorageUsage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Sabre\DAV\Exception\InsufficientStorage;

/**
 * Shared blob/versioning helpers for the WebDAV nodes — the same rules as
 * FilesController (bytes plaintext under files/{uuid}; a content replace archives
 * the current bytes as a FileVersion, keeping up to the per-user cap).
 */
class DavStorage
{
    /**
     * Write an incoming stream (or string) to a new blob; return [path, size, sha256].
     *
     * @return array{0: string, 1: int, 2: string|null}
     */
    public static function writeBlob(mixed $data): array
    {
        $disk = BlobStore::disk();
        $path = 'files/'.Str::uuid()->toString();
        $payload = is_resource($data) || is_string($data) ? $data : '';
        $disk->put($path, $payload);
        $size = (int) $disk->size($path);
        $sha = null;
        $stream = $disk->readStream($path);
        if (is_resource($stream)) {
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, $stream);
            fclose($stream);
            $sha = hash_final($ctx);
        }

        return [$path, $size, $sha];
    }

    /**
     * Enforce the owner's storage quota for a WebDAV write (parity with the HTTP
     * upload's 413). Deletes the just-written blob and throws 507 when over.
     * quota_mb <= 0 means unlimited.
     */
    public static function assertWithinQuota(int $justWrittenSize, string $blobPath): void
    {
        $uid = (int) (Auth::id() ?? 0);
        // `used` already includes the blob we just wrote (its row isn't saved yet,
        // but the sum is over file/gallery rows — so this still means comparing
        // used vs cap with the fresh blob's bytes added again on top).
        if ($uid > 0 && StorageUsage::wouldExceed($uid, $justWrittenSize)) {
            BlobStore::disk()->delete($blobPath);
            throw new InsufficientStorage('Storage quota exceeded.');
        }
    }

    /** Archive a file's current bytes as a version and prune beyond the keep cap. */
    public static function archiveVersion(FileEntry $file, int $keep): void
    {
        $v = new FileVersion;
        $v->forceFill([
            'file_id' => $file->id,
            'storage_path' => $file->storage_path,
            'size' => $file->size,
            'mime' => $file->mime,
            'sha256' => $file->sha256,
            'created_at' => Carbon::now(),
        ])->save();

        $stale = $file->versions()->orderByDesc('id')->skip(max(1, $keep))->take(1000)->get();
        foreach ($stale as $old) {
            BlobStore::disk()->delete((string) $old->storage_path);
            $old->delete();
        }
    }
}
