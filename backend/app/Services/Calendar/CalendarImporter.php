<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;
use Throwable;

/**
 * Imports an .ics file (one or many VEVENTs) into a calendar. Each event is
 * wrapped into its own VCALENDAR, deduped by UID (update in place), and persisted
 * through the shared persister so the sync log stays consistent. Malformed events
 * are skipped, not fatal. Mirrors ContactImporter.
 */
class CalendarImporter
{
    public function __construct(
        private readonly CalendarEventService $events,
        private readonly CalendarEventPersister $persister,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(Calendar $calendar, string $ics): array
    {
        // Suppress per-save side effects during the bulk loop.
        return CalendarEvent::withoutEvents(fn (): array => $this->importEvents($calendar, $ics));
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    private function importEvents(Calendar $calendar, string $ics): array
    {
        $created = $updated = $skipped = 0;

        // The source calendar declares its VTIMEZONEs once at the top level; keep
        // them so each extracted event can carry the definitions its TZIDs need.
        $zones = $this->events->timezones($ics);

        foreach ($this->events->parseCalendarStream($ics) as $vevent) {
            try {
                $rawUid = $vevent->UID ?? null;
                $uid = is_scalar($rawUid) || $rawUid instanceof \Stringable ? trim((string) $rawUid) : '';
                if ($uid === '') {
                    $uid = (string) Str::uuid();
                    $vevent->remove('UID');
                    $vevent->add('UID', $uid);
                }

                $wrapper = new VCalendar;
                foreach ($this->events->referencedTzids($vevent) as $tzid) {
                    if (isset($zones[$tzid])) {
                        $wrapper->add(clone $zones[$tzid]);
                    }
                }
                $wrapper->add(clone $vevent);
                $serialized = $wrapper->serialize();
                $eventIcs = is_string($serialized) ? $serialized : '';

                $existing = CalendarEvent::where('calendar_id', $calendar->id)->where('uid', $uid)->first();
                if ($existing !== null) {
                    $this->persister->persistUpdate($existing, $eventIcs);
                    $updated++;
                } else {
                    $this->persister->persistNew($calendar, Str::uuid().'.ics', $eventIcs);
                    $created++;
                }
            } catch (Throwable) {
                $skipped++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
