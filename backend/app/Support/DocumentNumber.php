<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The number template shared by every document that carries a number.
 *
 * `YYYY`, `YY`, `MM`, `DD` come from the document's own date, and one run of `N`s
 * becomes the sequence, zero-padded to that run's width. Rendering and reading
 * back are two directions of one rule, kept together so a template that prints
 * AN-2026-0007 also parses it — an invoice and a quote must not disagree about
 * what their own number means.
 *
 * Which run: the LONGEST one, and on a tie the rightmost. Taking the first run
 * would eat the N in a prefix like `AN-` or `RN-` and turn AN-2026-0007 into
 * A7-2026-0007 — a letter silently replaced by a digit, in the one field that
 * identifies the document. A sequence also sits at the end by convention, which
 * is why a tie resolves to the right.
 *
 * That leaves one honestly ambiguous case: a template whose only runs are single
 * N's cannot say which of them is the sequence. The rightmost wins, which also
 * means a plain word containing an N is not a usable template — the sequence has
 * to be written as N's, so there is no reading under which such a word is one.
 */
class DocumentNumber
{
    /** The template used when none is configured. */
    public const DEFAULT_FORMAT = 'YYYY-NNNN';

    /** Render a template: date tokens plus a run of N's → zero-padded sequence. */
    public static function format(?string $fmt, int $seq, ?Carbon $date): string
    {
        $d = $date ?? Carbon::now();
        $out = ($fmt !== null && $fmt !== '') ? $fmt : self::DEFAULT_FORMAT;
        $out = str_replace(
            ['YYYY', 'YY', 'MM', 'DD'],
            [$d->format('Y'), $d->format('y'), $d->format('m'), $d->format('d')],
            $out,
        );
        $run = self::sequenceRun($out);
        if ($run === null) {
            return (string) $seq;
        }
        [$at, $len] = $run;

        return substr($out, 0, $at).str_pad((string) $seq, $len, '0', STR_PAD_LEFT).substr($out, $at + $len);
    }

    /**
     * Read the sequence back out of a rendered number, or null if it does not
     * match the template.
     *
     * Null matters: it is how a number from an older format, or one typed by
     * hand, is recognised as "cannot be read" rather than silently treated as
     * sequence zero.
     */
    public static function sequenceFrom(?string $fmt, string $number, ?Carbon $date): ?int
    {
        $d = $date ?? Carbon::now();
        $template = ($fmt !== null && $fmt !== '') ? $fmt : self::DEFAULT_FORMAT;
        $token = '__LEDGERLINE_SEQUENCE__';
        $run = self::sequenceRun($template);
        if ($run === null) {
            return null;
        }
        [$at, $len] = $run;
        $template = substr($template, 0, $at).$token.substr($template, $at + $len);
        $template = str_replace(
            ['YYYY', 'YY', 'MM', 'DD'],
            [$d->format('Y'), $d->format('y'), $d->format('m'), $d->format('d')],
            $template,
        );
        $pattern = '/^'.str_replace(preg_quote($token, '/'), '(\\d+)', preg_quote($template, '/')).'$/D';
        if (preg_match($pattern, $number, $matches) !== 1 || ! ctype_digit($matches[1])) {
            return null;
        }

        $seq = (int) $matches[1];

        return $seq > 0 ? $seq : null;
    }

    /**
     * Where the sequence sits in a template: [offset, length] of the longest run
     * of `N`s, rightmost on a tie. Null when the template has no run at all.
     *
     * @return array{0: int, 1: int}|null
     */
    private static function sequenceRun(string $template): ?array
    {
        // preg_match_all returns the number of runs, so anything but a positive
        // count means the template names no sequence at all.
        $found = preg_match_all('/N+/', $template, $m, PREG_OFFSET_CAPTURE);
        if (! is_int($found) || $found < 1) {
            return null;
        }
        $best = null;
        foreach ($m[0] as $match) {
            $len = strlen((string) $match[0]);
            // >= so a later run of the same length wins: a sequence belongs at
            // the end, and a prefix letter must not be mistaken for one.
            if ($best === null || $len >= $best[1]) {
                $best = [(int) $match[1], $len];
            }
        }

        return $best;
    }
}
