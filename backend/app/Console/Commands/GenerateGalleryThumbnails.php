<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateGalleryThumbnail;
use App\Models\GalleryPhoto;
use Illuminate\Console\Command;

/**
 * Queue thumbnail generation for gallery photos (backfill after deploy or a
 * cache wipe). Runs the actual decode on the worker, never inline.
 */
class GenerateGalleryThumbnails extends Command
{
    protected $signature = 'gallery:thumbs';

    protected $description = 'Queue WebP thumbnail generation for gallery photos (idempotent — the job skips photos that already have a thumbnail)';

    public function handle(): int
    {
        $count = 0;
        GalleryPhoto::withTrashed()->orderBy('id')->chunkById(200, function ($photos) use (&$count): void {
            foreach ($photos as $photo) {
                GenerateGalleryThumbnail::dispatch($photo->id);
                $count++;
            }
        });
        $this->info("Queued {$count} thumbnail job(s).");

        return self::SUCCESS;
    }
}
