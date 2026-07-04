<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DetectFaces;
use App\Models\Face;
use App\Models\Photo;
use App\Services\Gallery\MachineLearning;
use Illuminate\Console\Command;

/**
 * Backfills face detection: dispatches DetectFaces for photos that have not been
 * scanned yet (--all re-scans everything).
 */
class DetectFacesCommand extends Command
{
    protected $signature = 'gallery:faces {--all : Re-scan every ready photo}';

    protected $description = 'Detect faces in photos that have not been scanned';

    public function handle(MachineLearning $ml): int
    {
        if (! $ml->faceEnabled()) {
            $this->warn('Face recognition is disabled (FACE_ENABLED).');

            return self::SUCCESS;
        }

        $scanned = Face::query()->distinct()->pluck('photo_id')->all();

        $count = 0;
        Photo::query()->where('status', 'ready')
            ->when(! $this->option('all'), fn ($q) => $q->whereNotIn('id', $scanned))
            ->eachById(function (Photo $photo) use (&$count): void {
                DetectFaces::dispatch($photo->id);
                $count++;
            });

        $this->info("Dispatched face detection for {$count} photo(s).");

        return self::SUCCESS;
    }
}
