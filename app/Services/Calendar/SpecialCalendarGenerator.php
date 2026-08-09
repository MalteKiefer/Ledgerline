<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Services\Contacts\VCardService;
use Carbon\CarbonImmutable;

/**
 * Generates the events of a "special" (read-only) calendar as REAL calendar_events
 * rows, so they render in the UI and sync via CalDAV like any other event.
 *
 *  - birthdays: one all-day, FREQ=YEARLY event per owner contact that has a BDAY.
 *  - holidays:  German NATIONAL (bundeseinheitliche) public holidays as one all-day
 *               event per holiday per year over [currentYear-1 .. currentYear+3].
 *               Computed locally (no composer dependency): fixed dates + Easter-based
 *               dates via the Gauss/Meeus/Butcher algorithm. Bundesland/country
 *               selection is future scope — national only for now.
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

    /** German national public holidays across the rolling window. */
    private function generateHolidays(Calendar $calendar): int
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
