<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One scheduled backup task: a source, a destination, a cron schedule, how many
 * versions to keep, optional archive encryption and a notification channel.
 */
#[Fillable([
    'name', 'source', 'mode', 'backup_destination_id', 'cron', 'retention',
    'encrypt', 'passphrase', 'notify_channels', 'enabled',
])]
class BackupJob extends Model
{
    public const SOURCES = ['database', 'files', 'gallery'];

    /** Backup mode for the file-based sources (database is always a full dump). */
    public const MODES = ['mirror', 'archive'];

    /** Notification channels a job may fire on completion (any combination). */
    public const NOTIFY_CHANNELS = ['desktop', 'mail', 'ntfy', 'webhook'];

    protected function casts(): array
    {
        return [
            'retention' => 'integer',
            'encrypt' => 'boolean',
            'passphrase' => 'encrypted',
            'enabled' => 'boolean',
            'notify_channels' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BackupDestination, $this> */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(BackupDestination::class, 'backup_destination_id');
    }

    /** @return HasMany<BackupRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(BackupRun::class);
    }

    /**
     * Aggregate run statistics for this job: counts, success rate, last/average
     * duration, last/total stored size, last run age and next scheduled run.
     *
     * @return array{runs:int, ok:int, failed:int, successRate:?int,
     *     lastStatus:?string, lastRun:?\Illuminate\Support\Carbon,
     *     lastDuration:?int, avgDuration:?int, lastBytes:?int, totalBytes:int,
     *     nextRun:?\Illuminate\Support\Carbon}
     */
    public function statistics(): array
    {
        // Use the loaded relation when eager-loaded (index page), else load once.
        $runs = $this->runs;
        $ok = $runs->where('status', 'success');
        $failed = $runs->where('status', 'failed');
        $last = $runs->sortByDesc('started_at')->first();
        $lastOk = $ok->sortByDesc('started_at')->first();

        $durations = $ok->map(fn (BackupRun $r): ?int => $r->durationSeconds())->filter(fn (?int $d): bool => $d !== null);

        $nextRun = null;
        try {
            // Match the scheduler: compute the next run in the app timezone.
            $tz = config('app.timezone');
            $nextRun = Carbon::instance(CronExpression::factory($this->cron)->getNextRunDate(now($tz), 0, false, $tz));
        } catch (\Throwable) {
        }

        return [
            'runs' => $runs->count(),
            'ok' => $ok->count(),
            'failed' => $failed->count(),
            'successRate' => $runs->count() > 0 ? (int) round($ok->count() / $runs->count() * 100) : null,
            'lastStatus' => $last?->status,
            'lastRun' => $last?->started_at,
            'lastDuration' => $lastOk?->durationSeconds(),
            'avgDuration' => $durations->isNotEmpty() ? (int) round($durations->avg()) : null,
            'lastBytes' => $lastOk?->bytes,
            'totalBytes' => (int) $ok->sum('bytes'),
            'nextRun' => $nextRun,
        ];
    }
}
