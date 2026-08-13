<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\CalendarEvent;
use App\Services\Calendar\CalendarEventService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Creates notification-centre rows (category=event) when an event's reminder
 * (VALARM) trigger — start minus alarm_minutes_before — falls in the current
 * tick; they then fan out to push via the AppNotification::record choke point.
 *
 * Runs on a short cadence (every 5 min). A generous lookback window absorbs
 * scheduler drift; a per-(event, occurrence) cache key dedups so an overlapping
 * window never double-fires. Recurrences are expanded so each occurrence's own
 * reminder fires. Only events with a denormalised alarm are considered.
 */
class RemindCalendar extends Command
{
    protected $signature = 'calendar:remind {--lookback=10 : Minutes of overlap to absorb scheduler drift}';

    protected $description = 'Notify about events whose reminder time has arrived';

    private const MAX_ALARM = 40320; // 4 weeks, matches the validation ceiling

    public function handle(CalendarEventService $service): int
    {
        $now = CarbonImmutable::now()->utc();
        $lookback = $now->subMinutes(max(1, (int) $this->option('lookback')));

        $events = CalendarEvent::query()->whereNotNull('alarm_minutes_before')->with('calendar')->get();

        $sent = 0;
        foreach ($events as $event) {
            $alarm = (int) $event->alarm_minutes_before;
            $userId = $event->calendar?->user_id;
            if ($userId === null || $alarm < 0 || $alarm > self::MAX_ALARM) {
                continue;
            }
            // Occurrences whose start lies within the window whose trigger could
            // sit in (lookback, now]: from lookback (covers alarm=0) to now+alarm.
            $occurrences = $service->expand($event, $lookback, $now->addMinutes($alarm + 1));
            foreach ($occurrences as $occ) {
                if ($this->fire($event, $occ, $alarm, $now, $lookback, (int) $userId)) {
                    $sent++;
                }
            }
        }

        $this->info($sent.' event reminder(s) created.');

        return self::SUCCESS;
    }

    /**
     * @param  array{summary: ?string, start: string, ...}  $occ
     */
    private function fire(CalendarEvent $event, array $occ, int $alarm, CarbonImmutable $now, CarbonImmutable $lookback, int $userId): bool
    {
        try {
            $start = CarbonImmutable::parse($occ['start'])->utc();
        } catch (\Throwable) {
            return false;
        }
        $trigger = $start->subMinutes($alarm);
        // Only fire when the trigger crossed within this tick's window.
        if ($trigger->greaterThan($now) || $trigger->lessThanOrEqualTo($lookback)) {
            return false;
        }
        // One reminder per (event, occurrence) — dedups across overlapping ticks.
        $key = 'calendar:remind:'.$event->id.':'.$start->toIso8601ZuluString();
        if (! Cache::add($key, 1, now()->addDays(2))) {
            return false;
        }

        try {
            $summary = is_string($occ['summary'] ?? null) && $occ['summary'] !== '' ? $occ['summary'] : '—';
            AppNotification::record(
                $userId,
                'info',
                __('notifications.event_soon', ['event' => $summary]),
                __('notifications.event_at', ['time' => $start->format('H:i')]),
                'event',
            );

            return true;
        } catch (\Throwable) {
            Cache::forget($key);

            return false;
        }
    }
}
