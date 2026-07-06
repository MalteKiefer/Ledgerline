<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use DateTimeInterface;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;
use Sabre\VObject\Splitter\ICalendar;
use Throwable;

/**
 * Builds and parses iCalendar. The raw ICS is the source of truth; denormalize()
 * mirrors the first component's fields into the calendar_objects columns for the
 * UI + time-range queries, and expand() unrolls recurrences within a window.
 */
class ICalService
{
    /**
     * @return array{component: string, summary: ?string, starts_at: ?string, ends_at: ?string, all_day: bool, rrule: ?string, alarm_minutes: ?int}
     */
    public function denormalize(string $ics): array
    {
        $empty = ['component' => 'VEVENT', 'uid' => null, 'summary' => null, 'starts_at' => null, 'ends_at' => null, 'all_day' => false, 'rrule' => null, 'alarm_minutes' => null];

        try {
            $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return $empty;
        }

        // With recurrence overrides present, the denormalised columns mirror
        // the master VEVENT (the one without a RECURRENCE-ID).
        $comp = (isset($vcal->VEVENT) ? $this->masterEvent($vcal) : null) ?? $vcal->VEVENT[0] ?? $vcal->VTODO[0] ?? null;
        if ($comp === null) {
            return $empty;
        }

        $start = isset($comp->DTSTART) ? $comp->DTSTART->getDateTime() : null;
        $allDay = isset($comp->DTSTART) && ! $comp->DTSTART->hasTime();
        $end = null;
        if (isset($comp->DTEND)) {
            $end = $comp->DTEND->getDateTime();
        } elseif ($start !== null && isset($comp->DURATION)) {
            $end = (clone $start)->add(DateTimeParser::parseDuration((string) $comp->DURATION));
        }

        // Timed events are stored in UTC (their TZID is preserved in the ICS);
        // all-day events keep their bare calendar date.
        return [
            'component' => $comp->name,
            'uid' => isset($comp->UID) ? (string) $comp->UID : null,
            'summary' => isset($comp->SUMMARY) ? (string) $comp->SUMMARY : null,
            'starts_at' => $this->toUtc($start, $allDay),
            'ends_at' => $this->toUtc($end, $allDay),
            'all_day' => $allDay,
            'rrule' => isset($comp->RRULE) ? (string) $comp->RRULE : null,
            'alarm_minutes' => $this->alarmMinutes($comp),
        ];
    }

    /** Format a datetime for the denormalised columns: UTC for timed, bare date for all-day. */
    private function toUtc(?DateTimeInterface $dt, bool $allDay): ?string
    {
        if ($dt === null) {
            return null;
        }
        if ($allDay) {
            return $dt->format('Y-m-d H:i:s');
        }

        return (new \DateTimeImmutable('@'.$dt->getTimestamp()))->format('Y-m-d H:i:s');
    }

    /**
     * Largest lead of the component's relative VALARM triggers (null if none).
     * The column drives the reminder scan; the max lead makes its expansion
     * window wide enough for every alarm of the event.
     */
    private function alarmMinutes(object $comp): ?int
    {
        $leads = $this->componentLeads($comp);

        return $leads === [] ? null : max($leads);
    }

    /**
     * All relative VALARM leads (minutes before start) of an event, sorted
     * descending and de-duplicated. Absolute DATE-TIME triggers are ignored.
     *
     * @return list<int>
     */
    public function alarmLeads(string $ics): array
    {
        try {
            $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return [];
        }
        $comp = $vcal->VEVENT[0] ?? $vcal->VTODO[0] ?? null;

        return $comp === null ? [] : $this->componentLeads($comp);
    }

    /** @return list<int> */
    private function componentLeads(object $comp): array
    {
        $leads = [];
        foreach ($comp->VALARM ?? [] as $alarm) {
            if (! isset($alarm->TRIGGER)) {
                continue;
            }
            $trigger = $alarm->TRIGGER;
            if (isset($trigger['VALUE']) && strtoupper((string) $trigger['VALUE']) === 'DATE-TIME') {
                continue;
            }
            try {
                $interval = DateTimeParser::parseDuration((string) $trigger);
            } catch (Throwable) {
                continue;
            }
            $minutes = ($interval->d * 1440) + ($interval->h * 60) + $interval->i;
            $leads[] = $interval->invert === 1 ? $minutes : 0;
        }
        rsort($leads);

        return array_values(array_unique($leads));
    }

    /**
     * Sanitise a user-supplied RRULE before it is written as a structured
     * property: strip control characters (CRLF injection guard) and enforce an
     * RFC5545 recurrence-rule charset, so it can never smuggle extra properties
     * or components. Returns null if nothing valid remains.
     */
    public function sanitizeRrule(string $rrule): ?string
    {
        $rrule = strtoupper(trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $rrule) ?? ''));
        // FREQ=…;INTERVAL=…;BYDAY=MO,TU;UNTIL=20260101T000000Z etc. — letters,
        // digits, and the RRULE separators only. No ':' or whitespace.
        if ($rrule === '' || preg_match('/^[A-Z0-9;=,\-+]+$/', $rrule) !== 1) {
            return null;
        }
        // Must carry a valid RFC5545 FREQ.
        if (preg_match('/(^|;)FREQ=(SECONDLY|MINUTELY|HOURLY|DAILY|WEEKLY|MONTHLY|YEARLY)(;|$)/', $rrule) !== 1) {
            return null;
        }

        return $rrule;
    }

    /**
     * Split a (multi-event) iCalendar document into a deterministic
     * uri => single-component-ICS map, keyed by a stable hash of the component's
     * UID (falling back to its content). Feeding the same feed twice yields the
     * same uris, so a subscription refresh via CalendarObjectPersister::replace()
     * touches only what actually changed.
     *
     * @return array<string, string>
     */
    public function eventMap(string $ics): array
    {
        try {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $ics);
            rewind($stream);
            $splitter = new ICalendar($stream);
        } catch (Throwable) {
            return [];
        }

        $map = [];
        while (true) {
            if (count($map) >= CalendarImporter::MAX_OBJECTS) {
                break; // bound feed size (DoS guard)
            }
            try {
                $vobj = $splitter->getNext();
            } catch (Throwable) {
                continue;
            }
            if ($vobj === null) {
                break;
            }
            $comp = $vobj->VEVENT[0] ?? $vobj->VTODO[0] ?? null;
            if ($comp === null) {
                continue;
            }
            $payload = $vobj->serialize();
            $key = isset($comp->UID) ? (string) $comp->UID : $payload;
            $map[sha1($key).'.ics'] = $payload;
        }

        return $map;
    }

    /** Extract the first component's UID (to preserve it across edits). */
    public function uid(string $ics): ?string
    {
        try {
            $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return null;
        }
        $comp = $vcal->VEVENT[0] ?? $vcal->VTODO[0] ?? null;

        return isset($comp->UID) ? (string) $comp->UID : null;
    }

    /**
     * Build a VEVENT ICS from editor data.
     *
     * @param  array<string, mixed>  $data
     */
    public function buildEvent(array $data, ?string $uid = null): string
    {
        $vcal = new VCalendar;
        $vcal->PRODID = '-//Ledgerline//Calendar//EN';
        $vevent = $vcal->add('VEVENT', ['UID' => $uid ?: (string) Str::uuid()]);
        $vevent->add('SUMMARY', (string) ($data['summary'] ?? 'Untitled'));

        $allDay = (bool) ($data['all_day'] ?? false);
        // The wall-clock start/end are interpreted in the event's timezone (an
        // IANA name); absent, UTC — matching the previous behaviour. sabre emits
        // a TZID parameter for a named zone, or a 'Z' (UTC) time otherwise.
        $tz = $this->displayZone(filled($data['timezone'] ?? null) ? (string) $data['timezone'] : 'UTC');
        try {
            $start = new \DateTimeImmutable((string) $data['start'], $tz);
            $end = new \DateTimeImmutable((string) ($data['end'] ?? $data['start']), $tz);
        } catch (Throwable) {
            // Unparseable date → fall back to "now" rather than fatalling; the
            // web layer validates dates, this guards the other call paths.
            $start = new \DateTimeImmutable('now', $tz);
            $end = $start;
        }

        if ($allDay) {
            // RFC5545: a DATE DTEND is exclusive, so a single-day event needs
            // DTEND = DTSTART + 1 day (and DTEND must be strictly after DTSTART).
            $vevent->add('DTSTART', $start, ['VALUE' => 'DATE']);
            $vevent->add('DTEND', $end->modify('+1 day'), ['VALUE' => 'DATE']);
        } else {
            $vevent->add('DTSTART', $start);
            $vevent->add('DTEND', $end);
        }

        foreach (['location' => 'LOCATION', 'description' => 'DESCRIPTION'] as $key => $prop) {
            if (filled($data[$key] ?? null)) {
                $vevent->add($prop, (string) $data[$key]);
            }
        }
        if (filled($data['rrule'] ?? null)) {
            $rrule = $this->sanitizeRrule((string) $data['rrule']);
            if ($rrule !== null) {
                $vevent->add('RRULE', $rrule);
            }
        }
        // Reminders: one VALARM DISPLAY trigger per lead. The legacy single
        // 'reminder_minutes' key is folded in for older call paths.
        $leads = array_map('intval', array_filter((array) ($data['reminders'] ?? []), fn ($m) => $m !== null && $m !== ''));
        if (filled($data['reminder_minutes'] ?? null)) {
            $leads[] = (int) $data['reminder_minutes'];
        }
        foreach (array_slice(array_unique($leads), 0, 5) as $lead) {
            $alarm = $vevent->add('VALARM', ['ACTION' => 'DISPLAY', 'DESCRIPTION' => 'Reminder']);
            $alarm->add('TRIGGER', '-PT'.max(0, $lead).'M');
        }

        return $vcal->serialize();
    }

    /**
     * Exclude one occurrence of a recurring event: appends an EXDATE matching
     * the master DTSTART's value type/zone and drops any override VEVENT that
     * addressed the same occurrence.
     */
    public function addExdate(string $ics, string $occurrenceStart): string
    {
        $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        $master = $this->masterEvent($vcal);
        if ($master === null || ! isset($master->RRULE)) {
            return $ics;
        }

        $occurrence = $this->occurrenceValue($master, $occurrenceStart);
        $allDay = ! $master->DTSTART->hasTime();
        $master->add('EXDATE', $occurrence, $allDay ? ['VALUE' => 'DATE'] : []);
        $this->removeOverride($vcal, $occurrence);

        return $vcal->serialize();
    }

    /**
     * Add or replace the override VEVENT (RECURRENCE-ID) for one occurrence of
     * a recurring event. $data carries the changed editor fields; anything not
     * provided falls back to the master's value.
     *
     * @param  array<string, mixed>  $data
     */
    public function setOverride(string $ics, string $occurrenceStart, array $data): string
    {
        $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        $master = $this->masterEvent($vcal);
        if ($master === null || ! isset($master->RRULE)) {
            return $ics;
        }

        $occurrence = $this->occurrenceValue($master, $occurrenceStart);
        $this->removeOverride($vcal, $occurrence);

        $allDay = ! $master->DTSTART->hasTime();
        $tz = $master->DTSTART->getDateTime()->getTimezone();

        $override = $vcal->add('VEVENT', ['UID' => (string) $master->UID]);
        $override->add('RECURRENCE-ID', $occurrence, $allDay ? ['VALUE' => 'DATE'] : []);
        $override->add('SUMMARY', (string) ($data['summary'] ?? (string) $master->SUMMARY));

        try {
            $start = new \DateTimeImmutable((string) $data['start'], $tz);
            $end = new \DateTimeImmutable((string) ($data['end'] ?? $data['start']), $tz);
        } catch (Throwable) {
            $start = $occurrence;
            $end = $occurrence;
        }
        if ($allDay) {
            $override->add('DTSTART', $start, ['VALUE' => 'DATE']);
            $override->add('DTEND', $end->modify('+1 day'), ['VALUE' => 'DATE']);
        } else {
            $override->add('DTSTART', $start);
            $override->add('DTEND', $end);
        }

        foreach (['location' => 'LOCATION', 'description' => 'DESCRIPTION'] as $key => $prop) {
            $value = $data[$key] ?? (isset($master->$prop) ? (string) $master->$prop : null);
            if (filled($value)) {
                $override->add($prop, (string) $value);
            }
        }

        return $vcal->serialize();
    }

    /**
     * Carry recurrence exceptions (EXDATE + override VEVENTs) from the stored
     * ICS onto a freshly rebuilt one. Editing the series must not silently
     * drop its exceptions — unless the recurrence rule itself changed, in
     * which case the old exceptions no longer address valid occurrences.
     */
    public function carryExceptions(string $oldIcs, string $newIcs): string
    {
        try {
            $old = Reader::read($oldIcs, Reader::OPTION_FORGIVING);
            $new = Reader::read($newIcs, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return $newIcs;
        }
        $oldMaster = isset($old->VEVENT) ? $this->masterEvent($old) : null;
        $newMaster = isset($new->VEVENT) ? $this->masterEvent($new) : null;
        if ($oldMaster === null || $newMaster === null || ! isset($oldMaster->RRULE, $newMaster->RRULE)) {
            return $newIcs;
        }
        if ((string) $oldMaster->RRULE !== (string) $newMaster->RRULE) {
            return $newIcs; // rule changed → old occurrences are meaningless
        }

        foreach ($oldMaster->EXDATE ?? [] as $exdate) {
            $newMaster->add(clone $exdate);
        }
        foreach ($old->VEVENT as $vevent) {
            if (isset($vevent->{'RECURRENCE-ID'})) {
                $new->add(clone $vevent);
            }
        }

        return $new->serialize();
    }

    /** The master VEVENT (the one without a RECURRENCE-ID). */
    private function masterEvent(VCalendar $vcal): ?object
    {
        foreach ($vcal->VEVENT ?? [] as $vevent) {
            if (! isset($vevent->{'RECURRENCE-ID'})) {
                return $vevent;
            }
        }

        return null;
    }

    /** Parse a feed-format occurrence start into the master DTSTART's zone. */
    private function occurrenceValue(object $master, string $occurrenceStart): \DateTimeImmutable
    {
        $allDay = ! $master->DTSTART->hasTime();
        $zone = $allDay ? null : $master->DTSTART->getDateTime()->getTimezone();
        // Timed feed values are UTC wall-clock; convert into the event's zone
        // so the RECURRENCE-ID/EXDATE matches the generated occurrence exactly.
        $utc = new \DateTimeImmutable($occurrenceStart, new \DateTimeZone('UTC'));

        return $allDay ? new \DateTimeImmutable($utc->format('Y-m-d')) : $utc->setTimezone($zone);
    }

    private function removeOverride(VCalendar $vcal, \DateTimeImmutable $occurrence): void
    {
        foreach (iterator_to_array($vcal->VEVENT ?? []) as $vevent) {
            if (isset($vevent->{'RECURRENCE-ID'})
                && $vevent->{'RECURRENCE-ID'}->getDateTime()->getTimestamp() === $occurrence->getTimestamp()) {
                $vcal->remove($vevent);
            }
        }
    }

    /**
     * Expand a (possibly recurring) event's instances overlapping [from, to].
     * Timed instances are rendered as wall-clock in $displayTz (default UTC);
     * all-day instances keep their bare date regardless of zone. Each recurring
     * instance also carries its occurrence id (the original scheduled start, in
     * UTC feed format) so exceptions can address it, even when an override
     * VEVENT has moved the instance elsewhere.
     *
     * @return list<array{start: string, end: ?string, recurrence_id: ?string}>
     */
    public function expand(string $ics, DateTimeInterface $from, DateTimeInterface $to, string $displayTz = 'UTC'): array
    {
        try {
            $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return [];
        }
        $master = isset($vcal->VEVENT) ? $this->masterEvent($vcal) : null;
        if ($master === null || ! isset($master->DTSTART)) {
            return [];
        }

        $allDay = ! $master->DTSTART->hasTime();
        $zone = $allDay ? null : $this->displayZone($displayTz);
        $fmt = fn (?DateTimeInterface $d): ?string => $d === null ? null
            : ($zone === null ? $d->format('Y-m-d H:i:s') : \DateTimeImmutable::createFromInterface($d)->setTimezone($zone)->format('Y-m-d H:i:s'));
        // Occurrence ids travel in UTC (all-day: the bare date) so the
        // exception endpoints can round-trip them without knowing the zone.
        $rid = fn (DateTimeInterface $d): string => $allDay
            ? $d->format('Y-m-d')
            : \DateTimeImmutable::createFromInterface($d)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $out = [];
        try {
            if (isset($master->RRULE)) {
                $it = new EventIterator($vcal, (string) $master->UID);
                $it->fastForward($from);
                $limit = 0;
                while ($it->valid() && $it->getDTStart() <= $to && $limit++ < 750) {
                    // The expanded object's RECURRENCE-ID is the ORIGINAL
                    // occurrence, also for instances an override has moved.
                    $obj = $it->getEventObject();
                    $occurrence = isset($obj->{'RECURRENCE-ID'}) ? $obj->{'RECURRENCE-ID'}->getDateTime() : $it->getDTStart();
                    $out[] = [
                        'start' => $fmt($it->getDTStart()),
                        'end' => $fmt($it->getDTEnd()),
                        'recurrence_id' => $rid($occurrence),
                    ];
                    $it->next();
                }
            } else {
                $s = $master->DTSTART->getDateTime();
                if ($s >= $from && $s <= $to) {
                    $e = isset($master->DTEND) ? $master->DTEND->getDateTime() : null;
                    $out[] = ['start' => $fmt($s), 'end' => $fmt($e), 'recurrence_id' => null];
                }
            }
        } catch (Throwable) {
            // malformed recurrence: fall back to the single stored start
            if ($this->denormalize($ics)['starts_at'] !== null) {
                $out[] = ['start' => $this->denormalize($ics)['starts_at'], 'end' => $this->denormalize($ics)['ends_at'], 'recurrence_id' => null];
            }
        }

        return $out;
    }

    /**
     * The editor's view of an event: wall-clock start/end in the event's own
     * timezone (so what the user typed comes back unchanged) plus that timezone.
     *
     * @return array{start: ?string, end: ?string, all_day: bool, timezone: ?string}
     */
    public function editable(string $ics): array
    {
        try {
            $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
            $vevent = isset($vcal->VEVENT) ? ($this->masterEvent($vcal) ?? $vcal->VEVENT[0]) : null;
        } catch (Throwable) {
            $vevent = null;
        }
        if ($vevent === null || ! isset($vevent->DTSTART)) {
            return ['start' => null, 'end' => null, 'all_day' => false, 'timezone' => null];
        }

        $allDay = ! $vevent->DTSTART->hasTime();
        $tzid = $allDay ? null : ($vevent->DTSTART['TZID'] !== null ? (string) $vevent->DTSTART['TZID'] : null);
        $format = $allDay ? 'Y-m-d' : 'Y-m-d\TH:i';

        $end = null;
        if (isset($vevent->DTEND)) {
            $endDt = $vevent->DTEND->getDateTime();
            // All-day DTEND is exclusive on the wire; show the inclusive last day.
            $end = ($allDay ? $endDt->modify('-1 day') : $endDt)->format($format);
        }

        return [
            'start' => $vevent->DTSTART->getDateTime()->format($format),
            'end' => $end,
            'all_day' => $allDay,
            'timezone' => $tzid,
        ];
    }

    private function displayZone(string $tz): \DateTimeZone
    {
        try {
            return new \DateTimeZone($tz);
        } catch (Throwable) {
            return new \DateTimeZone('UTC');
        }
    }
}
