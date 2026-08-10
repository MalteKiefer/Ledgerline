<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\GalleryController;
use App\Models\GalleryPhoto;
use App\Support\ImageManagerFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generate a gallery photo's WebP thumbnail off the web request. A HEIC decode
 * is ~20s; doing it inline in thumb() lets a grid of N photos stampede the FPM
 * pool. The thumbnail is produced here (worker: high memory, long timeout),
 * written to the files disk, and thereafter served cache-only. Queued on upload
 * and whenever an edit changes rotation/flip (the version bump changes the
 * cache path). Idempotent — a no-op when the cache already exists.
 */
class GenerateGalleryThumbnail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $photoId) {}

    public function handle(GalleryController $controller, ImageManagerFactory $images): void
    {
        // No auth in a queued context → the owner global scope is a no-op, so a
        // plain find resolves the row (thumbnailing is an internal operation).
        $photo = GalleryPhoto::withTrashed()->find($this->photoId);
        if ($photo instanceof GalleryPhoto) {
            $controller->generateThumb($photo, $images);
        }
    }
}
