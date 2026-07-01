<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Photo;
use App\Services\Gallery\PhotoStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Generates a photo's renditions and reads its EXIF metadata in the background,
 * so uploads return immediately.
 */
class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $photoId) {}

    public function handle(PhotoStorage $storage): void
    {
        $photo = Photo::find($this->photoId);

        if ($photo === null) {
            return;
        }

        try {
            $storage->process($photo);
        } catch (Throwable $e) {
            $photo->forceFill(['status' => 'failed'])->save();

            throw $e;
        }
    }
}
