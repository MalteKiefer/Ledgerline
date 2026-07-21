<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\AppNotification;
use App\Models\AppSettings;
use App\Models\BackupJob;
use App\Models\User;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Sends a backup success/failure notification over every channel selected for
 * the job (desktop/in-app bell, e-mail, NTFY, generic webhook). The actual
 * NTFY / webhook / SMTP transport lives in {@see ChannelNotifier}; this class
 * only shapes the backup-specific title, tags and webhook payload. One channel
 * failing never fails the backup or the others — failures are logged only.
 */
class BackupNotifier
{
    public function __construct(private readonly ChannelNotifier $channels) {}

    public function notify(BackupJob $job, bool $success, string $summary): void
    {
        $channels = $job->notify_channels ?? [];
        if ($channels === []) {
            return;
        }

        $settings = AppSettings::current();
        $title = sprintf('[Ledgerline] Backup %s: %s', $success ? 'OK' : 'FAILED', $job->name);

        foreach ($channels as $channel) {
            try {
                match ($channel) {
                    'desktop' => $this->desktop($job, $success, $summary),
                    'ntfy' => $this->channels->ntfy($settings, $title, $summary, [
                        'priority' => $success ? 'default' : 'high',
                        'tags' => $success ? 'white_check_mark' : 'rotating_light',
                    ]),
                    'webhook' => $this->channels->webhook($settings, [
                        'event' => 'backup',
                        'job' => $job->name,
                        'source' => $job->source,
                        'status' => $success ? 'success' : 'failed',
                        'message' => $summary,
                    ]),
                    'mail' => $this->channels->mail($settings, $title, $summary),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::warning('Backup notification failed', ['job' => $job->id, 'channel' => $channel, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Send a test message over one channel. Unlike notify(), this throws on any
     * failure (misconfiguration, unreachable server) so the caller can surface
     * the reason — it is triggered by an explicit "send test" action.
     */
    public function test(string $channel): void
    {
        $settings = AppSettings::current();
        $title = '[Ledgerline] Test notification';
        $body = 'This is a test message from Ledgerline. If you can read this, the channel works.';

        match ($channel) {
            'ntfy' => $this->channels->ntfy($settings, $title, $body, ['tags' => 'white_check_mark']),
            'webhook' => $this->channels->webhook($settings, [
                'event' => 'test',
                'status' => 'success',
                'message' => $body,
            ]),
            'mail' => $this->channels->mail($settings, $title, $body),
            default => throw new \InvalidArgumentException('Unknown notification channel.'),
        };
    }

    /** In-app bell notification — backups are workspace infra, so notify the admins. */
    private function desktop(BackupJob $job, bool $success, string $summary): void
    {
        $admins = User::query()->get()
            ->filter->managesGlobalSettings()
            ->map(function (User $u): int {
                $id = $u->getKey();

                return is_numeric($id) ? (int) $id : 0;
            })
            ->values();
        $success
            ? AppNotification::recordFor($admins, 'success', __('notifications.backup_ok', ['name' => $job->name]), $summary, 'backup')
            : AppNotification::recordFor($admins, 'error', __('notifications.backup_failed', ['name' => $job->name]), $summary, 'backup');
    }
}
