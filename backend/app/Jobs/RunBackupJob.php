<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BackupJob;
use App\Services\Backup\BackupManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Runs a single backup job on the queue (dispatched by the scheduler when a
 * job's cron is due, or immediately from the "back up now" button).
 */
class RunBackupJob implements ShouldQueue
{
    use Queueable;

    /** The first mirror sync of a large blob set (e.g. a 40+ GB gallery over SFTP) can
     *  take many hours; give it generous room but never overlap the same job. It is
     *  resumable, so even a timeout here just continues next run — but 8h lets the
     *  initial full complete in one pass. Later runs are delta-only (minutes). */
    public int $timeout = 28800;

    public function __construct(public int $backupJobId) {}

    /**
     * Prevent the same job running concurrently (a slow queue + every-minute
     * cron could otherwise enqueue it twice). The lock auto-expires after the
     * timeout so a crashed worker can't wedge the job forever.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('backup-job-'.$this->backupJobId))->dontRelease()->expireAfter($this->timeout + 60)];
    }

    public function handle(BackupManager $manager): void
    {
        $job = BackupJob::find($this->backupJobId);
        if ($job === null) {
            return;
        }
        $manager->run($job);
    }
}
