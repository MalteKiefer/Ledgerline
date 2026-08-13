<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use App\Models\CalendarEvent;

/**
 * Persists an event's ICS consistently: the etag + denormalised columns are
 * derived the same way everywhere, and the matching CalDAV change is logged.
 * Shared by the web writer, the importer and the CalDAV backend so persistence
 * and the sync-collection log never drift apart. Mirrors ContactPersister.
 */
class CalendarEventPersister
{
    public function __construct(
        private readonly CalendarEventService $events,
        private readonly CalendarChangeLog $changes,
    ) {}

    public function persistNew(Calendar $calendar, string $uri, string $ics): CalendarEvent
    {
        $event = CalendarEvent::create(array_merge([
            'calendar_id' => $calendar->id,
            'uri' => $uri,
            'etag' => md5($ics),
            'component' => 'VEVENT',
            'ics' => $ics,
        ], $this->events->denormalize($ics)));

        $this->changes->record($calendar, $uri, DavChangeOperation::Added);

        return $event;
    }

    public function persistUpdate(CalendarEvent $event, string $ics): CalendarEvent
    {
        $event->forceFill(array_merge(
            ['etag' => md5($ics), 'ics' => $ics],
            $this->events->denormalize($ics),
        ))->save();

        // Explicit relation query — never an implicit lazy load, which is
        // disabled app-wide and would throw during a bulk import.
        $calendar = $event->calendar()->first();
        if ($calendar !== null) {
            $this->changes->record($calendar, $event->uri, DavChangeOperation::Modified);
        }

        return $event;
    }
}
