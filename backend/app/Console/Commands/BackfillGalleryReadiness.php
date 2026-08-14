<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GalleryPhoto;
use App\Support\BlobStore;
use Illuminate\Console\Command;

/**
 * One-time backfill of thumb_ready/preview_ready for existing gallery rows after
 * the readiness columns were added (they default to false, which would show every
 * existing photo as a spinner). Stats each rendition path ONCE and sets the flags —
 * the renditions already exist, so this is cheap (roughly one old /gallery/data
 * worth of stats) and never re-renders. New/edited photos get the flags from the
 * worker; this only covers the pre-existing backlog.
 */
class BackfillGalleryReadiness extends Command
{
    protected $signature = 'gallery:backfill-readiness';

    protected $description = 'Set thumb_ready/preview_ready on existing gallery photos by stat-ing their rendition once (idempotent)';

    public function handle(): int
    {
        $disk = BlobStore::disk();
        $updated = 0;
        GalleryPhoto::withTrashed()->orderBy('id')->chunkById(200, function ($photos) use ($disk, &$updated): void {
            foreach ($photos as $photo) {
                // Rendition paths are versioned: gallery/{thumb,preview}/{id}-{version}.webp.
                $suffix = $photo->id.'-'.$photo->version.'.webp';
                $thumb = $disk->exists('gallery/thumb/'.$suffix);
                $preview = $disk->exists('gallery/preview/'.$suffix);
                if ((bool) $photo->thumb_ready === $thumb && (bool) $photo->preview_ready === $preview) {
                    continue;
                }
                $photo->forceFill(['thumb_ready' => $thumb, 'preview_ready' => $preview])->saveQuietly();
                $updated++;
            }
        });
        $this->info("Backfilled readiness on {$updated} photo(s).");

        return self::SUCCESS;
    }
}
