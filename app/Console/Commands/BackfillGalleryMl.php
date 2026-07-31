<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GalleryPhoto;
use App\Services\Gallery\GalleryMl;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfill CLIP embeddings + faces for every photo that was uploaded while ML
 * was off (embedded_at IS NULL). Runs owner-agnostic (no auth in console, so the
 * OwnsUserData read scope is inert and every user's photos are visited), chunked
 * to bound memory, with a per-photo try/catch so one bad photo never aborts the
 * run. Idempotent: reprocess() sets embedded_at, so a re-run skips finished rows.
 */
class BackfillGalleryMl extends Command
{
    protected $signature = 'gallery:backfill-ml {--limit=0 : Max photos to process (0 = all)}';

    protected $description = 'Reprocess photos missing ML data (embedding + faces)';

    public function handle(GalleryMl $ml): int
    {
        if (! $ml->enabled()) {
            $this->warn('ML is disabled (gallery.ml_enabled) — nothing to backfill.');

            return self::SUCCESS;
        }

        $limitOpt = $this->option('limit');
        $limit = is_numeric($limitOpt) ? (int) $limitOpt : 0;

        $done = 0;
        $failed = 0;

        GalleryPhoto::query()
            ->whereNull('embedded_at')
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($ml, $limit, &$done, &$failed): bool {
                foreach ($chunk as $photo) {
                    try {
                        $ml->reprocess($photo);
                    } catch (Throwable $e) {
                        $failed++;
                        $this->error('Photo '.$photo->id.': '.$e->getMessage());

                        continue;
                    }
                    $done++;
                    if ($limit > 0 && $done >= $limit) {
                        return false;
                    }
                }

                return true;
            });

        $this->info("Backfilled {$done} photo(s), {$failed} failed.");

        return self::SUCCESS;
    }
}
