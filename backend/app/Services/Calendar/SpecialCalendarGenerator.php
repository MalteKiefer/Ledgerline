<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Services\Contacts\VCardService;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Generates the events of a "special" (read-only) calendar as REAL calendar_events
 * rows, so they render in the UI and sync via CalDAV like any other event.
 *
 *  - birthdays:       one all-day, FREQ=YEARLY event per owner contact with a BDAY.
 *  - holidays:        public holidays for the calendar's country (default DE) +
 *                     optional subdivision, fetched from OpenHolidays over
 *                     [currentYear-1 .. currentYear+3] as one all-day event each.
 *                     If OpenHolidays is unreachable AND the calendar is DE with no
 *                     subdivision, falls back to the locally-computed German
 *                     national holidays (fixed dates + Easter-based dates via the
 *                     Gauss/Meeus/Butcher algorithm).
 *  - school_holidays: school holidays (Ferien) for the country + subdivision from
 *                     OpenHolidays — each a multi-day all-day range (e.g. Sommerferien).
 *
 * Regeneration is idempotent-ish: it clears the calendar's existing events (logging
 * CalDAV tombstones) and rebuilds from the current source, all through CalendarWriter
 * so etag/uid/synctoken + the change log stay correct.
 */
class SpecialCalendarGenerator
{
    /**
     * Neutral base year for a birthday with no year in its vCard BDAY. A leap year
     * so that a Feb 29 birthday keeps its FREQ=YEARLY anchor.
     */
    private const NEUTRAL_YEAR = 2000;

    public function __construct(
        private readonly CalendarWriter $writer,
        private readonly VCardService $vcards,
        private readonly OpenHolidaysClient $holidays,
    ) {}

    /**
     * Rebuild all generated events for a special calendar from its current source.
     * Returns the number of events created. A `normal` calendar generates nothing.
     */
    public function regenerate(Calendar $calendar): int
    {
        foreach (CalendarEvent::where('calendar_id', $calendar->id)->get() as $event) {
            $this->writer->delete($event);
        }

        return match ($calendar->kind) {
            Calendar::KIND_BIRTHDAYS => $this->generateBirthdays($calendar),
            Calendar::KIND_HOLIDAYS => $this->generateHolidays($calendar),
            Calendar::KIND_SCHOOL_HOLIDAYS => $this->generateSchoolHolidays($calendar),
            default => 0,
        };
    }

    /**
     * One all-day, yearly-recurring event per owner contact that has a birthday.
     * Contacts resolve owner-scoped via the address-book relation (auth global
     * scope), so this only ever reads the acting user's own contacts.
     */
    private function generateBirthdays(Calendar $calendar): int
    {
        $created = 0;
        $contacts = Contact::query()->whereHas('addressBook')->get(['id', 'address_book_id', 'vcard', 'fn']);

        foreach ($contacts as $contact) {
            $parsed = $this->vcards->parse($contact->vcard);
            $bday = is_string($parsed['bday'] ?? null) ? $parsed['bday'] : null;
            $md = $bday !== null ? $this->parseBirthday($bday) : null;
            if ($md === null) {
                continue;
            }

            $name = $this->contactName($parsed['fn'] ?? null, $contact->fn);
            if ($name === '') {
                continue;
            }

            $this->writer->create($calendar, [
                'summary' => __('calendar.ui.birthday_event', ['name' => $name]),
                'dtstart' => sprintf('%04d-%02d-%02d', $md['year'] ?? self::NEUTRAL_YEAR, $md['month'], $md['day']),
                'all_day' => true,
                'rrule' => 'FREQ=YEARLY',
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Public holidays for the calendar's country (+ optional subdivision) across
     * the rolling window, from OpenHolidays. If the API is unreachable AND the
     * calendar is DE with no subdivision, fall back to locally-computed German
     * national holidays.
     */
    private function generateHolidays(Calendar $calendar): int
    {
        $country = $this->country($calendar);
        $subdivision = $this->subdivision($calendar);
        [$from, $to] = $this->window();

        try {
            $rows = $this->holidays->publicHolidays($country, $subdivision, $from, $to, $this->lang());
        } catch (Throwable) {
            // Offline fallback only for the German national set.
            if ($country === 'DE' && $subdivision === null) {
                return $this->generateGermanFallback($calendar);
            }

            return 0;
        }

        return $this->createRangeEvents($calendar, $rows);
    }

    /** School holidays (Ferien) for the calendar's country + subdivision. */
    private function generateSchoolHolidays(Calendar $calendar): int
    {
        [$from, $to] = $this->window();

        try {
            $rows = $this->holidays->schoolHolidays($this->country($calendar), $this->subdivision($calendar), $from, $to, $this->lang());
        } catch (Throwable) {
            return 0;
        }

        return $this->createRangeEvents($calendar, $rows);
    }

    /**
     * Create one all-day event per normalized holiday range. A single-day holiday
     * (start == end) gets no DTEND; a multi-day Ferien range gets an all-day DTEND
     * of endDate+1 (RFC 5545 all-day DTEND is exclusive).
     *
     * @param  list<array{startDate: string, endDate: string, name: string, allDay: bool}>  $rows
     */
    private function createRangeEvents(Calendar $calendar, array $rows): int
    {
        $created = 0;
        foreach ($rows as $row) {
            $data = ['summary' => $row['name'], 'dtstart' => $row['startDate'], 'all_day' => true];
            if ($row['endDate'] > $row['startDate']) {
                $data['dtend'] = CarbonImmutable::parse($row['endDate'])->addDay()->format('Y-m-d');
            }
            $this->writer->create($calendar, $data);
            $created++;
        }

        return $created;
    }

    /** Locally-computed German NATIONAL holidays across the rolling window (offline fallback). */
    private function generateGermanFallback(Calendar $calendar): int
    {
        $current = (int) CarbonImmutable::now()->format('Y');
        $created = 0;

        for ($year = $current - 1; $year <= $current + 3; $year++) {
            foreach ($this->germanHolidays($year) as $holiday) {
                $this->writer->create($calendar, [
                    'summary' => $holiday['name'],
                    'dtstart' => $holiday['date'],
                    'all_day' => true,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /** The calendar's ISO 3166-1 alpha-2 country (default DE). */
    private function country(Calendar $calendar): string
    {
        $country = strtoupper(trim((string) ($calendar->country ?? '')));

        return $country !== '' ? $country : 'DE';
    }

    /** The calendar's OpenHolidays subdivision code, or null for the national set. */
    private function subdivision(Calendar $calendar): ?string
    {
        $subdivision = trim((string) ($calendar->subdivision ?? ''));

        return $subdivision !== '' ? $subdivision : null;
    }

    /** Names come back in the current UI language (best-effort; falls back upstream). */
    private function lang(): string
    {
        $lang = strtoupper(substr(app()->getLocale(), 0, 2));

        return $lang !== '' ? $lang : 'EN';
    }

    /**
     * The rolling [currentYear-1 .. currentYear+3] fetch window as ISO Y-m-d bounds.
     *
     * @return array{0: string, 1: string}
     */
    private function window(): array
    {
        $current = (int) CarbonImmutable::now()->format('Y');

        return [sprintf('%04d-01-01', $current - 1), sprintf('%04d-12-31', $current + 3)];
    }

    /**
     * German NATIONAL (bundeseinheitliche) public holidays for one year.
     *
     * @return list<array{date: string, name: string}>
     */
    public function germanHolidays(int $year): array
    {
        $easter = $this->easterSunday($year);
        $rel = fn (int $days): string => $easter->addDays($days)->format('Y-m-d');

        return [
            ['date' => sprintf('%04d-01-01', $year), 'name' => 'Neujahr'],
            ['date' => $rel(-2), 'name' => 'Karfreitag'],
            ['date' => $rel(1), 'name' => 'Ostermontag'],
            ['date' => sprintf('%04d-05-01', $year), 'name' => 'Tag der Arbeit'],
            ['date' => $rel(39), 'name' => 'Christi Himmelfahrt'],
            ['date' => $rel(50), 'name' => 'Pfingstmontag'],
            ['date' => sprintf('%04d-10-03', $year), 'name' => 'Tag der Deutschen Einheit'],
            ['date' => sprintf('%04d-12-25', $year), 'name' => '1. Weihnachtstag'],
            ['date' => sprintf('%04d-12-26', $year), 'name' => '2. Weihnachtstag'],
        ];
    }

    /** Easter Sunday for a Gregorian year (Gauss/Meeus/Butcher algorithm). */
    public function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::parse(sprintf('%04d-%02d-%02dT00:00:00Z', $year, $month, $day));
    }

    /**
     * Parse a vCard BDAY into month/day (+ optional year). Accepts full dates
     * (`YYYYMMDD`, `YYYY-MM-DD`, ISO) and year-omitted forms (`--MMDD`, `--MM-DD`).
     *
     * @return array{month: int, day: int, year: ?int}|null
     */
    private function parseBirthday(string $bday): ?array
    {
        $value = trim($bday);
        if (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})/', $value, $m)) {
            return $this->validMonthDay((int) $m[2], (int) $m[3], (int) $m[1]);
        }
        if (preg_match('/^--(\d{2})-?(\d{2})/', $value, $m)) {
            return $this->validMonthDay((int) $m[1], (int) $m[2], null);
        }

        return null;
    }

    /** @return array{month: int, day: int, year: ?int}|null */
    private function validMonthDay(int $month, int $day, ?int $year): ?array
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        return ['month' => $month, 'day' => $day, 'year' => $year];
    }

    /** Prefer the parsed formatted name, fall back to the denormalised column. */
    private function contactName(mixed $parsedFn, ?string $fallback): string
    {
        if (is_string($parsedFn) && trim($parsedFn) !== '') {
            return trim($parsedFn);
        }

        return trim((string) ($fallback ?? ''));
    }
}
