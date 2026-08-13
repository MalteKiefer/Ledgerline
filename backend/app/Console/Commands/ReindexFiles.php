<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Files\ContentIndexer;
use Illuminate\Console\Command;

/** Backfill/refresh file content search_text (PDF text + image OCR + plain text). */
class ReindexFiles extends Command
{
    protected $signature = 'files:reindex {--user=0 : Limit to one user id (0 = all)}';

    protected $description = 'Re-extract searchable content (PDF/OCR/text) for existing files';

    public function handle(ContentIndexer $indexer): int
    {
        $user = (int) $this->option('user');
        $this->info('Reindexing file content'.($user > 0 ? " for user {$user}" : ' (all users)').'…');
        $n = $indexer->reindexFiles($user > 0 ? $user : null);
        $this->info("Done: {$n} files reindexed.");

        return self::SUCCESS;
    }
}
