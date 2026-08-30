<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Domain\Projects;

use App\Modules\Finance\Domain\Projects\TimeCharge;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;
use App\Modules\Finance\Domain\Shared\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeChargeTest extends TestCase
{
    #[DataProvider('exactCharges')]
    public function test_it_calculates_time_charges_with_integer_only_half_away_from_zero_rounding(
        string $hours,
        int $hourlyRateMinor,
        int $expectedMinor,
    ): void {
        $charge = TimeCharge::calculate(
            DecimalQuantity::fromString($hours),
            Money::fromMinor($hourlyRateMinor, 'EUR'),
        );

        $this->assertSame($expectedMinor, $charge->minor());
    }

    /** @return iterable<string, array{string, int, int}> */
    public static function exactCharges(): iterable
    {
        yield 'two and a half hours at one hundred euros' => ['2.5000', 10_000, 25_000];
        yield 'one third hour rounded to forty euros' => ['0.3333', 12_000, 4_000];
        yield 'negative correction hours' => ['-0.3333', 12_000, -4_000];
        yield 'positive exact half cent' => ['0.0001', 5_000, 1];
        yield 'negative exact half cent' => ['-0.0001', 5_000, -1];
    }

    public function test_it_preserves_the_hourly_rate_currency(): void
    {
        $charge = TimeCharge::calculate(
            DecimalQuantity::fromString('1.0000'),
            Money::fromMinor(12_345, 'usd'),
        );

        $this->assertSame(12_345, $charge->minor());
        $this->assertSame('USD', $charge->currency());
    }

    public function test_it_rejects_a_calculation_that_would_overflow_the_supported_money_range(): void
    {
        $this->expectException(InvalidMoney::class);

        TimeCharge::calculate(
            DecimalQuantity::fromString('10.0000'),
            Money::fromMinor(99_999_999_999_999, 'EUR'),
        );
    }
}
