<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppSettings;
use App\Models\BackupJob;
use App\Models\BackupRun;
use App\Services\Backup\BackupVerifier;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Periodic backup assurance: verify the latest successful run of every job is
 * actually restorable, and flag staleness (no recent successful backup). Alerts
 * the configured channels on any failure so a silently-broken backup surfaces
 * before it is needed. Runs daily.
 */
class VerifyLatestBackup extends Command
{
    protected $signature = 'backups:verify';

    protected $description = 'Verify the latest backup restores and alert on failure or staleness';

    public function handle(BackupVerifier $verifier, ChannelNotifier $notifier): int
    {
        $failures = [];

        foreach (BackupJob::all() as $job) {
            $run = $job->runs()->where('status', 'success')->orderByDesc('finished_at')->first();
            if ($run === null) {
                continue;
            }
            $result = $verifier->verify($run, $job->effectivePassphrase());
            if (! ($result['ok'] ?? false)) {
                $failures[] = ($job->name ?: 'backup').': '.($result['message'] ?? 'verification failed');
            }
        }

        $stale = $this->stalenessMessage();

        if ($failures === [] && $stale === null) {
            $this->info('Backups verified OK.');

            return self::SUCCESS;
        }

        $body = trim(($stale ?? '')."\n".implode("\n", array_map(static fn (string $f): string => '• '.$f, $failures)));
        $this->warn($body);

        $channels = $this->channels();
        if ($channels !== []) {
            $notifier->send(
                $channels,
                __('settings.backup_verify_alert_title'),
                $body,
                ['event' => 'backup', 'priority' => 'high'],
            );
        }

        return self::SUCCESS;
    }

    /** A staleness message when the newest successful backup is too old, else null. */
    private function stalenessMessage(): ?string
    {
        $hours = (int) config('ops.backup_stale_hours', 48);
        if ($hours <= 0) {
            return null;
        }

        $last = BackupRun::where('status', 'success')->max('finished_at');
        if ($last === null) {
            return __('settings.backup_verify_none');
        }
        $at = Carbon::parse($last);
        if ($at->lt(Carbon::now()->subHours($hours))) {
            return __('settings.backup_verify_stale', ['ago' => $at->diffForHumans()]);
        }

        return null;
    }

    /**
     * Globally enabled notification channels.
     *
     * @return list<string>
     */
    private function channels(): array
    {
        $s = AppSettings::current();

        return array_values(array_filter([
            $s->ntfy_enabled ? 'ntfy' : null,
            $s->webhook_enabled ? 'webhook' : null,
            $s->mail_enabled ? 'mail' : null,
        ]));
    }
}
