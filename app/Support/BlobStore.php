<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Single entry point for the unencrypted blob disk that backs files, photos,
 * contact avatars and exports. Every module used to inline the files disk;
 * routing them through here makes the backing disk (local / S3 / R2) a
 * one-line change.
 */
final class BlobStore
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('files.disk'));
    }

    /**
     * Apply the standard immutable-blob headers to a response that streams
     * raw ciphertext. Called after the disk response is created.
     *
     * Headers (byte-exact):
     *   Content-Type:             application/octet-stream
     *   X-Content-Type-Options:   nosniff
     *   Content-Security-Policy:  default-src 'none'; sandbox
     *   Cache-Control:            private, max-age=31536000, immutable
     *   ETag:                     "$etag"
     */
    public static function immutableResponse(
        StreamedResponse $r,
        string $etag,
    ): StreamedResponse {
        $r->headers->set('Content-Type', 'application/octet-stream');
        $r->headers->set('X-Content-Type-Options', 'nosniff');
        $r->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        $r->headers->set('Cache-Control', 'private, max-age=31536000, immutable');
        $r->headers->set('ETag', '"'.$etag.'"');

        return $r;
    }
}
