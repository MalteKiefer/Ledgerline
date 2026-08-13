<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\GalleryPhoto;
use App\Support\BlobStore;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared, read-only gallery-photo byte streaming for the public-link and
 * shared-with-me controllers. Serves the cached WebP thumbnail/preview (same
 * paths GalleryController writes) and the original, always with a script-less
 * sandbox CSP + nosniff. Download (attachment) is opt-in per call.
 */
trait StreamsGalleryPhoto
{
    private function galleryDisk(): string
    {
        $d = config('files.disk');

        return is_string($d) ? $d : 'files';
    }

    private function galleryFs(): Filesystem
    {
        return Storage::disk($this->galleryDisk());
    }

    private function galleryThumbPath(GalleryPhoto $p): string
    {
        return 'gallery/thumb/'.$p->id.'-'.$p->version.'.webp';
    }

    private function galleryPreviewPath(GalleryPhoto $p): string
    {
        return 'gallery/preview/'.$p->id.'-'.$p->version.'.webp';
    }

    /** @return array<string, string> */
    private function sandboxHeaders(string $mime): array
    {
        return [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ];
    }

    /** Cached WebP thumbnail; 404 when not generated yet. */
    private function streamGalleryThumb(GalleryPhoto $p): StreamedResponse
    {
        $path = $this->galleryThumbPath($p);
        abort_unless($this->galleryFs()->exists($path), 404);

        return $this->galleryFs()->response($path, 'thumb.webp', $this->sandboxHeaders('image/webp'), 'inline');
    }

    /** Browser-viewable WebP preview; 404 when not generated yet. */
    private function streamGalleryPreview(GalleryPhoto $p): StreamedResponse
    {
        $path = $this->galleryPreviewPath($p);
        abort_unless($this->galleryFs()->exists($path), 404);

        return $this->galleryFs()->response($path, 'preview.webp', $this->sandboxHeaders('image/webp'), 'inline');
    }

    /** The original bytes; inline unless $download (attachment). */
    private function streamGalleryOriginal(GalleryPhoto $p, bool $download): StreamedResponse
    {
        $rel = (string) $p->storage_path;
        abort_unless($rel !== '' && $this->galleryFs()->exists($rel), 404);
        $etag = 'gal-'.$p->id.'-'.$p->version;
        $resp = $this->galleryFs()->response(
            $rel,
            $this->safeGalleryName($p->name),
            $this->sandboxHeaders($p->mime ?? 'application/octet-stream'),
            $download ? 'attachment' : 'inline',
        );

        return BlobStore::immutableResponse($resp, $etag);
    }

    private function safeGalleryName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'photo' : $clean;
    }
}
