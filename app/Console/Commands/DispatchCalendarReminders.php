<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CalendarReminder;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Fires due calendar reminders. Zero-knowledge: the queued rows carry only an
 * opaque event id + an absolute fire time (no title/content), so the pushed
 * notification is GENERIC — the server never learns what the reminder is for.
 * `desktop` records the per-user in-app bell; `ntfy` (if the workspace configured
 * it) is the works-when-closed phone push. Delivered rows are pruned after a week.
 */
class DispatchCalendarReminders extends Command
{
    protected $signature = 'calendar:dispatch-reminders';

    protected $description = 'Push generic notifications for due calendar reminders';

    public function handle(ChannelNotifier $notifier): int
    {
        $now = Carbon::now();

        $due = CalendarReminder::query()
            ->whereNull('delivered_at')
            ->where('remind_at', '<=', $now)
            ->orderBy('remind_at')
            ->limit(500)
            ->get();

        foreach ($due as $reminder) {
            $notifier->send(['desktop', 'ntfy'], 'Calendar reminder', 'You have an upcoming event.', [
                'user_id' => $reminder->user_id,
                'category' => 'calendar',
                'event' => 'calendar_reminder',
                'url' => route('calendar.index'),
            ]);
            $reminder->forceFill(['delivered_at' => $now])->save();
        }

        // Prune delivered rows older than a week (append-only queue, not history).
        CalendarReminder::query()
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<', $now->copy()->subDays(7))
            ->delete();

        $this->info("Dispatched {$due->count()} calendar reminder(s).");

        return self::SUCCESS;
    }
}
