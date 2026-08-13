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
 * Process an uploaded video off the web request (worker), exactly like thumbnail
 * generation: ffprobe for metadata, a poster frame for the grid, and a
 * web-friendly MP4 rendition when the source is not directly playable. Bounded
 * by the worker's long timeout; one video at a time as workers drain the queue.
 */
class ProcessGalleryVideo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $photoId) {}

    public function handle(GalleryController $controller, ImageManagerFactory $images): void
    {
        $photo = GalleryPhoto::withTrashed()->find($this->photoId);
        if ($photo instanceof GalleryPhoto) {
            $controller->processVideo($photo, $images);
        }
    }
}
