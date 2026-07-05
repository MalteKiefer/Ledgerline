<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Single entry point for the unencrypted blob disk that backs files, photos,
 * mail archives and exports. Every module used to inline the files disk;
 * routing them through here makes the backing disk (local / S3 / R2) a
 * one-line change.
 */
final class BlobStore
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('files.disk'));
    }
}
