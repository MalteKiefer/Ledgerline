<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property;
use Sabre\VObject\Property\ICalendar\DateTime as DateTimeProperty;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;
use Throwable;

/**
 * Builds and parses iCalendar (VCALENDAR/VEVENT). The raw ICS is the source of
 * truth; build() produces it from the editor's fields, parse() reads it back for
 * the editor, denormalize() mirrors a few fields into calendar_events columns for
 * list/range/search, and expand() unrolls recurrences into concrete occurrences.
 *
 * sabre/vobject is untyped (every property access is mixed), so reads funnel
 * through the small mixed-narrowing helpers below (str/s/dt) and Property/VEvent
 * instances are confirmed with instanceof before use — same idiom as VCardService.
 *
 * All datetimes are stored/emitted UTC ('Z'); all-day events carry VALUE=DATE.
 */
class CalendarEventService
{
    /** Hard cap on recurrence expansion to bound unbounded RRULEs (DoS guard). */
    private const MAX_OCCURRENCES = 1000;

    /**
     * Build a VCALENDAR/VEVENT string from editor data. Reuses $uid on update so
     * the event keeps its identity for CalDAV clients.
     *
     * @param  array<string, mixed>  $data  {summary,description,location,dtstart,dtend,all_day,rrule,status}
     */
    public function build(array $data, ?string $uid = null, int $sequence = 0): string
    {
        $uidValue = $uid !== null && $uid !== '' ? $uid : (string) Str::uuid();
        $allDay = (bool) ($data['all_day'] ?? false);

        $cal = new VCalendar;
        /** @var VEvent $event */
        $event = $cal->add('VEVENT', []);
        // A fresh VEVENT auto-populates UID + DTSTAMP; remove-then-add so we
        // replace those single-valued properties instead of duplicating them.
        $event->remove('UID');
        $event->add('UID', $uidValue);
        $event->remove('DTSTAMP');
        $event->add('DTSTAMP', gmdate('Ymd\THis\Z'));
        $event->add('SEQUENCE', (string) max(0, $sequence));

        $start = $this->parseDateTime($data['dtstart'] ?? null);
        if ($start !== null) {
            if ($allDay) {
                $event->add('DTSTART', $start->format('Ymd'), ['VALUE' => 'DATE']);
            } else {
                $event->add('DTSTART', $start->format('Ymd\THis\Z'));
            }
        }
        $end = $this->parseDateTime($data['dtend'] ?? null);
        if ($end !== null) {
            if ($allDay) {
                $event->add('DTEND', $end->format('Ymd'), ['VALUE' => 'DATE']);
            } else {
                $event->add('DTEND', $end->format('Ymd\THis\Z'));
            }
        }

        foreach (['summary' => 'SUMMARY', 'description' => 'DESCRIPTION', 'location' => 'LOCATION'] as $key => $prop) {
            if (filled($data[$key] ?? null)) {
                $event->add($prop, $this->str($data[$key] ?? null));
            }
        }

        $status = strtoupper(trim($this->str($data['status'] ?? null)));
        if (in_array($status, ['CONFIRMED', 'TENTATIVE', 'CANCELLED'], true)) {
            $event->add('STATUS', $status);
        }

        $rrule = trim($this->str($data['rrule'] ?? null));
        if ($rrule !== '') {
            $event->add('RRULE', $rrule);
        }

        $serialized = $cal->serialize();

        return is_string($serialized) ? $serialized : '';
    }

    /**
     * Parse a VCALENDAR into structured editor data (first VEVENT).
     *
     * @return array<string, mixed> {uid,summary,description,location,dtstart,dtend,all_day,rrule,status,sequence}
     */
    public function parse(string $ics): array
    {
        $event = $this->firstEvent($ics);
        if ($event === null) {
            return [
                'uid' => null, 'summary' => null, 'description' => null, 'location' => null,
                'dtstart' => null, 'dtend' => null, 'all_day' => false, 'rrule' => null,
                'status' => null, 'sequence' => 0,
            ];
        }

        $allDay = $this->isAllDay($event);

        return [
            'uid' => $this->s($event->UID ?? null),
            'summary' => $this->s($event->SUMMARY ?? null),
            'description' => $this->s($event->DESCRIPTION ?? null),
            'location' => $this->s($event->LOCATION ?? null),
            'dtstart' => $this->propIso($event->DTSTART ?? null, $allDay),
            'dtend' => $this->propIso($event->DTEND ?? null, $allDay),
            'all_day' => $allDay,
            'rrule' => $this->s($event->RRULE ?? null),
            'status' => $this->s($event->STATUS ?? null),
            'sequence' => (int) $this->str($event->SEQUENCE ?? null),
        ];
    }

    /**
     * Mirror a few fields into calendar_events columns for list/range/search.
     *
     * @return array{uid: ?string, summary: ?string, description: ?string, location: ?string,
     *   dtstart: ?CarbonImmutable, dtend: ?CarbonImmutable, all_day: bool, rrule: ?string,
     *   recurrence_until: ?CarbonImmutable, status: ?string, sequence: int}
     */
    public function denormalize(string $ics): array
    {
        $event = $this->firstEvent($ics);
        if ($event === null) {
            return [
                'uid' => null, 'summary' => null, 'description' => null, 'location' => null,
                'dtstart' => null, 'dtend' => null, 'all_day' => false, 'rrule' => null,
                'recurrence_until' => null, 'status' => null, 'sequence' => 0,
            ];
        }

        $rrule = $this->s($event->RRULE ?? null);

        return [
            'uid' => $this->s($event->UID ?? null),
            'summary' => $this->s($event->SUMMARY ?? null),
            'description' => $this->s($event->DESCRIPTION ?? null),
            'location' => $this->s($event->LOCATION ?? null),
            'dtstart' => $this->propDate($event->DTSTART ?? null),
            'dtend' => $this->propDate($event->DTEND ?? null),
            'all_day' => $this->isAllDay($event),
            'rrule' => $rrule,
            'recurrence_until' => $rrule !== null ? $this->recurrenceUntil($rrule) : null,
            'status' => $this->s($event->STATUS ?? null),
            'sequence' => (int) $this->str($event->SEQUENCE ?? null),
        ];
    }

    /**
     * Expand recurrences into concrete occurrences within [from,to]. Non-recurring
     * events pass through if they overlap the window.
     *
     * @return list<array{uid: string, summary: ?string, location: ?string, description: ?string,
     *   start: string, end: string, all_day: bool, status: ?string, recurring: bool}>
     */
    public function expand(CalendarEvent $event, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cal = $this->readCalendar($event->ics);
        $vevent = $this->firstEventOf($cal);
        if ($cal === null || $vevent === null) {
            return [];
        }

        $uid = $this->s($vevent->UID ?? null) ?? (string) $event->uid;
        $summary = $this->s($vevent->SUMMARY ?? null);
        $location = $this->s($vevent->LOCATION ?? null);
        $description = $this->s($vevent->DESCRIPTION ?? null);
        $status = $this->s($vevent->STATUS ?? null);
        $allDay = $this->isAllDay($vevent);

        // Single (non-recurring) event: emit once if it overlaps the window.
        if ($this->s($vevent->RRULE ?? null) === null) {
            $start = $this->propDate($vevent->DTSTART ?? null);
            if ($start === null) {
                return [];
            }
            $end = $this->propDate($vevent->DTEND ?? null) ?? $start;
            if ($start >= $to || $end <= $from) {
                return [];
            }

            return [[
                'uid' => $uid, 'summary' => $summary, 'location' => $location, 'description' => $description,
                'start' => $start->toIso8601ZuluString(), 'end' => $end->toIso8601ZuluString(),
                'all_day' => $allDay, 'status' => $status, 'recurring' => false,
            ]];
        }

        return $this->expandRecurring($cal, $uid, $from, $to, $summary, $location, $description, $status, $allDay);
    }

    /**
     * @return list<array{uid: string, summary: ?string, location: ?string, description: ?string,
     *   start: string, end: string, all_day: bool, status: ?string, recurring: bool}>
     */
    private function expandRecurring(
        VCalendar $cal,
        string $uid,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $summary,
        ?string $location,
        ?string $description,
        ?string $status,
        bool $allDay,
    ): array {
        try {
            $it = new EventIterator($cal, $uid);
            $it->fastForward($from);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        $count = 0;
        while ($it->valid() && $count < self::MAX_OCCURRENCES) {
            $start = $this->toCarbon($it->getDtStart());
            $end = $this->toCarbon($it->getDtEnd());
            if ($start === null) {
                break;
            }
            if ($start >= $to) {
                break;
            }
            $endUtc = $end ?? $start;
            if ($endUtc > $from) {
                $out[] = [
                    'uid' => $uid, 'summary' => $summary, 'location' => $location, 'description' => $description,
                    'start' => $start->toIso8601ZuluString(), 'end' => $endUtc->toIso8601ZuluString(),
                    'all_day' => $allDay, 'status' => $status, 'recurring' => true,
                ];
            }
            $count++;
            try {
                $it->next();
            } catch (Throwable) {
                break;
            }
        }

        return $out;
    }

    /**
     * Split a multi-VEVENT VCALENDAR (an .ics import) into individual VEVENTs.
     *
     * @return list<VEvent>
     */
    public function parseCalendarStream(string $ics): array
    {
        $cal = $this->readCalendar($ics);
        if ($cal === null) {
            return [];
        }
        $out = [];
        foreach ($this->iter($cal->select('VEVENT')) as $vevent) {
            if ($vevent instanceof VEvent) {
                $out[] = $vevent;
            }
        }

        return $out;
    }

    /** Read the calendar as a raw VCALENDAR (forgiving), or null when unreadable. */
    private function readCalendar(string $ics): ?VCalendar
    {
        try {
            $cal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return null;
        }

        return $cal instanceof VCalendar ? $cal : null;
    }

    private function firstEvent(string $ics): ?VEvent
    {
        return $this->firstEventOf($this->readCalendar($ics));
    }

    private function firstEventOf(?VCalendar $cal): ?VEvent
    {
        if ($cal === null) {
            return null;
        }
        foreach ($this->iter($cal->select('VEVENT')) as $vevent) {
            if ($vevent instanceof VEvent) {
                return $vevent;
            }
        }

        return null;
    }

    /** True when the property carries VALUE=DATE (an all-day event). */
    private function isAllDay(VEvent $event): bool
    {
        $prop = $event->DTSTART ?? null;
        if (! $prop instanceof Property) {
            return false;
        }
        $value = $prop['VALUE'] ?? null;

        return strtoupper($this->str($value)) === 'DATE';
    }

    /** Parse an editor-supplied datetime string into a UTC CarbonImmutable. */
    private function parseDateTime(mixed $value): ?CarbonImmutable
    {
        $raw = trim($this->str($value));
        if ($raw === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($raw)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /** A VObject DATE(-TIME) property as a UTC CarbonImmutable column value. */
    private function propDate(mixed $prop): ?CarbonImmutable
    {
        if (! $prop instanceof DateTimeProperty) {
            return null;
        }
        try {
            $dt = $prop->getDateTime();
        } catch (Throwable) {
            return null;
        }

        return $this->toCarbon($dt);
    }

    /** A VObject DATE(-TIME) property as an ISO-8601 editor string. */
    private function propIso(mixed $prop, bool $allDay): ?string
    {
        $carbon = $this->propDate($prop);
        if ($carbon === null) {
            return null;
        }

        return $allDay ? $carbon->format('Y-m-d') : $carbon->toIso8601ZuluString();
    }

    private function toCarbon(mixed $dt): ?CarbonImmutable
    {
        if (! $dt instanceof DateTimeInterface) {
            return null;
        }

        return CarbonImmutable::instance($dt)->utc();
    }

    /**
     * Coarse recurrence horizon for the range prefilter: the RRULE UNTIL when
     * present (null for COUNT/unbounded — exact filtering happens in expand()).
     */
    private function recurrenceUntil(string $rrule): ?CarbonImmutable
    {
        if (! preg_match('/UNTIL=([0-9TZ]+)/i', $rrule, $m)) {
            return null;
        }
        try {
            return CarbonImmutable::parse($m[1], new DateTimeZone('UTC'))->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /** Trimmed string or null. */
    private function s(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    /** Coerce any mixed to a string (empty for non-scalars). */
    private function str(mixed $v): string
    {
        return is_scalar($v) || $v instanceof \Stringable ? (string) $v : '';
    }

    /** @return iterable<mixed> */
    private function iter(mixed $v): iterable
    {
        return is_iterable($v) ? $v : [];
    }
}
