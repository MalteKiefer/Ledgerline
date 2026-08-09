<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RequestLog;
use Illuminate\Console\Command;

/**
 * Trim the verbose request log to its retention window
 * (ops.request_log_retention_days). Scheduled daily.
 */
class PruneRequestLog extends Command
{
    protected $signature = 'request-log:prune';

    protected $description = 'Delete request-log rows older than the configured retention window.';

    public function handle(): int
    {
        $daysCfg = config('ops.request_log_retention_days', 30);
        $days = is_numeric($daysCfg) ? (int) $daysCfg : 30;
        if ($days <= 0) {
            $this->info('Retention disabled (0) — keeping all request-log rows.');

            return self::SUCCESS;
        }

        $result = RequestLog::where('created_at', '<', now()->subDays($days))->delete();
        $deleted = is_numeric($result) ? (int) $result : 0;

        $this->info("Pruned {$deleted} request-log row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
