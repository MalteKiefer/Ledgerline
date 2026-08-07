<?php

declare(strict_types=1);

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One scheduled backup task: a source, a destination, a cron schedule, how many
 * versions to keep, optional archive encryption and a notification channel.
 *
 * @property Carbon|null $last_run_at
 * @property list<string>|null $notify_channels
 */
#[Fillable([
    'name', 'source', 'sources', 'mode', 'backup_destination_id', 'cron', 'retention',
    'keep_daily', 'keep_weekly', 'keep_monthly',
    'encrypt', 'passphrase', 'notify_channels', 'enabled',
])]
#[Hidden(['passphrase'])] // archive encryption passphrase — never serialize
class BackupJob extends Model
{
    public const SOURCES = ['database', 'invoices', 'files'];

    public const MODES = ['full', 'incremental'];

    /** Blob (disk-prefix) sources support incremental mode; the DB dump is always full. */
    public const INCREMENTAL_SOURCES = ['invoices', 'files'];

    /** Notification channels a job may fire on completion (any combination). */
    public const NOTIFY_CHANNELS = ['desktop', 'mail', 'ntfy', 'webhook'];

    protected function casts(): array
    {
        return [
            'retention' => 'integer',
            'sources' => 'array',
            'keep_daily' => 'integer',
            'keep_weekly' => 'integer',
            'keep_monthly' => 'integer',
            'encrypt' => 'boolean',
            'passphrase' => 'encrypted',
            'enabled' => 'boolean',
            'notify_channels' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * The sources this job backs up — the multi-select list, falling back to the
     * legacy single `source` column. Only known sources.
     *
     * @return list<string>
     */
    public function effectiveSources(): array
    {
        $list = is_array($this->sources) && $this->sources !== []
            ? $this->sources
            : [$this->source];
        $valid = array_values(array_filter($list, fn ($s): bool => is_string($s) && in_array($s, self::SOURCES, true)));

        return $valid !== [] ? array_values(array_unique($valid)) : ['database'];
    }

    /**
     * GFS retention tiers (son/father/grandfather). Falls back to the flat
     * `retention` as keep_daily when the tiers are unset.
     *
     * @return array{daily: int, weekly: int, monthly: int}
     */
    public function retentionTiers(): array
    {
        $daily = $this->keep_daily ?? $this->retention ?? 7;

        return [
            'daily' => max(0, (int) $daily),
            'weekly' => max(0, (int) ($this->keep_weekly ?? 0)),
            'monthly' => max(0, (int) ($this->keep_monthly ?? 0)),
        ];
    }

    /**
     * The passphrase actually used to encrypt this job's archives: the
     * environment-provided one (config/backup.php → BACKUP_PASSPHRASE) wins so the
     * key can live outside the database that gets dumped into the backups; the
     * per-job DB column is the legacy fallback.
     */
    public function effectivePassphrase(): ?string
    {
        $env = config('backup.passphrase', '');
        $env = is_string($env) ? $env : '';

        return $env !== '' ? $env : ($this->passphrase ?: null);
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
     *     lastStatus:?string, lastRun:?Carbon,
     *     lastDuration:?int, avgDuration:?int, lastBytes:?int, totalBytes:int,
     *     nextRun:?Carbon}
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
            $tz = is_string($tz) ? $tz : null;
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
            'avgDuration' => $durations->isNotEmpty() ? (int) round($durations->avg() ?? 0) : null,
            'lastBytes' => $lastOk?->bytes,
            'totalBytes' => (int) $ok->sum(fn (BackupRun $r): int => (int) $r->bytes),
            'nextRun' => $nextRun,
        ];
    }
}
