<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\GalleryController;
use App\Models\GalleryPhoto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * OCR a photo off the web request (worker), like thumbnails/embeddings. Best-effort;
 * fills gallery_photos.ocr_text so text inside images is searchable.
 */
class ExtractGalleryOcr implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $photoId) {}

    public function handle(GalleryController $controller): void
    {
        $photo = GalleryPhoto::withTrashed()->find($this->photoId);
        if ($photo instanceof GalleryPhoto) {
            $controller->ocrPhoto($photo);
        }
    }
}
