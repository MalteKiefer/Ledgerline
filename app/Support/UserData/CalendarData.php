<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Per-user data contributor for the calendar module (CalDAV): exports and erases
 * a user's calendars together with their stored objects (VEVENT/VTODO) and their
 * subscriptions. Calendars own their data via the `user_id` column; objects hang
 * off a calendar by `calendar_id` and are scoped transitively through it.
 *
 * Subscriptions are not a separate table — a subscription is a calendar row with
 * a `subscription_url` — so they travel with the calendars. Calendar reminders
 * are the VALARM baked into each object's raw ICS (denormalised to `alarm_minutes`),
 * not standalone rows; the `reminders` table belongs to the to-dos module. Virtual
 * calendars (tasks/contact-derived/holidays) generate their objects on the fly and
 * are not stored in `calendar_objects`, so only real stored rows are exported.
 */
final class CalendarData implements UserDataContributor
{
    public function key(): string
    {
        return 'calendar';
    }

    public function export(User $user): array
    {
        return Calendar::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get()
            ->map(function (Calendar $calendar): array {
                $row = $calendar->attributesToArray();

                $row['objects'] = CalendarObject::query()
                    ->withoutGlobalScopes()
                    ->where('calendar_id', $calendar->getKey())
                    ->orderBy('id')
                    ->get()
                    ->map(fn (CalendarObject $object): array => $object->attributesToArray())
                    ->all();

                return $row;
            })
            ->all();
    }

    public function purge(User $user): void
    {
        $calendarIds = Calendar::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->pluck('id');

        if ($calendarIds->isEmpty()) {
            return;
        }

        // Children first (FK-safe): the sync change-log has no model, so delete
        // it directly; then the objects (VEVENT/VTODO); then the calendars.
        DB::table('calendar_changes')
            ->whereIn('calendar_id', $calendarIds)
            ->delete();

        CalendarObject::query()
            ->withoutGlobalScopes()
            ->whereIn('calendar_id', $calendarIds)
            ->delete();

        Calendar::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $calendarIds)
            ->delete();
    }
}
