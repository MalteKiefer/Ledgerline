<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use Illuminate\Support\Str;

/**
 * Writes events from the web UI: builds the ICS, keeps the denormalised columns
 * in sync, and bumps the calendar's DAV sync token + change log so CalDAV clients
 * see edits. All web-side writes go through this so ICS + denormalised columns +
 * sync token never drift. Mirrors ContactWriter.
 */
class CalendarWriter
{
    public function __construct(
        private readonly CalendarEventService $events,
        private readonly CalendarChangeLog $changes,
        private readonly CalendarEventPersister $persister,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Calendar $calendar, array $data): CalendarEvent
    {
        $ics = $this->events->build($data);

        return $this->persister->persistNew($calendar, Str::uuid().'.ics', $ics);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CalendarEvent $event, array $data): CalendarEvent
    {
        // Merge into the stored ICS so unmodelled properties (VALARM/ATTENDEE/
        // CATEGORIES/EXDATE/…) and the event's UID survive the edit.
        $sequence = (int) $event->sequence + 1;
        $ics = $this->events->rebuild($event->ics, $data, $sequence);

        return $this->persister->persistUpdate($event, $ics);
    }

    /** Delete a single occurrence of a recurring event (EXDATE on the master). */
    public function excludeOccurrence(CalendarEvent $event, string $occurrenceStart): CalendarEvent
    {
        $ics = $this->events->excludeOccurrence($event->ics, $occurrenceStart, (int) $event->sequence + 1);

        return $this->persister->persistUpdate($event, $ics);
    }

    /**
     * Edit a single occurrence of a recurring event (detached RECURRENCE-ID override).
     *
     * @param  array<string, mixed>  $data
     */
    public function overrideOccurrence(CalendarEvent $event, string $recurrenceId, array $data): CalendarEvent
    {
        $ics = $this->events->overrideOccurrence($event->ics, $recurrenceId, $data, (int) $event->sequence + 1);

        return $this->persister->persistUpdate($event, $ics);
    }

    public function delete(CalendarEvent $event): void
    {
        $calendar = $event->calendar;
        if ($calendar === null) {
            $event->delete();

            return;
        }
        $uri = $event->uri;
        $event->delete();
        $this->changes->record($calendar, $uri, DavChangeOperation::Deleted);
    }
}
