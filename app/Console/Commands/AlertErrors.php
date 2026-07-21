<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppSettings;
use App\Models\ErrorEvent;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Notifies the configured channels when new server errors have been recorded
 * since the last alert. Keeps operators aware of faults without an external
 * error-tracking service. Runs hourly.
 *
 * Delivery state is tracked per error signature (error_events.alerted_at), not
 * via a cache cursor: a cache flush must not drop the alert window, and the
 * marker only advances AFTER a successful send so a failed delivery retries.
 */
class AlertErrors extends Command
{
    protected $signature = 'ops:alert-errors';

    protected $description = 'Alert configured channels about new recorded errors';

    public function handle(ChannelNotifier $notifier): int
    {
        if (! config('ops.error_alerts', true)) {
            return self::SUCCESS;
        }

        // Unresolved signatures never alerted, or seen again since their last
        // alert (a recurrence worth re-surfacing).
        $fresh = ErrorEvent::whereNull('resolved_at')
            ->where(function ($q): void {
                $q->whereNull('alerted_at')
                    ->orWhereColumn('last_seen_at', '>', 'alerted_at');
            })
            ->orderByDesc('last_seen_at')
            ->get();

        if ($fresh->isEmpty()) {
            $this->info('No new errors.');

            return self::SUCCESS;
        }

        $channels = $this->channels();
        if ($channels !== []) {
            $top = $fresh->take(5)
                ->map(fn (ErrorEvent $e): string => '• '.class_basename($e->exception).': '.Str::limit($e->message, 120))
                ->implode("\n");
            // send() swallows per-channel failures internally; only mark the
            // signatures alerted once we have attempted delivery to a channel.
            $notifier->send(
                $channels,
                __('settings.system_error_alert_title', ['count' => $fresh->count()]),
                $top,
                ['event' => 'error', 'priority' => 'high'],
            );
        }

        ErrorEvent::whereIn('id', $fresh->pluck('id'))->update(['alerted_at' => Carbon::now()]);

        $this->info($fresh->count().' new error(s) reported.');

        return self::SUCCESS;
    }

    /**
     * Globally enabled notification channels for ops alerts.
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
