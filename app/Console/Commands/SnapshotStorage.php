<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ops\StorageHistory;
use Illuminate\Console\Command;

/**
 * Records today's storage usage per module so the System page can show growth
 * over time. Idempotent — one row per day.
 */
class SnapshotStorage extends Command
{
    protected $signature = 'ops:snapshot-storage';

    protected $description = 'Record a daily storage-usage snapshot per module';

    public function handle(StorageHistory $history): int
    {
        $snapshot = $history->capture();
        $this->info('Storage snapshot recorded: '.$snapshot->total_bytes.' bytes total.');

        return self::SUCCESS;
    }
}
