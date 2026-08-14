<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Invoice;

/**
 * Read-only invoice numbering gap detection (GoBD). Mirrors the historic
 * client-side algorithm (removed in the Alpine→Vue migration): a number in
 * `YYYY-NNNN` form resets per calendar year; every other format (bare
 * integers, `R-NNNNN`, …) is treated as ONE continuous ordinal sequence
 * spanning all years — the ordinal is the last run of digits in the string,
 * so a format switch mid-sequence (e.g. `R-00126` → `2025-1`) does not itself
 * count as a gap, only genuine missing ordinals within a regime do. Reports
 * only — never mutates a row; the owner decides whether a gap is a missing
 * historical import or a real numbering problem.
 */
class FinanceNumberGaps
{
    private const YYYY_NNNN = '/^(\d{4})-(\d+)$/';

    private const TRAILING_DIGITS = '/(\d+)(?!.*\d)/';

    /**
     * @return list<array{group: string, missing: list<string>, min: string, max: string, count: int}>
     */
    public function detect(): array
    {
        /** @var array<string, list<array{ordinal:int, sample:string, prefix:string, width:int}>> $byGroup */
        $byGroup = [];

        foreach (Invoice::query()->whereNotNull('number')->get(['number']) as $inv) {
            $number = trim((string) $inv->number);
            if ($number === '') {
                continue;
            }

            if (preg_match(self::YYYY_NNNN, $number, $m) === 1) {
                $group = 'year:'.$m[1];
                $prefix = $m[1].'-';
                $digits = $m[2];
            } elseif (preg_match(self::TRAILING_DIGITS, $number, $m, PREG_OFFSET_CAPTURE) === 1) {
                $group = 'sequence';
                $digits = $m[1][0];
                $prefix = substr($number, 0, (int) $m[1][1]);
            } else {
                continue; // no numeric ordinal to reason about (e.g. a free-text legacy number)
            }

            $byGroup[$group][] = [
                'ordinal' => (int) $digits,
                'sample' => $number,
                'prefix' => $prefix,
                // Zero-pad missing numbers to match this sample's digit width, but only
                // when the sample itself was zero-padded (leading zero + >1 digit) —
                // otherwise render the bare integer.
                'width' => (strlen($digits) > 1 && $digits[0] === '0') ? strlen($digits) : 0,
            ];
        }

        $out = [];
        foreach ($byGroup as $group => $entries) {
            if (count($entries) < 2) {
                continue;
            }
            usort($entries, fn (array $a, array $b): int => $a['ordinal'] <=> $b['ordinal']);
            $present = array_unique(array_column($entries, 'ordinal'));
            $last = $entries[count($entries) - 1];
            $min = (int) $entries[0]['ordinal'];
            $max = (int) $last['ordinal'];
            $missing = [];
            for ($n = $min; $n <= $max; $n++) {
                if (! in_array($n, $present, true)) {
                    $digits = $last['width'] > 0 ? str_pad((string) $n, $last['width'], '0', STR_PAD_LEFT) : (string) $n;
                    $missing[] = $last['prefix'].$digits;
                }
            }
            if ($missing !== []) {
                $out[] = [
                    'group' => $group,
                    'missing' => $missing,
                    'min' => $entries[0]['sample'],
                    'max' => $last['sample'],
                    'count' => count($missing),
                ];
            }
        }

        return $out;
    }
}
