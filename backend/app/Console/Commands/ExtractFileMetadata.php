<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileEntry;
use App\Services\Files\FileMetadata;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-off backfill of the Files metadata column for files uploaded before the
 * extraction existed (v1.661.0). Runs the same FileMetadata::extract the upload
 * observer runs; owner-scope-free (all users). Best-effort per file. Re-running is
 * safe — by default it only touches rows with no metadata yet (--force redoes all).
 */
class ExtractFileMetadata extends Command
{
    protected $signature = 'files:extract-metadata {--force : Re-extract even where metadata already exists} {--limit=0 : Cap the number of files processed (0 = all)}';

    protected $description = 'Backfill extracted metadata (EXIF/PDF/STL/…) for existing files';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');
        $service = new FileMetadata;

        $query = FileEntry::withoutGlobalScopes()->orderBy('id');
        if (! $force) {
            $query->whereNull('metadata');
        }
        $total = (clone $query)->count();
        if ($limit > 0) {
            $total = min($total, $limit);
        }
        if ($total === 0) {
            $this->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        $this->info("Extracting metadata for {$total} file(s)…");
        $bar = $this->output->createProgressBar($total);
        $done = 0;
        $ok = 0;

        $query->chunkById(200, function ($files) use ($service, $bar, &$done, &$ok, $limit): bool {
            foreach ($files as $file) {
                if (! $file instanceof FileEntry) {
                    continue;
                }
                try {
                    $meta = $service->extract($file);
                    $file->forceFill(['metadata' => $meta])->saveQuietly();
                    if ($meta !== null) {
                        $ok++;
                    }
                } catch (Throwable) {
                    // best-effort per file
                }
                $bar->advance();
                $done++;
                if ($limit > 0 && $done >= $limit) {
                    return false; // stop chunking
                }
            }

            return true;
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done: {$done} processed, {$ok} with metadata.");

        return self::SUCCESS;
    }
}
