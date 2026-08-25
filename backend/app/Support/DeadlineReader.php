<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Reads deadlines out of extracted document text.
 *
 * Pure: text in, findings out. No database, no side effects — so the rules can
 * be tested against real wording instead of being guessed at.
 *
 * The rule that shapes everything here: a date only counts when a WORD nearby
 * says what it means. A document is full of dates (invoice date, booking date,
 * period covered) and reporting them all as deadlines would drown the one that
 * matters. So the label leads and the date follows, on the same line, never
 * across a line break — the same discipline the receipt reader learned the hard
 * way (a value on the next line belongs to a different field).
 *
 * Each finding carries the sentence it came from, because a reader who cannot
 * see why something was found cannot judge whether it is right.
 */
final class DeadlineReader
{
    /**
     * Label => kind. Longest, most specific wording first: "kündigungsfrist"
     * must not be swallowed by a looser "frist" rule.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'kündigungsfrist' => 'notice',
        'kuendigungsfrist' => 'notice',
        'kündigung bis' => 'notice',
        'kündbar bis' => 'notice',
        'notice period' => 'notice',
        'cancel by' => 'notice',
        'vertragsende' => 'contract_end',
        'vertragslaufzeit bis' => 'contract_end',
        'laufzeit bis' => 'contract_end',
        'mindestlaufzeit bis' => 'contract_end',
        'contract ends' => 'contract_end',
        'contract end' => 'contract_end',
        'garantie bis' => 'warranty',
        'gewährleistung bis' => 'warranty',
        'garantie läuft' => 'warranty',
        'warranty until' => 'warranty',
        'warranty expires' => 'warranty',
        'gültig bis' => 'expiry',
        'gueltig bis' => 'expiry',
        'läuft ab am' => 'expiry',
        'läuft ab' => 'expiry',
        'ablaufdatum' => 'expiry',
        'verfällt am' => 'expiry',
        'valid until' => 'expiry',
        'valid through' => 'expiry',
        'expires on' => 'expiry',
        'expiry date' => 'expiry',
        'expiration date' => 'expiry',
        'nächste hauptuntersuchung' => 'expiry',
        'hu bis' => 'expiry',
    ];

    /** Beyond this a "deadline" is almost certainly a misread. */
    private const MAX_YEARS_AHEAD = 30;

    /**
     * @return list<array{due_on: string, kind: string, evidence: string}>
     */
    public function read(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $found = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s+/u', ' ', (string) $line));
            if ($line === '' || mb_strlen($line) > 400) {
                continue; // a 400-character "line" is a paragraph; labels do not live there
            }
            $lower = mb_strtolower($line);

            foreach (self::LABELS as $label => $kind) {
                $at = mb_strpos($lower, $label);
                if ($at === false) {
                    continue;
                }
                // Only what follows the label, so an invoice date printed before
                // "gültig bis" is not mistaken for the deadline.
                $after = mb_substr($line, $at + mb_strlen($label), 60);
                $date = $this->firstDate($after);
                if ($date === null) {
                    continue;
                }
                // Keyed by date, so two labels that describe the same deadline
                // ("Mindestlaufzeit bis" and "Laufzeit bis" on one line) collapse
                // into one finding, and the more specific label — first in the
                // list — is the one that names its kind. No break: a contract line
                // genuinely carries two dates ("Vertragsende ... Kündigungsfrist
                // ...") and both are deadlines.
                $found[$date->toDateString()] ??= [
                    'due_on' => $date->toDateString(),
                    'kind' => $kind,
                    'evidence' => mb_substr($line, 0, 500),
                ];
            }
        }

        return array_values($found);
    }

    /** The first plausible calendar date in a short stretch of text. */
    private function firstDate(string $text): ?Carbon
    {
        // 31.12.2027 / 31-12-2027 / 31/12/2027 (day first — German documents)
        if (preg_match('/(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})/u', $text, $m) === 1) {
            return $this->makeDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }
        // 2027-12-31
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/u', $text, $m) === 1) {
            return $this->makeDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        // 31. Dezember 2027 / December 31, 2027
        $months = $this->monthNames();
        $names = implode('|', array_keys($months));
        if (preg_match('/(\d{1,2})\.?\s*('.$names.')\s*(\d{4})/ui', $text, $m) === 1) {
            return $this->makeDate((int) $m[3], $months[mb_strtolower($m[2])], (int) $m[1]);
        }
        if (preg_match('/('.$names.')\s+(\d{1,2}),?\s*(\d{4})/ui', $text, $m) === 1) {
            return $this->makeDate((int) $m[3], $months[mb_strtolower($m[1])], (int) $m[2]);
        }
        // 12/2027 — a month-end deadline, common on cards and certificates.
        if (preg_match('/(?<!\d)(\d{1,2})\/(\d{4})(?!\d)/u', $text, $m) === 1) {
            $date = $this->makeDate((int) $m[2], (int) $m[1], 1);

            return $date?->endOfMonth();
        }

        return null;
    }

    /** @return array<string, int> */
    private function monthNames(): array
    {
        return [
            'januar' => 1, 'january' => 1, 'jan' => 1,
            'februar' => 2, 'february' => 2, 'feb' => 2,
            'märz' => 3, 'maerz' => 3, 'march' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'mai' => 5, 'may' => 5,
            'juni' => 6, 'june' => 6, 'jun' => 6,
            'juli' => 7, 'july' => 7, 'jul' => 7,
            'august' => 8, 'aug' => 8,
            'september' => 9, 'sep' => 9, 'sept' => 9,
            'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11,
            'dezember' => 12, 'december' => 12, 'dez' => 12, 'dec' => 12,
        ];
    }

    /** A real calendar date within a believable range, or nothing. */
    private function makeDate(int $year, int $month, int $day): ?Carbon
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }
        if (! checkdate($month, $day, $year)) {
            return null; // 31 February is a misread, not a deadline
        }
        $date = Carbon::createFromDate($year, $month, $day)->startOfDay();
        // A deadline decades out is a scanning artefact; one in the distant past
        // is a period covered, not something to be reminded about.
        if ($date->year > (int) now()->year + self::MAX_YEARS_AHEAD || $date->year < (int) now()->year - 5) {
            return null;
        }

        return $date;
    }
}
