<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DetectGalleryFaces;
use App\Models\GalleryPhoto;
use Illuminate\Console\Command;

/**
 * Queue face detection for gallery photos (backfill after enabling face ML). The
 * detection + grouping runs on the worker.
 */
class DetectGalleryFacesCommand extends Command
{
    protected $signature = 'gallery:faces';

    protected $description = 'Queue face detection + grouping for gallery photos';

    public function handle(): int
    {
        $count = 0;
        GalleryPhoto::query()->orderBy('id')->chunkById(200, function ($photos) use (&$count): void {
            foreach ($photos as $photo) {
                DetectGalleryFaces::dispatch($photo->id);
                $count++;
            }
        });
        $this->info("Queued {$count} face-detection job(s).");

        return self::SUCCESS;
    }
}
