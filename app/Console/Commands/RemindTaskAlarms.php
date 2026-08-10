<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\CalendarTodo;
use App\Services\Calendar\CalendarTodoService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Fires the per-task reminder (VTODO VALARM) — due minus alarm_minutes_before —
 * when its trigger falls in the current tick, creating a notification-centre row
 * (category=task) that fans out to push via the AppNotification::record choke point.
 *
 * Mirrors RemindCalendar for VTODOs. Runs every 5 min with a lookback window to
 * absorb scheduler drift; a per-(task, due) cache key dedups across overlapping
 * ticks and re-arms when the due date moves (recurring roll-forward). Distinct
 * from the date-level daily digest command `tasks:remind`.
 */
class RemindTaskAlarms extends Command
{
    protected $signature = 'tasks:remind-alarms {--lookback=10 : Minutes of overlap to absorb scheduler drift}';

    protected $description = 'Notify about tasks whose per-task reminder time has arrived';

    private const MAX_ALARM = 40320; // 4 weeks, matches the validation ceiling

    public function handle(CalendarTodoService $service): int
    {
        $now = CarbonImmutable::now()->utc();
        $lookback = $now->subMinutes(max(1, (int) $this->option('lookback')));

        $todos = CalendarTodo::query()
            ->whereNotNull('due')
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->get();

        $sent = 0;
        foreach ($todos as $todo) {
            if ($this->fire($todo, $service, $now, $lookback)) {
                $sent++;
            }
        }

        $this->info($sent.' task reminder(s) created.');

        return self::SUCCESS;
    }

    private function fire(CalendarTodo $todo, CalendarTodoService $service, CarbonImmutable $now, CarbonImmutable $lookback): bool
    {
        $due = $todo->due;
        if ($due === null) {
            return false;
        }
        $alarm = $service->alarmMinutes($todo->ics);
        if ($alarm === null || $alarm < 0 || $alarm > self::MAX_ALARM) {
            return false;
        }

        $dueUtc = CarbonImmutable::instance($due)->utc();
        $trigger = $dueUtc->subMinutes($alarm);
        // Only fire when the trigger crossed within this tick's window (lookback, now].
        if ($trigger->greaterThan($now) || $trigger->lessThanOrEqualTo($lookback)) {
            return false;
        }

        // One reminder per (task, due); re-arms when the due date moves.
        $key = 'tasks:remind-alarms:'.$todo->id.':'.$dueUtc->toIso8601ZuluString();
        if (! Cache::add($key, 1, now()->addDays(2))) {
            return false;
        }

        try {
            $summary = $todo->summary !== null && $todo->summary !== '' ? $todo->summary : '—';
            AppNotification::record(
                $todo->user_id,
                'info',
                __('notifications.task_due', ['task' => $summary]),
                __('notifications.task_reminder_at', ['time' => $dueUtc->toDateTimeString()]),
                'task',
            );

            return true;
        } catch (\Throwable) {
            Cache::forget($key);

            return false;
        }
    }
}
