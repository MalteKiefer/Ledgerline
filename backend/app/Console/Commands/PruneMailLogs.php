<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailLog;
use Illuminate\Console\Command;

/**
 * Prune per-account mail sync/ingest diagnostic logs older than the configured
 * retention window (metadata only; a short-lived diagnostic trail).
 */
class PruneMailLogs extends Command
{
    protected $signature = 'mail-logs:prune';

    protected $description = 'Delete mail sync/ingest diagnostic logs older than the retention window.';

    public function handle(): int
    {
        $configured = config('mail_archive.log_retention_days', 30);
        $days = is_numeric($configured) ? (int) $configured : 30;
        $cutoff = now()->subDays(max(1, $days));

        $result = MailLog::query()->where('created_at', '<', $cutoff)->delete();
        $deleted = is_numeric($result) ? (int) $result : 0;
        $this->info("Pruned {$deleted} mail log line(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
