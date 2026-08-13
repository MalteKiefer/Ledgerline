<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\CalendarTodo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Per-user data contributor for the plaintext-relational Calendar / CalDAV module.
 * Calendars own their data via the `user_id` column; events hang off a calendar by
 * `calendar_id` and are scoped transitively through it (identical to Contact →
 * AddressBook). The iCalendar (VEVENT) is the authoritative payload and is embedded
 * inline in the event's `ics` column, so there are no separate file blobs to move
 * or delete.
 *
 * Erasure already succeeds via FK cascade (calendars cascade off users.id, events
 * and calendar_changes cascade off calendar_id), but — like ContactsData — this
 * purge deletes the rows explicitly so account erasure does not lean on cascade
 * behaviour. Export inventories the calendars + their events for GDPR Art.15.
 */
final class CalendarData implements UserDataContributor
{
    public function key(): string
    {
        return 'calendar';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $calendars = Calendar::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (Calendar $cal): array => $cal->attributesToArray())
            ->all();

        $calendarIds = array_column($calendars, 'id');

        $events = $calendarIds === []
            ? []
            : CalendarEvent::query()
                ->whereIn('calendar_id', $calendarIds)
                ->orderBy('id')
                ->get([
                    'id', 'calendar_id', 'uri', 'uid', 'component', 'ics',
                    'summary', 'description', 'location', 'dtstart', 'dtend',
                    'all_day', 'rrule', 'recurrence_until', 'status', 'sequence', 'updated_at',
                ])
                ->map(fn (CalendarEvent $event): array => $event->attributesToArray())
                ->all();

        $todos = CalendarTodo::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (CalendarTodo $todo): array => $todo->attributesToArray())
            ->all();

        return [
            'calendars' => $calendars,
            'events' => $events,
            'todos' => $todos,
        ];
    }

    public function purge(User $user): void
    {
        $calendarIds = Calendar::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->pluck('id');

        if ($calendarIds->isNotEmpty()) {
            // Sync change-log tombstones, events and tasks sit below calendars in FK
            // terms; all cascade at the DB level, but we clear them explicitly so the
            // erasure does not depend on cascade behaviour.
            DB::table('calendar_changes')->whereIn('calendar_id', $calendarIds)->delete();
            DB::table('calendar_todo_changes')->whereIn('calendar_id', $calendarIds)->delete();

            CalendarEvent::query()
                ->whereIn('calendar_id', $calendarIds)
                ->delete();

            CalendarTodo::query()
                ->withoutGlobalScopes()
                ->whereIn('calendar_id', $calendarIds)
                ->delete();

            Calendar::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $calendarIds)
                ->delete();
        }
    }
}
