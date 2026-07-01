<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Re-queue every photo for processing, so renditions and EXIF metadata are
 * regenerated (e.g. after an extractor improvement).
 */
#[Signature('photos:rescan')]
#[Description('Re-process all photos to refresh their renditions and metadata')]
class RescanPhotos extends Command
{
    public function handle(): int
    {
        $count = 0;

        Photo::query()->orderBy('id')->each(function (Photo $photo) use (&$count): void {
            ProcessPhoto::dispatch($photo->id);
            $count++;
        });

        $this->info("Queued {$count} photo(s) for re-processing.");

        return self::SUCCESS;
    }
}
