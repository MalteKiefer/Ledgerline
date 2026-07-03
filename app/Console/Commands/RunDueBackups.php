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
        // Evaluate cron against the app timezone so "0 3 * * *" means 03:00 local,
        // not 03:00 in the server's default timezone.
        $now = now(config('app.timezone'));
        foreach (BackupJob::where('enabled', true)->get() as $job) {
            try {
                if (CronExpression::factory($job->cron)->isDue($now)) {
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
