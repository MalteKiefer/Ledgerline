<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Reminder;
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
            $notifier->send(
                $reminder->channels ?? [],
                $reminder->title,
                trim(__('reminders.body', ['time' => $when]).($reminder->url ? "\n".$reminder->url : '')),
                ['url' => $reminder->url, 'category' => 'reminder', 'priority' => 'high'],
            );
            $reminder->update(['fired_at' => Carbon::now()]);
        }

        $this->info('Sent '.$due->count().' reminder(s).');

        return self::SUCCESS;
    }
}
