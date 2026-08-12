<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VAlarm;
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

        $cal = new VCalendar;
        /** @var VEvent $event */
        $event = $cal->add('VEVENT', []);
        // A fresh VEVENT auto-populates UID + DTSTAMP; remove-then-add so we
        // replace those single-valued properties instead of duplicating them.
        $event->remove('UID');
        $event->add('UID', $uidValue);
        $this->applyCoreFields($event, $data, $sequence);

        $serialized = $cal->serialize();

        return is_string($serialized) ? $serialized : '';
    }

    /**
     * Update an existing VEVENT in place from editor data, preserving every
     * property the editor does not model (VALARM/ATTENDEE/ORGANIZER/CATEGORIES/
     * URL/EXDATE/RECURRENCE-ID/X-*) and the surrounding VCALENDAR (VTIMEZONE).
     * A fresh build() would drop all of those on every edit; this keeps them.
     * Falls back to build() when the stored ICS is unreadable.
     *
     * @param  array<string, mixed>  $data
     */
    public function rebuild(string $existingIcs, array $data, int $sequence = 0): string
    {
        $cal = $this->readCalendar($existingIcs);
        $event = $this->firstEventOf($cal);
        if ($cal === null || $event === null) {
            $uid = $this->parse($existingIcs)['uid'];

            return $this->build($data, is_string($uid) ? $uid : null, $sequence);
        }

        $this->applyCoreFields($event, $data, $sequence);

        $serialized = $cal->serialize();

        return is_string($serialized) ? $serialized : $this->build($data, $this->s($event->UID ?? null), $sequence);
    }

    /**
     * Exclude a single occurrence of a recurring event by adding an EXDATE to the
     * master VEVENT (the CalDAV-canonical way to delete one instance without
     * touching the series). Sabre's EventIterator honours EXDATE natively, so the
     * occurrence simply drops out of expand(). No-op if the ICS is unreadable.
     */
    public function excludeOccurrence(string $ics, string $occurrenceStart, int $sequence): string
    {
        $cal = $this->readCalendar($ics);
        $event = $this->firstEventOf($cal);
        $start = $this->parseDateTime($occurrenceStart);
        if ($cal === null || $event === null || $start === null) {
            return $ics;
        }
        $this->isAllDay($event)
            ? $event->add('EXDATE', $start->format('Ymd'), ['VALUE' => 'DATE'])
            : $event->add('EXDATE', $start->format('Ymd\THis\Z'));
        $this->bumpStamp($event, $sequence);

        $serialized = $cal->serialize();

        return is_string($serialized) ? $serialized : $ics;
    }

    /**
     * Override a single occurrence: add (or replace) a detached VEVENT that shares
     * the master's UID and carries a RECURRENCE-ID pointing at the original
     * occurrence start, with the edited fields. Sabre's EventIterator applies the
     * override in place of that instance. The override never carries the RRULE.
     *
     * @param  array<string, mixed>  $data
     */
    public function overrideOccurrence(string $ics, string $recurrenceId, array $data, int $sequence): string
    {
        $cal = $this->readCalendar($ics);
        $master = $this->firstEventOf($cal);
        $recur = $this->parseDateTime($recurrenceId);
        if ($cal === null || $master === null || $recur === null) {
            return $ics;
        }
        $uid = $this->s($master->UID ?? null) ?? '';
        $masterAllDay = $this->isAllDay($master);

        // Drop any existing override for the same instant so we replace it.
        foreach ($this->iter($cal->select('VEVENT')) as $vevent) {
            if ($vevent instanceof VEvent && $this->recurrenceInstant($vevent) === $recur->format('Ymd\THis')) {
                $cal->remove($vevent);
            }
        }

        /** @var VEvent $override */
        $override = $cal->add('VEVENT', []);
        $override->remove('UID');
        $override->add('UID', $uid);
        $masterAllDay
            ? $override->add('RECURRENCE-ID', $recur->format('Ymd'), ['VALUE' => 'DATE'])
            : $override->add('RECURRENCE-ID', $recur->format('Ymd\THis\Z'));
        $this->applyCoreFields($override, $data, $sequence);
        $override->remove('RRULE'); // an override is a single instance, never recurring

        $serialized = $cal->serialize();

        return is_string($serialized) ? $serialized : $ics;
    }

    /** DTSTAMP refresh + SEQUENCE bump shared by the occurrence mutators. */
    private function bumpStamp(VEvent $event, int $sequence): void
    {
        $event->remove('DTSTAMP');
        $event->add('DTSTAMP', gmdate('Ymd\THis\Z'));
        $event->remove('SEQUENCE');
        $event->add('SEQUENCE', (string) max(0, $sequence));
    }

    /** A VEVENT's RECURRENCE-ID as a 'YmdHis' UTC key, or null when it has none. */
    private function recurrenceInstant(VEvent $event): ?string
    {
        $prop = $event->{'RECURRENCE-ID'} ?? null;
        $carbon = $this->propDate($prop);

        return $carbon?->format('Ymd\THis');
    }

    /**
     * Set the editor-owned properties on a VEVENT (remove-then-add so an update
     * replaces rather than duplicates, and a cleared field is removed). Leaves
     * UID and any unmodelled property untouched.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyCoreFields(VEvent $event, array $data, int $sequence): void
    {
        $allDay = (bool) ($data['all_day'] ?? false);

        $event->remove('DTSTAMP');
        $event->add('DTSTAMP', gmdate('Ymd\THis\Z'));
        $event->remove('SEQUENCE');
        $event->add('SEQUENCE', (string) max(0, $sequence));

        $event->remove('DTSTART');
        $start = $this->parseDateTime($data['dtstart'] ?? null);
        if ($start !== null) {
            $allDay
                ? $event->add('DTSTART', $start->format('Ymd'), ['VALUE' => 'DATE'])
                : $event->add('DTSTART', $start->format('Ymd\THis\Z'));
        }

        $event->remove('DTEND');
        $end = $this->parseDateTime($data['dtend'] ?? null);
        if ($end !== null) {
            $allDay
                ? $event->add('DTEND', $end->format('Ymd'), ['VALUE' => 'DATE'])
                : $event->add('DTEND', $end->format('Ymd\THis\Z'));
        }

        foreach (['summary' => 'SUMMARY', 'description' => 'DESCRIPTION', 'location' => 'LOCATION'] as $key => $prop) {
            $event->remove($prop);
            if (filled($data[$key] ?? null)) {
                $event->add($prop, $this->str($data[$key] ?? null));
            }
        }

        // GEO carries the picked coordinate (RFC 5545 "GEO:lat;lon"); LOCATION
        // stays the human address. Emit only when BOTH are present + in range.
        $event->remove('GEO');
        $lat = $this->coord($data['geo_lat'] ?? null, 90.0);
        $lon = $this->coord($data['geo_lon'] ?? null, 180.0);
        if ($lat !== null && $lon !== null) {
            $event->add('GEO', $lat.';'.$lon);
        }

        $event->remove('STATUS');
        $status = strtoupper(trim($this->str($data['status'] ?? null)));
        if (in_array($status, ['CONFIRMED', 'TENTATIVE', 'CANCELLED'], true)) {
            $event->add('STATUS', $status);
        }

        $event->remove('RRULE');
        $rrule = trim($this->str($data['rrule'] ?? null));
        if ($rrule !== '') {
            $event->add('RRULE', $rrule);
        }

        // ORGANIZER + ATTENDEE (iMIP). Editor-owned: replace wholesale when the
        // request carries them, leave the ICS untouched otherwise (rebuild path).
        if (array_key_exists('organizer', $data) || array_key_exists('attendees', $data)) {
            $event->remove('ORGANIZER');
            $organizer = trim($this->str($data['organizer'] ?? null));
            if ($organizer !== '') {
                $event->add('ORGANIZER', 'mailto:'.$organizer);
            }
            $event->remove('ATTENDEE');
            $attendees = is_array($data['attendees'] ?? null) ? $data['attendees'] : [];
            foreach ($attendees as $a) {
                if (! is_array($a)) {
                    continue;
                }
                $email = trim($this->str($a['email'] ?? null));
                if ($email === '' || ! str_contains($email, '@')) {
                    continue;
                }
                $params = ['ROLE' => 'REQ-PARTICIPANT', 'RSVP' => 'TRUE', 'PARTSTAT' => $this->partstat($a['partstat'] ?? null)];
                $name = trim($this->str($a['name'] ?? null));
                if ($name !== '') {
                    $params['CN'] = $name;
                }
                $event->add('ATTENDEE', 'mailto:'.$email, $params);
            }
        }

        // VALARM is editor-owned (alarm_minutes_before → DISPLAY reminder N minutes
        // before start): replace it wholesale, drop it when cleared.
        $event->remove('VALARM');
        $alarm = $this->alarmMinutes($data['alarm_minutes_before'] ?? null);
        if ($alarm !== null) {
            /** @var VAlarm $valarm */
            $valarm = $event->add('VALARM', []);
            $valarm->add('ACTION', 'DISPLAY');
            $valarm->add('TRIGGER', '-PT'.$alarm.'M');
            $desc = $this->str($data['summary'] ?? null);
            $valarm->add('DESCRIPTION', $desc !== '' ? $desc : 'Reminder');
        }
    }

    /** A non-negative alarm-minutes value within a 4-week ceiling, else null. */
    private function alarmMinutes(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }
        $n = (int) $value;

        return $n >= 0 && $n <= 40320 ? $n : null;
    }

    /** Minutes-before-start of the first DISPLAY VALARM, or null. */
    private function parseAlarm(VEvent $event): ?int
    {
        foreach ($this->iter($event->select('VALARM')) as $alarm) {
            if (! $alarm instanceof VAlarm) {
                continue;
            }
            $trigger = $this->str($alarm->TRIGGER ?? null);
            if (! str_starts_with($trigger, '-P')) {
                continue;
            }
            $minutes = 0;
            if (preg_match('/(\d+)D/', $trigger, $m) === 1) {
                $minutes += (int) $m[1] * 1440;
            }
            if (preg_match('/T\D*(\d+)H/', $trigger, $m) === 1) {
                $minutes += (int) $m[1] * 60;
            }
            if (preg_match('/T.*?(\d+)M/', $trigger, $m) === 1) {
                $minutes += (int) $m[1];
            }

            return $minutes;
        }

        return null;
    }

    /**
     * Parse a VCALENDAR into structured editor data (first VEVENT).
     *
     * @return array<string, mixed> {uid,summary,description,location,geo_lat,geo_lon,dtstart,dtend,all_day,rrule,status,sequence}
     */
    public function parse(string $ics): array
    {
        $event = $this->firstEvent($ics);
        if ($event === null) {
            return [
                'uid' => null, 'summary' => null, 'description' => null, 'location' => null,
                'geo_lat' => null, 'geo_lon' => null,
                'dtstart' => null, 'dtend' => null, 'all_day' => false, 'rrule' => null,
                'status' => null, 'sequence' => 0,
            ];
        }

        $allDay = $this->isAllDay($event);
        $geo = $this->geo($event);

        return [
            'uid' => $this->s($event->UID ?? null),
            'summary' => $this->s($event->SUMMARY ?? null),
            'description' => $this->s($event->DESCRIPTION ?? null),
            'location' => $this->s($event->LOCATION ?? null),
            'geo_lat' => $geo['lat'],
            'geo_lon' => $geo['lon'],
            'dtstart' => $this->propIso($event->DTSTART ?? null, $allDay),
            'dtend' => $this->propIso($event->DTEND ?? null, $allDay),
            'all_day' => $allDay,
            'rrule' => $this->s($event->RRULE ?? null),
            'status' => $this->s($event->STATUS ?? null),
            'alarm_minutes_before' => $this->parseAlarm($event),
            'sequence' => (int) $this->str($event->SEQUENCE ?? null),
            'organizer' => $this->mailtoValue($event->ORGANIZER ?? null),
            'attendees' => $this->parseAttendees($event),
        ];
    }

    /** @return list<array{email:string,name:?string,partstat:string}> */
    public function parseAttendees(VEvent $event): array
    {
        $out = [];
        foreach ($this->iter($event->select('ATTENDEE')) as $att) {
            $email = $this->mailtoValue($att);
            if ($email === null || $email === '') {
                continue;
            }
            $cn = null;
            $partstat = 'NEEDS-ACTION';
            if ($att instanceof Property) {
                $cn = $this->s($att->offsetGet('CN'));
                $ps = $this->str($att->offsetGet('PARTSTAT'));
                if ($ps !== '') {
                    $partstat = strtoupper($ps);
                }
            }
            $out[] = ['email' => $email, 'name' => $cn, 'partstat' => $partstat];
        }

        return $out;
    }

    /** Strip a leading mailto: from an ORGANIZER/ATTENDEE property value. */
    private function mailtoValue(mixed $prop): ?string
    {
        $v = trim($this->str($prop));
        if ($v === '') {
            return null;
        }
        $stripped = preg_replace('/^mailto:/i', '', $v);

        return is_string($stripped) ? $stripped : $v;
    }

    private function partstat(mixed $v): string
    {
        $s = strtoupper(trim(is_string($v) ? $v : ''));

        return in_array($s, ['NEEDS-ACTION', 'ACCEPTED', 'DECLINED', 'TENTATIVE'], true) ? $s : 'NEEDS-ACTION';
    }

    /**
     * Mirror a few fields into calendar_events columns for list/range/search.
     *
     * @return array{uid: ?string, summary: ?string, description: ?string, location: ?string,
     *   geo_lat: ?float, geo_lon: ?float,
     *   dtstart: ?CarbonImmutable, dtend: ?CarbonImmutable, all_day: bool, rrule: ?string,
     *   recurrence_until: ?CarbonImmutable, status: ?string, alarm_minutes_before: ?int, sequence: int}
     */
    public function denormalize(string $ics): array
    {
        $event = $this->firstEvent($ics);
        if ($event === null) {
            return [
                'uid' => null, 'summary' => null, 'description' => null, 'location' => null,
                'geo_lat' => null, 'geo_lon' => null,
                'dtstart' => null, 'dtend' => null, 'all_day' => false, 'rrule' => null,
                'recurrence_until' => null, 'status' => null, 'alarm_minutes_before' => null, 'sequence' => 0,
            ];
        }

        $rrule = $this->s($event->RRULE ?? null);
        $geo = $this->geo($event);

        return [
            'uid' => $this->s($event->UID ?? null),
            'summary' => $this->s($event->SUMMARY ?? null),
            'description' => $this->s($event->DESCRIPTION ?? null),
            'location' => $this->s($event->LOCATION ?? null),
            'geo_lat' => $geo['lat'],
            'geo_lon' => $geo['lon'],
            'dtstart' => $this->propDate($event->DTSTART ?? null),
            'dtend' => $this->propDate($event->DTEND ?? null),
            'all_day' => $this->isAllDay($event),
            'rrule' => $rrule,
            'recurrence_until' => $rrule !== null ? $this->recurrenceUntil($rrule) : null,
            'status' => $this->s($event->STATUS ?? null),
            'alarm_minutes_before' => $this->parseAlarm($event),
            'sequence' => (int) $this->str($event->SEQUENCE ?? null),
        ];
    }

    /**
     * Expand recurrences into concrete occurrences within [from,to]. Non-recurring
     * events pass through if they overlap the window.
     *
     * @return list<array{uid: string, summary: ?string, location: ?string, description: ?string,
     *   geo_lat: ?float, geo_lon: ?float, start: string, end: string, all_day: bool, status: ?string, recurring: bool}>
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
        $geo = $this->geo($vevent);

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
                'geo_lat' => $geo['lat'], 'geo_lon' => $geo['lon'],
                'start' => $start->toIso8601ZuluString(), 'end' => $end->toIso8601ZuluString(),
                'all_day' => $allDay, 'status' => $status, 'recurring' => false,
            ]];
        }

        return $this->expandRecurring($cal, $uid, $from, $to, $summary, $location, $description, $status, $allDay, $geo);
    }

    /**
     * @param  array{lat: ?float, lon: ?float}  $geo
     * @return list<array{uid: string, summary: ?string, location: ?string, description: ?string,
     *   geo_lat: ?float, geo_lon: ?float, start: string, end: string, all_day: bool, status: ?string, recurring: bool}>
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
        array $geo,
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
                // Read text fields from THIS occurrence's object so a RECURRENCE-ID
                // override's edited summary/location/etc win; fall back to the master.
                $obj = $it->getEventObject();
                $occGeo = $this->geo($obj);
                $out[] = [
                    'uid' => $uid,
                    'summary' => $this->s($obj->SUMMARY ?? null) ?? $summary,
                    'location' => $this->s($obj->LOCATION ?? null) ?? $location,
                    'description' => $this->s($obj->DESCRIPTION ?? null) ?? $description,
                    'geo_lat' => $occGeo['lat'] ?? $geo['lat'], 'geo_lon' => $occGeo['lon'] ?? $geo['lon'],
                    'start' => $start->toIso8601ZuluString(), 'end' => $endUtc->toIso8601ZuluString(),
                    'all_day' => $allDay,
                    'status' => $this->s($obj->STATUS ?? null) ?? $status,
                    'recurring' => true,
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

    /**
     * Read the VEVENT GEO property ("lat;lon") into a validated {lat,lon} pair.
     * Either component out of range or unparseable → both null (drop the pair).
     *
     * @return array{lat: ?float, lon: ?float}
     */
    private function geo(VEvent $event): array
    {
        $raw = $this->s($event->GEO ?? null);
        if ($raw === null || ! str_contains($raw, ';')) {
            return ['lat' => null, 'lon' => null];
        }
        [$latRaw, $lonRaw] = explode(';', $raw, 2);
        $lat = $this->coord(trim($latRaw), 90.0);
        $lon = $this->coord(trim($lonRaw), 180.0);
        if ($lat === null || $lon === null) {
            return ['lat' => null, 'lon' => null];
        }

        return ['lat' => $lat, 'lon' => $lon];
    }

    /** A numeric coordinate within ±$max, or null (out of range / non-numeric). */
    private function coord(mixed $value, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;

        return ($n >= -$max && $n <= $max) ? $n : null;
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
