<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GalleryPhoto;
use App\Services\GalleryFaceProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Detect + group faces for a gallery photo off the web request (worker), like
 * thumbnails/embeddings. Best-effort; a no-op when face ML / pgvector are off.
 */
class DetectGalleryFaces implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $photoId) {}

    public function handle(GalleryFaceProcessor $processor): void
    {
        $photo = GalleryPhoto::withTrashed()->find($this->photoId);
        if ($photo instanceof GalleryPhoto) {
            $processor->process($photo);
        }
    }
}
