<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EmbedPhoto;
use App\Models\Photo;
use App\Services\Gallery\MachineLearning;
use Illuminate\Console\Command;

/**
 * Backfills similarity data: dispatches EmbedPhoto for photos still missing a
 * perceptual hash or (when ML is enabled) a CLIP embedding. Safe to re-run.
 */
class EmbedPhotos extends Command
{
    protected $signature = 'gallery:embed {--all : Re-dispatch for every ready photo}';

    protected $description = 'Backfill perceptual hashes and CLIP embeddings for photos';

    public function handle(MachineLearning $ml): int
    {
        $all = (bool) $this->option('all');
        $mlOn = $ml->enabled();

        $count = 0;
        Photo::query()
            ->where('status', 'ready')
            ->when(! $all, function ($q) use ($mlOn): void {
                $q->where(function ($w) use ($mlOn): void {
                    $w->whereNull('phash');
                    if ($mlOn) {
                        $w->orWhereNull('embedded_at');
                    }
                });
            })
            ->eachById(function (Photo $photo) use (&$count): void {
                EmbedPhoto::dispatch($photo->id);
                $count++;
            });

        $this->info("Dispatched embedding for {$count} photo(s).".($mlOn ? '' : ' (ML disabled: perceptual hashes only.)'));

        return self::SUCCESS;
    }
}
