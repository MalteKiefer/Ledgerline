<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Paperless\PaperlessSync;
use Illuminate\Console\Command;

/**
 * Refreshes the cached Paperless tags, document types and correspondents.
 * Scheduled hourly; a no-op when Paperless is disabled or unconfigured.
 */
class SyncPaperless extends Command
{
    protected $signature = 'paperless:sync';

    protected $description = 'Refresh cached Paperless tags, document types and correspondents';

    public function handle(PaperlessSync $sync): int
    {
        try {
            $counts = $sync->run();
        } catch (\Throwable $e) {
            $this->error('Paperless sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($counts === []) {
            $this->info('Paperless not configured — nothing to sync.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Synced %d tag(s), %d document type(s), %d correspondent(s).',
            $counts['tag'] ?? 0,
            $counts['document_type'] ?? 0,
            $counts['correspondent'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
