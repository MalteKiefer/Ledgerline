<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Domain\Shared;

use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use App\Modules\Finance\Domain\Shared\Exception\InvalidDocument;
use App\Modules\Finance\Domain\Shared\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentCalculatorTest extends TestCase
{
    public function test_it_multiplies_quantity_and_unit_price_without_floating_point_arithmetic(): void
    {
        $totals = (new DocumentCalculator)->calculate([
            $this->line('Service', '2.5', '100.00', 1900),
        ], Discount::none('EUR'));

        $this->assertTotals($totals, 25_000, 4_750, 29_750, 0);
        $this->assertBreakdown($totals, 0, 1900, 25_000, 4_750, 29_750);
    }

    public function test_it_rounds_positive_and_negative_line_net_halves_away_from_zero(): void
    {
        $positive = (new DocumentCalculator)->calculate([
            $this->line('Surcharge', '0.5', '0.01', 0),
        ], Discount::none('EUR'));
        $credit = (new DocumentCalculator)->calculate([
            $this->line('Credit', '-0.5', '0.01', 0),
        ], Discount::none('EUR'));

        $this->assertTotals($positive, 1, 0, 1, 0);
        $this->assertTotals($credit, -1, 0, -1, 0);
    }

    public function test_it_calculates_zero_seven_and_nineteen_percent_tax_groups(): void
    {
        $totals = (new DocumentCalculator)->calculate([
            $this->line('Zero rated', '1', '50.00', 0),
            $this->line('Reduced', '1', '100.00', 700),
            $this->line('Standard', '1', '100.00', 1900),
        ], Discount::none('EUR'));

        $this->assertTotals($totals, 25_000, 2_600, 27_600, 0);
        $this->assertBreakdown($totals, 0, 0, 5_000, 0, 5_000);
        $this->assertBreakdown($totals, 1, 700, 10_000, 700, 10_700);
        $this->assertBreakdown($totals, 2, 1900, 10_000, 1_900, 11_900);
    }

    public function test_it_rounds_vat_once_per_tax_rate_group(): void
    {
        $totals = (new DocumentCalculator)->calculate([
            $this->line('First', '1', '0.03', 1900),
            $this->line('Second', '1', '0.03', 1900),
        ], Discount::none('EUR'));

        $this->assertTotals($totals, 6, 1, 7, 0);
        $this->assertBreakdown($totals, 0, 1900, 6, 1, 7);
    }

    public function test_it_rejects_an_empty_document(): void
    {
        $this->expectException(InvalidDocument::class);

        (new DocumentCalculator)->calculate([], Discount::none('EUR'));
    }

    public function test_it_applies_a_percentage_discount_to_the_taxable_base(): void
    {
        $totals = (new DocumentCalculator)->calculate([
            $this->line('Service', '1', '100.00', 1900),
        ], Discount::percentBasisPoints(1000, 'EUR'));

        $this->assertTotals($totals, 9_000, 1_710, 10_710, 1_000);
        $this->assertBreakdown($totals, 0, 1900, 9_000, 1_710, 10_710);
    }

    public function test_it_distributes_a_fixed_discount_proportionally_across_tax_groups(): void
    {
        $totals = (new DocumentCalculator)->calculate([
            $this->line('Reduced', '1', '100.00', 700),
            $this->line('Standard', '1', '300.00', 1900),
        ], Discount::fixed(Money::fromDecimal('40.00', 'EUR')));

        $this->assertTotals($totals, 36_000, 5_760, 41_760, 4_000);
        $this->assertBreakdown($totals, 0, 700, 9_000, 630, 9_630);
        $this->assertBreakdown($totals, 1, 1900, 27_000, 5_130, 32_130);
    }

    public function test_it_assigns_a_remaining_discount_cent_by_tax_rate_then_original_position(): void
    {
        $totals = (new DocumentCalculator)->calculate([
            $this->line('Standard first', '1', '1.00', 1900),
            $this->line('Reduced first', '1', '1.00', 700),
            $this->line('Reduced second', '1', '1.00', 700),
        ], Discount::fixed(Money::fromDecimal('0.01', 'EUR')));

        $this->assertTotals($totals, 299, 33, 332, 1);
        $this->assertBreakdown($totals, 0, 700, 199, 14, 213);
        $this->assertBreakdown($totals, 1, 1900, 100, 19, 119);
    }

    public function test_it_allows_a_discount_equal_to_the_total_net(): void
    {
        $totals = (new DocumentCalculator)->calculate([
            $this->line('Service', '1', '100.00', 1900),
        ], Discount::fixed(Money::fromDecimal('100.00', 'EUR')));

        $this->assertTotals($totals, 0, 0, 0, 10_000);
        $this->assertBreakdown($totals, 0, 1900, 0, 0, 0);
    }

    public function test_it_rejects_a_discount_exceeding_the_total_net(): void
    {
        $this->expectException(InvalidDocument::class);

        (new DocumentCalculator)->calculate([
            $this->line('Service', '1', '100.00', 1900),
        ], Discount::fixed(Money::fromDecimal('100.01', 'EUR')));
    }

    #[DataProvider('negativeDiscounts')]
    public function test_it_rejects_negative_discounts(Discount $discount): void
    {
        $this->expectException(InvalidDocument::class);

        (new DocumentCalculator)->calculate([
            $this->line('Service', '1', '100.00', 1900),
        ], $discount);
    }

    /** @return iterable<string, array{Discount}> */
    public static function negativeDiscounts(): iterable
    {
        yield 'negative percentage' => [Discount::percentBasisPoints(-1, 'EUR')];
        yield 'negative fixed amount' => [Discount::fixed(Money::fromMinor(-1, 'EUR'))];
    }

    #[DataProvider('invalidTaxRates')]
    public function test_it_rejects_tax_rates_outside_zero_to_one_hundred_percent(int $taxRateBasisPoints): void
    {
        $this->expectException(InvalidDocument::class);

        (new DocumentCalculator)->calculate([
            $this->line('Invalid tax', '1', '1.00', $taxRateBasisPoints),
        ], Discount::none('EUR'));
    }

    /** @return iterable<string, array{int}> */
    public static function invalidTaxRates(): iterable
    {
        yield 'negative' => [-1];
        yield 'above one hundred percent' => [10_001];
    }

    public function test_it_rejects_mixed_line_currencies(): void
    {
        $this->expectException(InvalidDocument::class);

        (new DocumentCalculator)->calculate([
            $this->line('Euro', '1', '1.00', 0),
            new DocumentLine('Dollar', DecimalQuantity::fromString('1'), Money::fromDecimal('1.00', 'USD'), 0),
        ], Discount::none('EUR'));
    }

    public function test_it_rejects_a_discount_currency_different_from_the_document_currency(): void
    {
        $this->expectException(InvalidDocument::class);

        (new DocumentCalculator)->calculate([
            $this->line('Euro', '1', '1.00', 0),
        ], Discount::none('USD'));
    }

    public function test_control_totals_match_only_when_every_supplied_money_value_is_exact(): void
    {
        $totals = (new DocumentCalculator)->calculate([
            $this->line('Service', '1', '100.00', 1900),
        ], Discount::none('EUR'));

        $this->assertTrue($totals->matchesControlTotals(null, null, null));
        $this->assertTrue($totals->matchesControlTotals(
            Money::fromMinor(10_000, 'EUR'),
            Money::fromMinor(1_900, 'EUR'),
            Money::fromMinor(11_900, 'EUR'),
        ));
        $this->assertFalse($totals->matchesControlTotals(Money::fromMinor(9_999, 'EUR'), null, null));
        $this->assertFalse($totals->matchesControlTotals(null, Money::fromMinor(1_900, 'USD'), null));
        $this->assertFalse($totals->matchesControlTotals(null, null, Money::fromMinor(11_899, 'EUR')));
    }

    private function line(string $description, string $quantity, string $unitPrice, int $taxRateBasisPoints): DocumentLine
    {
        return new DocumentLine(
            $description,
            DecimalQuantity::fromString($quantity),
            Money::fromDecimal($unitPrice, 'EUR'),
            $taxRateBasisPoints,
        );
    }

    private function assertTotals(
        DocumentTotals $totals,
        int $net,
        int $vat,
        int $gross,
        int $discount,
    ): void {
        $this->assertSame($net, $totals->net->minor());
        $this->assertSame($vat, $totals->vat->minor());
        $this->assertSame($gross, $totals->gross->minor());
        $this->assertSame($discount, $totals->discount->minor());
    }

    private function assertBreakdown(
        DocumentTotals $totals,
        int $index,
        int $taxRateBasisPoints,
        int $net,
        int $vat,
        int $gross,
    ): void {
        $breakdown = $totals->taxBreakdowns[$index];

        $this->assertSame($taxRateBasisPoints, $breakdown->taxRateBasisPoints);
        $this->assertSame($net, $breakdown->net->minor());
        $this->assertSame($vat, $breakdown->vat->minor());
        $this->assertSame($gross, $breakdown->gross->minor());
    }
}
