<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppSettings;
use App\Models\ErrorEvent;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Notifies the configured channels when new server errors have been recorded
 * since the last alert. Keeps operators aware of faults without an external
 * error-tracking service. Runs hourly.
 */
class AlertErrors extends Command
{
    protected $signature = 'ops:alert-errors';

    protected $description = 'Alert configured channels about new recorded errors';

    private const CURSOR = 'ops:error-alert:since';

    public function handle(ChannelNotifier $notifier): int
    {
        if (! config('ops.error_alerts', true)) {
            return self::SUCCESS;
        }

        $since = Cache::get(self::CURSOR);
        $since = $since ? Carbon::parse($since) : Carbon::now()->subHour();

        $fresh = ErrorEvent::whereNull('resolved_at')
            ->where('last_seen_at', '>', $since)
            ->orderByDesc('last_seen_at')
            ->get();

        Cache::forever(self::CURSOR, Carbon::now()->toIso8601String());

        if ($fresh->isEmpty()) {
            $this->info('No new errors.');

            return self::SUCCESS;
        }

        $channels = $this->channels();
        if ($channels !== []) {
            $top = $fresh->take(5)
                ->map(fn (ErrorEvent $e): string => '• '.class_basename($e->exception).': '.Str::limit($e->message, 120))
                ->implode("\n");
            $notifier->send(
                $channels,
                __('settings.system_error_alert_title', ['count' => $fresh->count()]),
                $top,
                ['event' => 'error', 'priority' => 'high'],
            );
        }

        $this->info($fresh->count().' new error(s) reported.');

        return self::SUCCESS;
    }

    /** Globally enabled notification channels for ops alerts. */
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
