<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppSettings;
use App\Models\CalendarObject;
use App\Services\Calendar\ICalService;
use App\Services\Notifications\ChannelNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fires calendar event alarms (VALARM) that have come due. Recurring events fire
 * once per instance: (object, occurrence) pairs are logged in calendar_alarm_log.
 * Scheduled every minute. Alarms older than a day are skipped (no backfill spam).
 */
class SendDueCalendarAlarms extends Command
{
    protected $signature = 'calendar:remind';

    protected $description = 'Send calendar event alarms (VALARM) that have come due';

    public function handle(ICalService $ical, ChannelNotifier $notifier): int
    {
        $now = Carbon::now();
        $channels = $this->channels(AppSettings::current());
        $sent = 0;

        $objects = CalendarObject::whereNotNull('alarm_minutes')->where('component', 'VEVENT')->get();

        foreach ($objects as $object) {
            $lead = (int) $object->alarm_minutes;
            $windowStart = (clone $now)->subDay();
            $windowEnd = (clone $now)->addMinutes($lead);

            foreach ($ical->expand($object->ics, $windowStart, $windowEnd) as $instance) {
                $start = Carbon::parse($instance['start']);
                $fireAt = (clone $start)->subMinutes($lead);

                // Only alarms whose time has just passed (not future, not ancient).
                if ($fireAt->greaterThan($now) || $fireAt->lessThan((clone $now)->subDay())) {
                    continue;
                }

                // Fire once per (object, occurrence). The unique index makes the
                // insert the dedup gate under concurrent runs.
                $inserted = DB::table('calendar_alarm_log')->insertOrIgnore([
                    'calendar_object_id' => $object->id,
                    'occurrence_at' => $start->format('Y-m-d H:i:s'),
                    'fired_at' => $now->format('Y-m-d H:i:s'),
                ]);
                if ($inserted === 0) {
                    continue;
                }

                $when = $start->timezone(config('app.timezone'))->format('Y-m-d H:i');
                $notifier->send(
                    $channels,
                    (string) ($object->summary ?: __('calendar.ui.new_event')),
                    __('reminders.body', ['time' => $when]),
                    ['category' => 'reminder', 'priority' => 'high'],
                );
                $sent++;
            }
        }

        $this->info('Sent '.$sent.' calendar alarm(s).');

        return self::SUCCESS;
    }

    /**
     * Channels enabled globally. Desktop (in-app bell) is always on; the rest
     * follow the notification settings.
     *
     * @return list<string>
     */
    private function channels(AppSettings $s): array
    {
        return array_values(array_filter([
            'desktop',
            $s->ntfy_enabled ? 'ntfy' : null,
            $s->webhook_enabled ? 'webhook' : null,
            $s->mail_enabled ? 'mail' : null,
        ]));
    }
}
