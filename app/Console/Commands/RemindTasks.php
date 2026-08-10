<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\CalendarTodo;
use App\Services\Calendar\CalendarTodoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Creates notification-centre rows for tasks (VTODO) that are due today or overdue
 * and not yet completed — which then fan out to push automatically via the
 * AppNotification::record choke point (SendPushJob). Runs daily.
 *
 * Throttled once per task per due-date via the cache (VTODO has no reminded_at
 * column): re-arms when the due date moves. Best-effort per task. `--lead=N`
 * also reminds about tasks due within the next N days.
 */
class RemindTasks extends Command
{
    protected $signature = 'tasks:remind {--lead=0 : Also remind about tasks due within this many days}';

    protected $description = 'Notify the owner about tasks due today or overdue';

    public function handle(): int
    {
        $today = Carbon::today();
        $lead = max(0, (int) $this->option('lead'));
        $horizon = $today->copy()->addDays($lead)->endOfDay();

        $due = CalendarTodo::query()
            ->whereNotNull('due')
            ->where('due', '<=', $horizon)
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->get();

        $sent = 0;
        foreach ($due as $todo) {
            if ($this->remind($todo)) {
                $sent++;
            }
        }

        $this->info($sent.' task reminder(s) created.');

        return self::SUCCESS;
    }

    private function remind(CalendarTodo $todo): bool
    {
        $due = $todo->due;
        if ($due === null) {
            return false;
        }
        // Skip tasks that carry a per-task reminder (VALARM) — the precise
        // `tasks:remind-alarms` command already covers them; avoid a double push.
        if (app(CalendarTodoService::class)->alarmMinutes($todo->ics) !== null) {
            return false;
        }
        // One reminder per task per due-date; re-arms if the due date changes.
        $key = 'tasks:remind:'.$todo->id.':'.$due->toDateString();
        if (! Cache::add($key, 1, now()->addHours(25))) {
            return false;
        }

        try {
            $title = __('notifications.task_due', ['task' => $todo->summary ?? '—']);
            $body = $due->isPast() && ! $due->isToday()
                ? __('notifications.task_overdue_since', ['date' => $due->toDateString()])
                : __('notifications.task_due_on', ['date' => $due->toDateString()]);
            AppNotification::record((int) $todo->user_id, 'info', $title, $body, 'task');

            return true;
        } catch (\Throwable) {
            // Best-effort per task — never stop the rest.
            Cache::forget($key);

            return false;
        }
    }
}
