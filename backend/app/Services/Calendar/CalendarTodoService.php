<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarTodo;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VAlarm;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VTodo;
use Sabre\VObject\Property;
use Sabre\VObject\Property\ICalendar\DateTime as DateTimeProperty;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\RRuleIterator;
use Throwable;

/**
 * Builds and parses iCalendar tasks (VCALENDAR/VTODO). The raw ICS is the source
 * of truth; build() produces it from editor fields, parse() reads it back for the
 * editor, denormalize() mirrors columns for list/filter/search, and the recurrence
 * helpers (nextOccurrences / rollForward) implement the standard "one VTODO with
 * RRULE, complete → roll the DUE/DTSTART forward" model.
 *
 * sabre/vobject is untyped (every property access is mixed), so reads funnel
 * through the small mixed-narrowing helpers below (str/s/parts) and Property/VTodo
 * instances are confirmed with instanceof before use — same idiom as
 * CalendarEventService. All datetimes are stored/emitted UTC ('Z'); tasks whose
 * DUE/DTSTART carry VALUE=DATE are all-day.
 */
class CalendarTodoService
{
    /** Hard cap on recurrence expansion to bound unbounded RRULEs (DoS guard). */
    private const MAX_OCCURRENCES = 1000;

    /** The four RFC 5545 VTODO status values. */
    public const STATUSES = ['NEEDS-ACTION', 'IN-PROCESS', 'COMPLETED', 'CANCELLED'];

    /**
     * Build a VCALENDAR/VTODO string from editor data. Reuses $uid on update so the
     * task keeps its identity for CalDAV clients.
     *
     * @param  array<string, mixed>  $data  {summary,description,dtstart,due,all_day,status,priority,percent_complete,completed,rrule,categories,related_to,alarm_minutes_before}
     */
    public function build(array $data, ?string $uid = null, int $sequence = 0): string
    {
        $uidValue = $uid !== null && $uid !== '' ? $uid : (string) Str::uuid();

        $cal = new VCalendar;
        /** @var VTodo $todo */
        $todo = $cal->add('VTODO', []);
        // A fresh VTODO auto-populates UID + DTSTAMP; remove-then-add so we replace
        // those single-valued properties instead of duplicating them.
        $todo->remove('UID');
        $todo->add('UID', $uidValue);
        $this->applyCoreFields($todo, $data, $sequence);

        $serialized = $cal->serialize();

        return is_string($serialized) ? $serialized : '';
    }

    /**
     * Update an existing VTODO in place from editor data, preserving every
     * property the editor does not model (ATTENDEE/ORGANIZER/URL/X-*) and the
     * surrounding VCALENDAR. A fresh build() would drop all of those on every
     * edit. Falls back to build() when the stored ICS is unreadable.
     *
     * @param  array<string, mixed>  $data
     */
    public function rebuild(string $existingIcs, array $data, int $sequence = 0): string
    {
        $cal = $this->readCalendar($existingIcs);
        $todo = $this->firstTodoOf($cal);
        if ($cal === null || $todo === null) {
            $uid = $this->parse($existingIcs)['uid'];

            return $this->build($data, is_string($uid) ? $uid : null, $sequence);
        }

        $this->applyCoreFields($todo, $data, $sequence);

        $serialized = $cal->serialize();

        return is_string($serialized) ? $serialized : $this->build($data, $this->s($todo->UID ?? null), $sequence);
    }

    /**
     * Set the editor-owned properties on a VTODO (remove-then-add so an update
     * replaces rather than duplicates, and a cleared field is removed). Leaves
     * UID and any unmodelled property untouched.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyCoreFields(VTodo $todo, array $data, int $sequence): void
    {
        $allDay = (bool) ($data['all_day'] ?? false);

        $todo->remove('DTSTAMP');
        $todo->add('DTSTAMP', gmdate('Ymd\THis\Z'));
        $todo->remove('SEQUENCE');
        $todo->add('SEQUENCE', (string) max(0, $sequence));

        foreach (['DTSTART', 'DUE', 'COMPLETED'] as $prop) {
            $todo->remove($prop);
        }
        $this->addDate($todo, 'DTSTART', $this->parseDateTime($data['dtstart'] ?? null), $allDay);
        $this->addDate($todo, 'DUE', $this->parseDateTime($data['due'] ?? null), $allDay);
        $this->addDate($todo, 'COMPLETED', $this->parseDateTime($data['completed'] ?? null), false);

        foreach (['summary' => 'SUMMARY', 'description' => 'DESCRIPTION'] as $key => $prop) {
            $todo->remove($prop);
            if (filled($data[$key] ?? null)) {
                $todo->add($prop, $this->str($data[$key] ?? null));
            }
        }

        $todo->remove('STATUS');
        $status = strtoupper(trim($this->str($data['status'] ?? null)));
        if (in_array($status, self::STATUSES, true)) {
            $todo->add('STATUS', $status);
        }

        $todo->remove('PRIORITY');
        $priority = $this->intOrNull($data['priority'] ?? null, 0, 9);
        if ($priority !== null) {
            $todo->add('PRIORITY', (string) $priority);
        }

        $todo->remove('PERCENT-COMPLETE');
        $percent = $this->intOrNull($data['percent_complete'] ?? null, 0, 100);
        if ($percent !== null) {
            $todo->add('PERCENT-COMPLETE', (string) $percent);
        }

        $todo->remove('CATEGORIES');
        $categories = $this->normaliseCategories($data['categories'] ?? null);
        if ($categories !== []) {
            $todo->add('CATEGORIES', $categories);
        }

        $todo->remove('RELATED-TO');
        $relatedTo = trim($this->str($data['related_to'] ?? null));
        if ($relatedTo !== '') {
            $todo->add('RELATED-TO', $relatedTo);
        }

        $todo->remove('RRULE');
        $rrule = trim($this->str($data['rrule'] ?? null));
        if ($rrule !== '') {
            $todo->add('RRULE', $rrule);
        }

        // VALARM is editor-owned (alarm_minutes_before): replace it wholesale.
        $todo->remove('VALARM');
        $alarm = $this->intOrNull($data['alarm_minutes_before'] ?? null, 0, 40320); // ≤ 4 weeks
        if ($alarm !== null) {
            /** @var VAlarm $valarm */
            $valarm = $todo->add('VALARM', []);
            $valarm->add('ACTION', 'DISPLAY');
            $valarm->add('TRIGGER', '-PT'.$alarm.'M');
            $valarm->add('DESCRIPTION', $this->str($data['summary'] ?? null) !== '' ? $this->str($data['summary'] ?? null) : 'Reminder');
        }
    }

    /**
     * Parse a VCALENDAR into structured editor data (first VTODO).
     *
     * @return array<string, mixed>
     */
    public function parse(string $ics): array
    {
        $todo = $this->firstTodo($ics);
        if ($todo === null) {
            return $this->blankParse();
        }

        $allDay = $this->isAllDay($todo);

        return [
            'uid' => $this->s($todo->UID ?? null),
            'summary' => $this->s($todo->SUMMARY ?? null),
            'description' => $this->s($todo->DESCRIPTION ?? null),
            'dtstart' => $this->propIso($todo->DTSTART ?? null, $allDay),
            'due' => $this->propIso($todo->DUE ?? null, $allDay),
            'completed' => $this->propIso($todo->COMPLETED ?? null, false),
            'all_day' => $allDay,
            'status' => $this->s($todo->STATUS ?? null),
            'priority' => $this->propInt($todo->PRIORITY ?? null),
            'percent_complete' => $this->propInt($todo->{'PERCENT-COMPLETE'} ?? null),
            'rrule' => $this->s($todo->RRULE ?? null),
            'categories' => $this->parseCategories($todo),
            'related_to' => $this->s($todo->{'RELATED-TO'} ?? null),
            'alarm_minutes_before' => $this->parseAlarmMinutes($todo),
            'sequence' => (int) $this->str($todo->SEQUENCE ?? null),
        ];
    }

    /**
     * Mirror a few fields into calendar_todos columns for list/filter/search.
     *
     * @return array{uid: ?string, summary: ?string, description: ?string, status: string,
     *   priority: ?int, percent_complete: ?int, due: ?CarbonImmutable, dtstart: ?CarbonImmutable,
     *   completed_at: ?CarbonImmutable, all_day: bool, rrule: ?string, categories: list<string>|null,
     *   related_to: ?string, sequence: int}
     */
    public function denormalize(string $ics): array
    {
        $todo = $this->firstTodo($ics);
        if ($todo === null) {
            return [
                'uid' => null, 'summary' => null, 'description' => null, 'status' => 'NEEDS-ACTION',
                'priority' => null, 'percent_complete' => null, 'due' => null, 'dtstart' => null,
                'completed_at' => null, 'all_day' => false, 'rrule' => null, 'categories' => null,
                'related_to' => null, 'sequence' => 0,
            ];
        }

        $status = strtoupper((string) ($this->s($todo->STATUS ?? null) ?? 'NEEDS-ACTION'));
        $categories = $this->parseCategories($todo);

        return [
            'uid' => $this->s($todo->UID ?? null),
            'summary' => $this->s($todo->SUMMARY ?? null),
            'description' => $this->s($todo->DESCRIPTION ?? null),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'NEEDS-ACTION',
            'priority' => $this->propInt($todo->PRIORITY ?? null),
            'percent_complete' => $this->propInt($todo->{'PERCENT-COMPLETE'} ?? null),
            'due' => $this->propDate($todo->DUE ?? null),
            'dtstart' => $this->propDate($todo->DTSTART ?? null),
            'completed_at' => $this->propDate($todo->COMPLETED ?? null),
            'all_day' => $this->isAllDay($todo),
            'rrule' => $this->s($todo->RRULE ?? null),
            'categories' => $categories === [] ? null : $categories,
            'related_to' => $this->s($todo->{'RELATED-TO'} ?? null),
            'sequence' => (int) $this->str($todo->SEQUENCE ?? null),
        ];
    }

    /**
     * The next N due occurrences at or after $from for a (possibly recurring) task,
     * as ISO-8601 UTC strings. A non-recurring task yields its single DUE (when set
     * and not before $from). Bounded by MAX_OCCURRENCES.
     *
     * @return list<string>
     */
    public function nextOccurrences(CalendarTodo $todo, CarbonImmutable $from, int $limit = 10): array
    {
        $anchor = $todo->due ?? $todo->dtstart;
        $rrule = $todo->rrule;
        if ($anchor === null) {
            return [];
        }
        if ($rrule === null || $rrule === '') {
            return $anchor >= $from ? [$anchor->toIso8601ZuluString()] : [];
        }

        try {
            $it = new RRuleIterator($rrule, $anchor->toDateTime());
            $it->fastForward($from->toDateTime());
        } catch (Throwable) {
            return [];
        }

        $out = [];
        $guard = 0;
        while ($it->valid() && count($out) < $limit && $guard < self::MAX_OCCURRENCES) {
            $dt = $it->current();
            if ($dt instanceof DateTimeInterface) {
                $out[] = CarbonImmutable::instance($dt)->utc()->toIso8601ZuluString();
            }
            $guard++;
            try {
                $it->next();
            } catch (Throwable) {
                break;
            }
        }

        return $out;
    }

    /**
     * Advance a recurring task to its next occurrence: shift DUE (and DTSTART, by
     * the same delta) to the following RRULE step and reset the instance to
     * NEEDS-ACTION (clear COMPLETED / PERCENT-COMPLETE, bump SEQUENCE). Returns the
     * rolled ICS, or null when the task does not recur or the series is exhausted
     * (UNTIL reached) — the caller then completes it terminally. RRULE, VALARM,
     * CATEGORIES, RELATED-TO and any unknown properties are preserved (the raw ICS
     * is the source of truth).
     */
    public function rollForward(string $ics): ?string
    {
        $cal = $this->readCalendar($ics);
        $todo = $this->firstTodoOf($cal);
        if ($cal === null || $todo === null) {
            return null;
        }
        $rrule = $this->s($todo->RRULE ?? null);
        if ($rrule === null) {
            return null;
        }

        $anchor = $this->propDate($todo->DTSTART ?? null) ?? $this->propDate($todo->DUE ?? null);
        if ($anchor === null) {
            return null;
        }

        try {
            $it = new RRuleIterator($rrule, $anchor->toDateTime());
            $it->next(); // step past the current occurrence
        } catch (Throwable) {
            return null;
        }
        if (! $it->valid()) {
            return null; // series exhausted (COUNT/UNTIL) → terminal completion
        }
        $next = $it->current();
        if (! $next instanceof DateTimeInterface) {
            return null;
        }
        $deltaSeconds = CarbonImmutable::instance($next)->utc()->getTimestamp() - $anchor->getTimestamp();
        if ($deltaSeconds <= 0) {
            return null;
        }

        $allDay = $this->isAllDay($todo);
        foreach (['DTSTART', 'DUE'] as $name) {
            $current = $this->propDate($todo->{$name} ?? null);
            if ($current !== null) {
                $todo->remove($name);
                $this->addDate($todo, $name, $current->addSeconds($deltaSeconds), $allDay);
            }
        }

        $todo->remove('COMPLETED');
        $todo->remove('PERCENT-COMPLETE');
        $todo->remove('STATUS');
        $todo->add('STATUS', 'NEEDS-ACTION');
        $this->bumpSequenceAndStamp($todo);

        return $this->serialize($cal);
    }

    /** Terminal completion: STATUS=COMPLETED, PERCENT-COMPLETE=100, COMPLETED=now. */
    public function markCompleted(string $ics): string
    {
        return $this->mutate($ics, function (VTodo $todo): void {
            $todo->remove('STATUS');
            $todo->add('STATUS', 'COMPLETED');
            $todo->remove('PERCENT-COMPLETE');
            $todo->add('PERCENT-COMPLETE', '100');
            $todo->remove('COMPLETED');
            $todo->add('COMPLETED', gmdate('Ymd\THis\Z'));
            $this->bumpSequenceAndStamp($todo);
        });
    }

    /** Re-open a task: STATUS=NEEDS-ACTION, PERCENT-COMPLETE=0, drop COMPLETED. */
    public function markUncompleted(string $ics): string
    {
        return $this->mutate($ics, function (VTodo $todo): void {
            $todo->remove('STATUS');
            $todo->add('STATUS', 'NEEDS-ACTION');
            $todo->remove('PERCENT-COMPLETE');
            $todo->add('PERCENT-COMPLETE', '0');
            $todo->remove('COMPLETED');
            $this->bumpSequenceAndStamp($todo);
        });
    }

    /**
     * Split a multi-VTODO VCALENDAR (an .ics import) into individual VTODOs.
     *
     * @return list<VTodo>
     */
    public function parseCalendarStream(string $ics): array
    {
        $cal = $this->readCalendar($ics);
        if ($cal === null) {
            return [];
        }
        $out = [];
        foreach ($this->iter($cal->select('VTODO')) as $todo) {
            if ($todo instanceof VTodo) {
                $out[] = $todo;
            }
        }

        return $out;
    }

    /** Apply a mutation to the first VTODO and reserialize; unreadable ICS passes through. */
    private function mutate(string $ics, callable $fn): string
    {
        $cal = $this->readCalendar($ics);
        $todo = $this->firstTodoOf($cal);
        if ($cal === null || $todo === null) {
            return $ics;
        }
        $fn($todo);

        return $this->serialize($cal);
    }

    private function bumpSequenceAndStamp(VTodo $todo): void
    {
        $seq = (int) $this->str($todo->SEQUENCE ?? null) + 1;
        $todo->remove('SEQUENCE');
        $todo->add('SEQUENCE', (string) $seq);
        $todo->remove('DTSTAMP');
        $todo->add('DTSTAMP', gmdate('Ymd\THis\Z'));
    }

    private function addDate(VTodo $todo, string $name, ?CarbonImmutable $value, bool $allDay): void
    {
        if ($value === null) {
            return;
        }
        if ($allDay && $name !== 'COMPLETED') {
            $todo->add($name, $value->format('Ymd'), ['VALUE' => 'DATE']);

            return;
        }
        $todo->add($name, $value->format('Ymd\THis\Z'));
    }

    /** @return array<string, mixed> */
    private function blankParse(): array
    {
        return [
            'uid' => null, 'summary' => null, 'description' => null, 'dtstart' => null,
            'due' => null, 'completed' => null, 'all_day' => false, 'status' => null,
            'priority' => null, 'percent_complete' => null, 'rrule' => null,
            'categories' => [], 'related_to' => null, 'alarm_minutes_before' => null, 'sequence' => 0,
        ];
    }

    private function serialize(VCalendar $cal): string
    {
        $serialized = $cal->serialize();

        return is_string($serialized) ? $serialized : '';
    }

    private function readCalendar(string $ics): ?VCalendar
    {
        try {
            $cal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return null;
        }

        return $cal instanceof VCalendar ? $cal : null;
    }

    private function firstTodo(string $ics): ?VTodo
    {
        return $this->firstTodoOf($this->readCalendar($ics));
    }

    private function firstTodoOf(?VCalendar $cal): ?VTodo
    {
        if ($cal === null) {
            return null;
        }
        foreach ($this->iter($cal->select('VTODO')) as $todo) {
            if ($todo instanceof VTodo) {
                return $todo;
            }
        }

        return null;
    }

    /** True when DUE/DTSTART carries VALUE=DATE (an all-day task). */
    private function isAllDay(VTodo $todo): bool
    {
        $prop = $todo->DUE ?? $todo->DTSTART ?? null;
        if (! $prop instanceof Property) {
            return false;
        }

        return strtoupper($this->str($prop['VALUE'] ?? null)) === 'DATE';
    }

    /** @return list<string> */
    private function parseCategories(VTodo $todo): array
    {
        $prop = $todo->CATEGORIES ?? null;
        if (! $prop instanceof Property) {
            return [];
        }
        $parts = $prop->getParts();
        $out = [];
        foreach ($parts as $part) {
            $value = trim($this->str($part));
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function normaliseCategories(mixed $value): array
    {
        $items = is_array($value) ? $value : (is_string($value) ? explode(',', $value) : []);
        $out = [];
        foreach ($items as $item) {
            $str = trim($this->str($item));
            if ($str !== '') {
                $out[] = $str;
            }
        }

        return $out;
    }

    /**
     * Minutes-before from the first VALARM's relative negative TRIGGER (e.g. -PT15M,
     * -PT1H, -P1D, -P1DT12H), else null. Each unit is matched independently so the
     * capture-group shape is trivial; the H/M matches are anchored after T so a date
     * part is never misread. Absolute (DATE-TIME) triggers yield null.
     */
    /** Minutes-before of the first VTODO's DISPLAY VALARM (for list rows), or null. */
    public function alarmMinutes(string $ics): ?int
    {
        $todo = $this->firstTodo($ics);

        return $todo !== null ? $this->parseAlarmMinutes($todo) : null;
    }

    private function parseAlarmMinutes(VTodo $todo): ?int
    {
        foreach ($this->iter($todo->select('VALARM')) as $alarm) {
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
            if ($minutes > 0) {
                return $minutes;
            }
        }

        return null;
    }

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

        return CarbonImmutable::instance($dt)->utc();
    }

    private function propIso(mixed $prop, bool $allDay): ?string
    {
        $carbon = $this->propDate($prop);
        if ($carbon === null) {
            return null;
        }

        return $allDay ? $carbon->format('Y-m-d') : $carbon->toIso8601ZuluString();
    }

    private function propInt(mixed $prop): ?int
    {
        if (! $prop instanceof Property) {
            return null;
        }
        $raw = trim($this->str($prop));

        return $raw === '' || ! ctype_digit($raw) ? null : (int) $raw;
    }

    /** Coerce a mixed to an int within [min,max], or null when absent/out of range. */
    private function intOrNull(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $int = (int) $value;

        return $int >= $min && $int <= $max ? $int : null;
    }

    private function s(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

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
