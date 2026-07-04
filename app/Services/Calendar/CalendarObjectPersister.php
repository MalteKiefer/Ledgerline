<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Services\Contacts\DavChangeLog;

/**
 * Persists a calendar object's ICS consistently (etag + denormalised columns +
 * calendar change-log), shared by the web controller, importer and CalDAV
 * backend (mirrors ContactPersister).
 */
class CalendarObjectPersister
{
    public function __construct(
        private readonly ICalService $ical,
        private readonly DavChangeLog $changes,
    ) {}

    public function persistNew(Calendar $calendar, string $uri, string $ics): CalendarObject
    {
        $object = CalendarObject::create(array_merge([
            'calendar_id' => $calendar->id,
            'uri' => $uri,
            'etag' => md5($ics),
            'ics' => $ics,
        ], $this->ical->denormalize($ics)));

        $this->changes->recordCalendar($calendar, $uri, DavChangeOperation::Added);

        return $object;
    }

    public function persistUpdate(CalendarObject $object, string $ics): CalendarObject
    {
        $object->forceFill(array_merge(['etag' => md5($ics), 'ics' => $ics], $this->ical->denormalize($ics)))->save();
        $this->changes->recordCalendar($object->calendar, $object->uri, DavChangeOperation::Modified);

        return $object;
    }
}
