<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use DateTimeInterface;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;
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
        $empty = ['component' => 'VEVENT', 'summary' => null, 'starts_at' => null, 'ends_at' => null, 'all_day' => false, 'rrule' => null, 'alarm_minutes' => null];

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

        return [
            'component' => $comp->name,
            'summary' => isset($comp->SUMMARY) ? (string) $comp->SUMMARY : null,
            'starts_at' => $start?->format('Y-m-d H:i:s'),
            'ends_at' => $end?->format('Y-m-d H:i:s'),
            'all_day' => $allDay,
            'rrule' => isset($comp->RRULE) ? (string) $comp->RRULE : null,
            'alarm_minutes' => $this->alarmMinutes($comp),
        ];
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
        $vevent = $vcal->add('VEVENT', ['UID' => $uid ?: (string) Str::uuid()]);
        $vevent->add('SUMMARY', (string) ($data['summary'] ?? 'Untitled'));

        $allDay = (bool) ($data['all_day'] ?? false);
        $start = new \DateTimeImmutable((string) $data['start']);
        $end = new \DateTimeImmutable((string) ($data['end'] ?? $data['start']));

        if ($allDay) {
            $vevent->add('DTSTART', $start, ['VALUE' => 'DATE']);
            $vevent->add('DTEND', $end, ['VALUE' => 'DATE']);
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
            $vevent->add('RRULE', (string) $data['rrule']);
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
     *
     * @return list<array{start: string, end: ?string}>
     */
    public function expand(string $ics, DateTimeInterface $from, DateTimeInterface $to): array
    {
        try {
            $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return [];
        }
        if (! isset($vcal->VEVENT)) {
            return [];
        }

        $out = [];
        try {
            if (isset($vcal->VEVENT[0]->RRULE)) {
                $it = new EventIterator($vcal, (string) $vcal->VEVENT[0]->UID);
                $it->fastForward($from);
                $limit = 0;
                while ($it->valid() && $it->getDTStart() <= $to && $limit++ < 750) {
                    $out[] = ['start' => $it->getDTStart()->format('Y-m-d H:i:s'), 'end' => $it->getDTEnd()?->format('Y-m-d H:i:s')];
                    $it->next();
                }
            } else {
                $s = $vcal->VEVENT[0]->DTSTART->getDateTime();
                if ($s >= $from && $s <= $to) {
                    $e = isset($vcal->VEVENT[0]->DTEND) ? $vcal->VEVENT[0]->DTEND->getDateTime() : null;
                    $out[] = ['start' => $s->format('Y-m-d H:i:s'), 'end' => $e?->format('Y-m-d H:i:s')];
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
}
