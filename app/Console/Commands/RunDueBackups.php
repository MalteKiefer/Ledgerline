<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunBackupJob;
use App\Models\BackupJob;
use Cron\CronExpression;
use Illuminate\Console\Command;

/**
 * Dispatches every enabled backup job whose cron schedule is due now. Wired to
 * run every minute by the scheduler, so each job fires on its own cadence.
 */
class RunDueBackups extends Command
{
    protected $signature = 'backups:run-due';

    protected $description = 'Dispatch backup jobs whose cron schedule is due';

    public function handle(): int
    {
        $dispatched = 0;
        foreach (BackupJob::where('enabled', true)->get() as $job) {
            try {
                if (CronExpression::factory($job->cron)->isDue()) {
                    RunBackupJob::dispatch($job->id);
                    $dispatched++;
                }
            } catch (\Throwable $e) {
                $this->warn("Skipping job #{$job->id}: {$e->getMessage()}");
            }
        }

        $this->info("Dispatched {$dispatched} backup job(s).");

        return self::SUCCESS;
    }
}
