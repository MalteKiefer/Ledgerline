<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Notifications\ChannelNotifier;
use App\Services\Todos\TodoReminders;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Notifies each owner about their due to-dos over the configured channels
 * (in-app bell + any enabled ntfy/webhook/mail). Every reminded task is stamped
 * with `reminded_at` so a second run the same day does not re-notify; a moved
 * due date re-arms it (see TodoReminders::dueForReminder). Best-effort per
 * task — one failure never aborts the batch. Run frequently (e.g. hourly).
 */
class RemindTodos extends Command
{
    protected $signature = 'todos:remind';

    protected $description = 'Notify owners about due to-dos over their configured channels';

    public function handle(TodoReminders $reminders, ChannelNotifier $notifier): int
    {
        $due = $reminders->dueForReminder();
        if ($due->isEmpty()) {
            $this->info('No due to-dos to remind.');

            return self::SUCCESS;
        }

        $url = route('todos.index');
        $reminded = 0;

        foreach ($due as $todo) {
            try {
                $userId = (int) $todo->user_id;
                $channels = $reminders->channelsFor($userId);
                if ($channels !== []) {
                    $notifier->send(
                        $channels,
                        __('todos.reminder_title'),
                        __('todos.reminder_body', ['title' => $todo->title]),
                        [
                            'event' => 'reminder',
                            'category' => 'reminder',
                            'level' => 'info',
                            'priority' => 'high',
                            'user_id' => $userId,
                            'url' => $url,
                        ],
                    );
                }
                // Stamp regardless (server-only column) so the batch is idempotent;
                // saveQuietly avoids the version bump / any observer loop.
                $todo->forceFill(['reminded_at' => Carbon::now()])->saveQuietly();
                $reminded++;
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
        }

        $this->info($reminded.' to-do reminder(s) processed.');

        return self::SUCCESS;
    }
}
