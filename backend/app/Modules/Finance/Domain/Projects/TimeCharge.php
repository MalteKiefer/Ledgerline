<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Projects;

use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Domain\Shared\Rounding;

final readonly class TimeCharge
{
    private const int QUANTITY_SCALE = 10_000;

    private const int MAX_ROUNDED_NUMERATOR = 999_999_999_999_994_999;

    public static function calculate(DecimalQuantity $hours, Money $hourlyRate): Money
    {
        $numerator = self::checkedNumerator($hours->scaled(), $hourlyRate->minor());
        $minor = Rounding::halfAwayFromZero($numerator, self::QUANTITY_SCALE);

        return Money::fromMinor($minor, $hourlyRate->currency());
    }

    private static function checkedNumerator(int $hoursScaled, int $hourlyRateMinor): int
    {
        if ($hoursScaled === 0 || $hourlyRateMinor === 0) {
            return 0;
        }

        if ($hoursScaled === PHP_INT_MIN || $hourlyRateMinor === PHP_INT_MIN) {
            throw new InvalidMoney('The calculated time charge exceeds the supported money range.');
        }

        $absoluteHours = abs($hoursScaled);
        $absoluteRate = abs($hourlyRateMinor);

        if ($absoluteHours > intdiv(self::MAX_ROUNDED_NUMERATOR, $absoluteRate)) {
            throw new InvalidMoney('The calculated time charge exceeds the supported money range.');
        }

        return $hoursScaled * $hourlyRateMinor;
    }
}
