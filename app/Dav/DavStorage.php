<?php

declare(strict_types=1);

namespace App\Dav;

use App\Models\FileEntry;
use App\Models\FileVersion;
use App\Support\BlobStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
