<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ServerCheck;
use Illuminate\Console\Command;

/**
 * Prune reachability history outside the retention window.
 *
 * This table grows quickly by design — a handful of rows per server every few
 * minutes — so pruning is not housekeeping, it is what keeps the feature
 * affordable. Unlike snapshots, nothing here is worth keeping past the window:
 * the current state is always the newest row, and it is written continuously.
 */
class PruneServerChecks extends Command
{
    protected $signature = 'servers:prune-checks';

    protected $description = 'Delete reachability checks older than the retention window.';

    public function handle(): int
    {
        $configured = config('servers.check_retention_days', 30);
        $days = is_numeric($configured) ? (int) $configured : 30;
        $cutoff = now()->subDays(max(1, $days));

        $result = ServerCheck::query()->where('created_at', '<', $cutoff)->delete();
        $deleted = is_numeric($result) ? (int) $result : 0;
        $this->info("Pruned {$deleted} reachability check(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
