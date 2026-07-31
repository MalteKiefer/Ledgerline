<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BlobAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Prune the blob/shard forensic trail past its retention window. Kept longer than
 * the device access trail (it's the record used to reconstruct a data-loss event),
 * but still bounded so the table doesn't grow without limit.
 */
class PruneBlobAuditLog extends Command
{
    protected $signature = 'blob-audit:prune';

    protected $description = 'Delete blob forensic-trail rows past the retention window';

    public function handle(): int
    {
        $cfg = config('ops.blob_audit_retention_days', 180);
        $days = is_numeric($cfg) ? (int) $cfg : 180;
        if ($days <= 0) {
            $this->info('Blob-audit retention disabled (keep forever).');

            return self::SUCCESS;
        }

        $result = BlobAuditLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();
        $deleted = is_numeric($result) ? (int) $result : 0;
        $this->info($deleted.' blob-audit row(s) pruned.');

        return self::SUCCESS;
    }
}
