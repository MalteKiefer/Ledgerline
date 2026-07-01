<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Photo;
use App\Services\Gallery\PhotoStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Regenerates a photo's thumbnail and medium renditions from the original.
 */
class GeneratePhotoRenditions implements ShouldQueue
{
    use Queueable;

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

        try {
            $storage->renditions($photo);
        } catch (Throwable $e) {
            $photo->forceFill(['status' => 'failed'])->save();

            throw $e;
        }
    }
}
