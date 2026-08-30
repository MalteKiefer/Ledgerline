<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Domain\Shared;

use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;
use App\Modules\Finance\Domain\Shared\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('decimalAmounts')]
    public function test_it_parses_decimal_amounts_into_exact_minor_units(string $amount, int $minor): void
    {
        $money = Money::fromDecimal($amount, 'eur');

        $this->assertSame($minor, $money->minor());
        $this->assertSame('EUR', $money->currency());
    }

    /** @return iterable<string, array{string, int}> */
    public static function decimalAmounts(): iterable
    {
        yield 'zero' => ['0', 0];
        yield 'one cent' => ['0.01', 1];
        yield 'whole amount with fraction' => ['119.00', 11_900];
        yield 'negative one cent' => ['-0.01', -1];
        yield 'largest decimal(14,2) value' => ['999999999999.99', 99_999_999_999_999];
    }

    #[DataProvider('invalidDecimals')]
    public function test_it_rejects_noncanonical_or_out_of_range_decimal_amounts(string $amount): void
    {
        $this->expectException(InvalidMoney::class);

        Money::fromDecimal($amount, 'EUR');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDecimals(): iterable
    {
        yield 'comma decimal' => ['119,00'];
        yield 'exponent notation' => ['1e2'];
        yield 'three fraction digits' => ['1.001'];
        yield 'outside decimal(14,2) range' => ['1000000000000.00'];
    }

    #[DataProvider('invalidCurrencies')]
    public function test_it_rejects_invalid_iso_currency_codes(string $currency): void
    {
        $this->expectException(InvalidMoney::class);

        Money::fromDecimal('1.00', $currency);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCurrencies(): iterable
    {
        yield 'too short' => ['EU'];
        yield 'too long' => ['EURO'];
        yield 'contains a digit' => ['E1R'];
    }

    public function test_it_rejects_minor_units_outside_the_supported_database_range(): void
    {
        $this->expectException(InvalidMoney::class);

        Money::fromMinor(100_000_000_000_000, 'EUR');
    }

    public function test_it_adds_and_subtracts_matching_currencies_exactly(): void
    {
        $subtotal = Money::fromDecimal('119.00', 'EUR')->add(Money::fromDecimal('0.01', 'EUR'));

        $this->assertSame(11_901, $subtotal->minor());
        $this->assertSame(11_900, $subtotal->subtract(Money::fromMinor(1, 'EUR'))->minor());
    }

    public function test_it_rejects_arithmetic_results_outside_the_supported_database_range(): void
    {
        $this->expectException(InvalidMoney::class);

        Money::fromDecimal('999999999999.99', 'EUR')->add(Money::fromMinor(1, 'EUR'));
    }

    public function test_it_rejects_cross_currency_addition(): void
    {
        $this->expectException(InvalidMoney::class);

        Money::fromMinor(1, 'EUR')->add(Money::fromMinor(1, 'USD'));
    }
}
