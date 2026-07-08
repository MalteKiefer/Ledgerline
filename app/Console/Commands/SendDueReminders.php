<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\Todo;
use App\Services\Notifications\ChannelNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Fires to-do reminders whose due time has passed and that have not been sent
 * yet. Scheduled every minute; each reminder fires once (fired_at is stamped).
 */
class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send to-do reminders that have come due';

    public function handle(ChannelNotifier $notifier): int
    {
        $due = Reminder::whereNull('fired_at')
            ->where('due_at', '<=', Carbon::now())
            ->get();

        foreach ($due as $reminder) {
            $when = $reminder->due_at?->timezone(config('app.timezone'))->format('Y-m-d H:i');
            // The in-app bell notification goes to the to-do's owner only.
            $ownerId = Todo::withoutGlobalScopes()->whereKey($reminder->todo_id)->value('user_id');
            // To-dos are zero-knowledge — the server can't read the title, so the
            // reminder is generic and links to the to-do list, not the task.
            $notifier->send(
                $reminder->channels ?? [],
                __('reminders.subject'),
                __('reminders.body', ['time' => $when]),
                ['url' => route('todos.index'), 'category' => 'reminder', 'priority' => 'high', 'user_id' => $ownerId],
            );
            $reminder->update(['fired_at' => Carbon::now()]);
        }

        $this->info('Sent '.$due->count().' reminder(s).');

        return self::SUCCESS;
    }
}
