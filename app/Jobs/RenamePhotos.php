<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Photo;
use App\Services\Gallery\PhotoStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applies the configured filename template to a photo's display name.
 */
class RenamePhotos implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $photoId) {}

    public function handle(PhotoStorage $storage): void
    {
        $photo = Photo::find($this->photoId);

        if ($photo === null) {
            return;
        }

        $storage->applyNameTemplate($photo);
    }
}
