<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppSettings;
use App\Models\CalendarObject;
use App\Models\UserSetting;
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
        // Channels are workspace infra; the reminder time is rendered in each
        // event owner's own pinned calendar timezone.
        $channels = $this->channels(AppSettings::current());
        $sent = 0;

        // Only the owner's own events raise local alarms — never read-only
        // subscriptions or generated (holiday/derived) calendars.
        $objects = CalendarObject::whereNotNull('alarm_minutes')
            ->where('component', 'VEVENT')
            // Recurring events always considered; one-off events only while their
            // start is still within the look-back window (older can't re-fire).
            ->where(fn ($q) => $q->whereNotNull('rrule')->orWhere('starts_at', '>=', (clone $now)->subDays(2)))
            ->whereHas('calendar', fn ($q) => $q->where('read_only', false)->whereNull('subscription_url'))
            ->get();

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

                $ownerId = (int) $object->calendar->user_id;
                $ownerTz = UserSetting::for($ownerId)->calendar_timezone ?: config('app.timezone');
                $when = $start->timezone($ownerTz)->format('Y-m-d H:i');
                $notifier->send(
                    $channels,
                    (string) ($object->summary ?: __('calendar.ui.new_event')),
                    __('reminders.body', ['time' => $when]),
                    ['category' => 'reminder', 'priority' => 'high', 'user_id' => $ownerId],
                );
                $sent++;
            }
        }

        // Prune log rows past the look-back window: the fire window only reaches
        // one day back, so older rows can never be re-matched.
        DB::table('calendar_alarm_log')->where('fired_at', '<', (clone $now)->subDays(2))->delete();

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
