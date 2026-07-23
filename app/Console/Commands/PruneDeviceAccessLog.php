<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DeviceAccessLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Prune the high-volume device access trail past its (short) retention window.
 * Separate from audit:prune because this trail is usage data, not a security
 * record, and rolls over far more quickly.
 */
class PruneDeviceAccessLog extends Command
{
    protected $signature = 'device-access-log:prune';

    protected $description = 'Delete device access-trail rows past the retention window';

    public function handle(): int
    {
        $cfg = config('ops.access_log_retention_days', 30);
        $days = is_numeric($cfg) ? (int) $cfg : 30;
        if ($days <= 0) {
            $this->info('Access-log retention disabled (keep forever).');

            return self::SUCCESS;
        }

        $result = DeviceAccessLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();
        $deleted = is_numeric($result) ? (int) $result : 0;
        $this->info($deleted.' device access-log row(s) pruned.');

        return self::SUCCESS;
    }
}
