<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Domain\Shared;

use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Exception\InvalidQuantity;
use App\Modules\Finance\Domain\Shared\Rounding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalQuantityTest extends TestCase
{
    #[DataProvider('quantities')]
    public function test_it_scales_canonical_decimal_quantities_exactly(string $quantity, int $scaled): void
    {
        $this->assertSame($scaled, DecimalQuantity::fromString($quantity)->scaled());
    }

    /** @return iterable<string, array{string, int}> */
    public static function quantities(): iterable
    {
        yield 'whole' => ['1', 10_000];
        yield 'one decimal place' => ['1.5', 15_000];
        yield 'smallest scale increment' => ['0.0001', 1];
        yield 'negative quantity' => ['-12.3456', -123_456];
        yield 'smallest signed integer value' => ['-922337203685477.5808', PHP_INT_MIN];
    }

    #[DataProvider('invalidQuantities')]
    public function test_it_rejects_noncanonical_or_overprecise_quantities(string $quantity): void
    {
        $this->expectException(InvalidQuantity::class);

        DecimalQuantity::fromString($quantity);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidQuantities(): iterable
    {
        yield 'exponent notation' => ['1e2'];
        yield 'comma decimal' => ['1,5'];
        yield 'five fraction digits' => ['1.00001'];
    }

    #[DataProvider('roundingCases')]
    public function test_it_rounds_half_values_away_from_zero(int $numerator, int $denominator, int $rounded): void
    {
        $this->assertSame($rounded, Rounding::halfAwayFromZero($numerator, $denominator));
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function roundingCases(): iterable
    {
        yield 'positive fraction below half' => [14, 10, 1];
        yield 'positive half' => [15, 10, 2];
        yield 'negative half' => [-15, 10, -2];
        yield 'negative fraction beyond half' => [-16, 10, -2];
    }
}
