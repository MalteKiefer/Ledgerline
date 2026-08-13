<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\GalleryController;
use App\Models\GalleryPhoto;
use App\Support\MachineLearning;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Compute a photo's CLIP embedding off the web request (worker), like thumbnails
 * and video processing. Best-effort — a no-op when ML is disabled/unreachable or
 * pgvector is unavailable.
 */
class EmbedGalleryPhoto implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $photoId) {}

    public function handle(GalleryController $controller, MachineLearning $ml): void
    {
        $photo = GalleryPhoto::withTrashed()->find($this->photoId);
        if ($photo instanceof GalleryPhoto) {
            $controller->embedPhoto($photo, $ml);
        }
    }
}
