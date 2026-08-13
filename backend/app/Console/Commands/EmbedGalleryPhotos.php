<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EmbedGalleryPhoto;
use App\Models\GalleryPhoto;
use Illuminate\Console\Command;

/**
 * Queue CLIP embedding for gallery photos that do not have one yet (backfill
 * after enabling ML). The actual embedding runs on the worker.
 */
class EmbedGalleryPhotos extends Command
{
    protected $signature = 'gallery:embed {--all : Re-queue every photo, not only those without an embedding}';

    protected $description = 'Queue CLIP embedding for gallery photos (semantic search)';

    public function handle(): int
    {
        $count = 0;
        GalleryPhoto::query()
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('embedded_at'))
            ->orderBy('id')
            ->chunkById(200, function ($photos) use (&$count): void {
                foreach ($photos as $photo) {
                    EmbedGalleryPhoto::dispatch($photo->id);
                    $count++;
                }
            });
        $this->info("Queued {$count} embedding job(s).");

        return self::SUCCESS;
    }
}
