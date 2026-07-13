<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Enforce the retention window on the append-only audit log. Entries are never
 * modified, only aged out past ops.audit_retention_days. Runs daily.
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Delete audit log entries past the retention window';

    public function handle(): int
    {
        $days = (int) config('ops.audit_retention_days', 365);
        if ($days <= 0) {
            $this->info('Audit retention disabled (keep forever).');

            return self::SUCCESS;
        }

        $deleted = AuditLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();
        $this->info($deleted.' audit entr'.($deleted === 1 ? 'y' : 'ies').' pruned.');

        return self::SUCCESS;
    }
}
