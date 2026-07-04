<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * Streams a stored file to a local temp path (for tools that need a real file:
 * ffmpeg, exiftool, Imagick, ZipArchive). Centralises the readStream + temp +
 * finally-close pattern; the caller deletes the returned path when done.
 */
class DiskTempFile
{
    public static function pull(Filesystem $disk, string $path, string $prefix = 'dl'): string
    {
        $tmp = tempnam(sys_get_temp_dir(), $prefix);
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            @unlink($tmp);
            throw new RuntimeException("Unable to read stream for {$path}");
        }
        try {
            file_put_contents($tmp, $stream);
        } finally {
            fclose($stream);
        }

        return $tmp;
    }
}
