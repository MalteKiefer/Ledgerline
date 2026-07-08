<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Face;
use App\Models\Photo;
use App\Support\BlobStore;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permanently purges photos trashed longer than the retention window (row +
 * blobs), then reclaims orphaned gallery blob files that nothing references.
 * The row is deleted first (so its PhotoObserver frees faces/crops/shares and a
 * DB failure rolls back with the bytes intact); the bytes are unlinked only
 * after the row is gone. Scheduled daily.
 */
class PruneTrashedPhotos extends Command
{
    protected $signature = 'gallery:prune-trash';

    protected $description = 'Permanently delete photos trashed longer than the retention window and reclaim orphan gallery blobs';

    public function handle(): int
    {
        $disk = BlobStore::disk();
        $cutoff = Carbon::now()->subDays((int) config('gallery.trash_retention_days', 30));

        $purged = 0;
        Photo::withoutGlobalScopes()->onlyTrashed()->where('deleted_at', '<', $cutoff)
            ->chunkById(200, function ($photos) use ($disk, &$purged): void {
                foreach ($photos as $photo) {
                    $paths = $photo->allPaths();
                    try {
                        DB::transaction(fn () => $photo->forceDelete());
                    } catch (\Throwable) {
                        continue; // keep the bytes; don't strand the rest
                    }
                    $disk->delete($paths);
                    $purged++;
                }
            });

        // Orphan sweep: reclaim blob files under photos/** that no photo row
        // (live OR trashed) references and that are older than the grace window
        // (so an upload whose row hasn't been saved yet is never reaped).
        $referenced = [];
        Photo::withoutGlobalScopes()->withTrashed()
            ->select(['id', 'disk_path', 'thumb_path', 'medium_path', 'motion_path'])
            ->chunkById(1000, function ($photos) use (&$referenced): void {
                foreach ($photos as $photo) {
                    foreach ($photo->allPaths() as $p) {
                        $referenced[$p] = true;
                    }
                }
            });
        // Face-crop thumbnails live under faces/** and are referenced by
        // Face.thumb_path — include them so a crashed crop-write (crop on disk,
        // row missing/updated) doesn't leak forever.
        foreach (Face::whereNotNull('thumb_path')->pluck('thumb_path') as $tp) {
            $referenced[$tp] = true;
        }

        $graceTs = Carbon::now()->subHours((int) config('gallery.blob_orphan_grace_hours', 24))->getTimestamp();
        $swept = 0;
        foreach (['photos', 'faces'] as $prefix) {
            foreach ($disk->allFiles($prefix) as $path) {
                if (isset($referenced[$path])) {
                    continue;
                }
                try {
                    if ($disk->lastModified($path) > $graceTs) {
                        continue;
                    }
                } catch (\Throwable) {
                    continue;
                }
                $disk->delete($path);
                $swept++;
            }
        }

        $this->info("Purged {$purged} trashed photo(s); swept {$swept} orphan gallery blob(s).");

        return self::SUCCESS;
    }
}
