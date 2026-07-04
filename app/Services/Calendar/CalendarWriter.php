<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Services\Contacts\DavChangeLog;
use Illuminate\Support\Str;

/**
 * Writes events from the web UI: builds the VEVENT ICS, keeps the denormalised
 * columns in sync, and bumps the calendar's DAV sync token + change log so
 * CalDAV clients see edits (mirrors ContactWriter).
 */
class CalendarWriter
{
    public function __construct(
        private readonly ICalService $ical,
        private readonly DavChangeLog $changes,
        private readonly CalendarObjectPersister $persister,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Calendar $calendar, array $data): CalendarObject
    {
        $ics = $this->ical->buildEvent($data);

        return $this->persister->persistNew($calendar, Str::uuid().'.ics', $ics);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CalendarObject $object, array $data): CalendarObject
    {
        $ics = $this->ical->buildEvent($data, $this->ical->uid($object->ics));

        return $this->persister->persistUpdate($object, $ics);
    }

    public function delete(CalendarObject $object): void
    {
        $calendar = $object->calendar;
        $uri = $object->uri;
        $object->delete();
        $this->changes->recordCalendar($calendar, $uri, DavChangeOperation::Deleted);
    }
}
