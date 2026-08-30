<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared;

use InvalidArgumentException;

final class Rounding
{
    public static function halfAwayFromZero(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Denominator must be positive.');
        }

        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if ($remainder === 0) {
            return $quotient;
        }

        $absoluteRemainder = abs($remainder);
        $half = intdiv($denominator, 2);
        $rounds = $denominator % 2 === 0 ? $absoluteRemainder >= $half : $absoluteRemainder > $half;

        if (! $rounds) {
            return $quotient;
        }

        return $numerator > 0 ? $quotient + 1 : $quotient - 1;
    }
}
