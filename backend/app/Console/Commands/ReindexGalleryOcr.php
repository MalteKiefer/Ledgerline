<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Files\ContentIndexer;
use Illuminate\Console\Command;

/** Backfill/refresh gallery photo OCR text (text inside images → searchable). */
class ReindexGalleryOcr extends Command
{
    protected $signature = 'gallery:ocr {--user=0 : Limit to one user id (0 = all)}';

    protected $description = 'Extract OCR text from existing gallery photos';

    public function handle(ContentIndexer $indexer): int
    {
        $user = (int) $this->option('user');
        $this->info('OCR-indexing photos'.($user > 0 ? " for user {$user}" : ' (all users)').'…');
        $n = $indexer->reindexGallery($user > 0 ? $user : null);
        $this->info("Done: {$n} photos processed.");

        return self::SUCCESS;
    }
}
