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
        $existing = $this->events->parse($event->ics);
        $uid = $existing['uid'] ?? null;
        $sequence = (int) $event->sequence + 1;
        $ics = $this->events->build($data, is_string($uid) ? $uid : null, $sequence);

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
