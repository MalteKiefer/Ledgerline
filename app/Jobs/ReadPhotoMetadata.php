<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Photo;
use App\Services\Gallery\PhotoStorage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-reads a photo's EXIF metadata and refreshes its reverse-geocoded place.
 */
class ReadPhotoMetadata implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public bool $failOnTimeout = true;

    public function __construct(public int $photoId) {}

    public function handle(PhotoStorage $storage): void
    {
        $photo = Photo::find($this->photoId);

        if ($photo === null) {
            return;
        }

        $storage->readMetadata($photo);
    }
}
