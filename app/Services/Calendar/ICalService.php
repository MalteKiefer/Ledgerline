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

        $comp = $vcal->VEVENT[0] ?? $vcal->VTODO[0] ?? null;
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

    /** Lead minutes of the first VALARM's relative TRIGGER (null if none/absolute). */
    private function alarmMinutes(object $comp): ?int
    {
        if (! isset($comp->VALARM[0]->TRIGGER)) {
            return null;
        }
        $trigger = $comp->VALARM[0]->TRIGGER;
        // Only relative (duration) triggers are supported; absolute DATE-TIME is ignored.
        if (isset($trigger['VALUE']) && strtoupper((string) $trigger['VALUE']) === 'DATE-TIME') {
            return null;
        }
        try {
            $interval = DateTimeParser::parseDuration((string) $trigger);
        } catch (Throwable) {
            return null;
        }
        $minutes = ($interval->d * 1440) + ($interval->h * 60) + $interval->i;

        // A negative duration (invert=1) means "before"; that's the reminder lead.
        return $interval->invert === 1 ? $minutes : 0;
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
        // Reminder: minutes-before as a VALARM DISPLAY trigger.
        if (filled($data['reminder_minutes'] ?? null)) {
            $alarm = $vevent->add('VALARM', ['ACTION' => 'DISPLAY', 'DESCRIPTION' => 'Reminder']);
            $alarm->add('TRIGGER', '-PT'.(int) $data['reminder_minutes'].'M');
        }

        return $vcal->serialize();
    }

    /**
     * Expand a (possibly recurring) event's instances overlapping [from, to].
     * Timed instances are rendered as wall-clock in $displayTz (default UTC);
     * all-day instances keep their bare date regardless of zone.
     *
     * @return list<array{start: string, end: ?string}>
     */
    public function expand(string $ics, DateTimeInterface $from, DateTimeInterface $to, string $displayTz = 'UTC'): array
    {
        try {
            $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return [];
        }
        if (! isset($vcal->VEVENT)) {
            return [];
        }

        $allDay = isset($vcal->VEVENT[0]->DTSTART) && ! $vcal->VEVENT[0]->DTSTART->hasTime();
        $zone = $allDay ? null : $this->displayZone($displayTz);
        $fmt = fn (?DateTimeInterface $d): ?string => $d === null ? null
            : ($zone === null ? $d->format('Y-m-d H:i:s') : \DateTimeImmutable::createFromInterface($d)->setTimezone($zone)->format('Y-m-d H:i:s'));

        $out = [];
        try {
            if (isset($vcal->VEVENT[0]->RRULE)) {
                $it = new EventIterator($vcal, (string) $vcal->VEVENT[0]->UID);
                $it->fastForward($from);
                $limit = 0;
                while ($it->valid() && $it->getDTStart() <= $to && $limit++ < 750) {
                    $out[] = ['start' => $fmt($it->getDTStart()), 'end' => $fmt($it->getDTEnd())];
                    $it->next();
                }
            } else {
                $s = $vcal->VEVENT[0]->DTSTART->getDateTime();
                if ($s >= $from && $s <= $to) {
                    $e = isset($vcal->VEVENT[0]->DTEND) ? $vcal->VEVENT[0]->DTEND->getDateTime() : null;
                    $out[] = ['start' => $fmt($s), 'end' => $fmt($e)];
                }
            }
        } catch (Throwable) {
            // malformed recurrence: fall back to the single stored start
            if ($this->denormalize($ics)['starts_at'] !== null) {
                $out[] = ['start' => $this->denormalize($ics)['starts_at'], 'end' => $this->denormalize($ics)['ends_at']];
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
            $vevent = $vcal->VEVENT[0] ?? null;
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
