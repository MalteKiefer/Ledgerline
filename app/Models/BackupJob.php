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
    'name', 'source', 'backup_destination_id', 'cron', 'retention',
    'encrypt', 'passphrase', 'notify', 'enabled',
])]
class BackupJob extends Model
{
    public const SOURCES = ['database', 'files', 'gallery'];

    public const NOTIFY_CHANNELS = ['none', 'ntfy', 'webhook', 'mail'];

    protected function casts(): array
    {
        return [
            'retention' => 'integer',
            'encrypt' => 'boolean',
            'passphrase' => 'encrypted',
            'enabled' => 'boolean',
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
        $runs = $this->runs()->get(['status', 'started_at', 'finished_at', 'bytes']);
        $ok = $runs->where('status', 'success');
        $failed = $runs->where('status', 'failed');
        $last = $runs->sortByDesc('started_at')->first();
        $lastOk = $ok->sortByDesc('started_at')->first();

        $durations = $ok->map(fn (BackupRun $r): ?int => $r->durationSeconds())->filter(fn (?int $d): bool => $d !== null);

        $nextRun = null;
        try {
            $nextRun = Carbon::instance(CronExpression::factory($this->cron)->getNextRunDate());
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
