<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\FinanceReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The global invoice-level discount (Rabatt) applied by FinanceReports::invoiceTotals
 * must reduce the net taxable base and the VAT proportionally — cent-identical to the
 * client's finance-stats.js (locked by the vitest of the same figures). Also covers a
 * credit note reversing an invoice (incl. its discount) in the VAT return.
 */
class InvoiceDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function reports(): FinanceReports
    {
        return app(FinanceReports::class);
    }

    private function make(User $user, array $attrs): Invoice
    {
        $this->actingAs($user);

        return Invoice::create(array_merge([
            'status' => 'sent', 'issue_date' => '2026-02-01', 'imported' => false, 'currency' => 'EUR',
            'customer' => ['name' => 'ACME'],
        ], $attrs));
    }

    public function test_percent_discount_reduces_net_and_vat(): void
    {
        $user = User::factory()->create();
        $inv = $this->make($user, [
            'lines' => [['qty' => 1, 'unitPrice' => 100, 'vatRate' => 19]],
            'discount_type' => 'percent', 'discount_value' => 10,
        ]);

        $t = $this->reports()->invoiceTotals($inv);
        // 100 − 10% = 90 net; 90 × 19% = 17.10 VAT; 107.10 gross.
        $this->assertEqualsWithDelta(90.0, $t['net'], 0.001);
        $this->assertEqualsWithDelta(17.10, $t['vat'], 0.001);
        $this->assertEqualsWithDelta(107.10, $t['gross'], 0.001);
    }

    public function test_amount_discount_spread_across_mixed_rates(): void
    {
        $user = User::factory()->create();
        $inv = $this->make($user, [
            'lines' => [['qty' => 1, 'unitPrice' => 100, 'vatRate' => 19], ['qty' => 1, 'unitPrice' => 100, 'vatRate' => 7]],
            'discount_type' => 'amount', 'discount_value' => 20,
        ]);

        $t = $this->reports()->invoiceTotals($inv);
        // net 200 − 20 = 180; VAT = 90×19% + 90×7% = 17.10 + 6.30 = 23.40; gross 203.40.
        $this->assertEqualsWithDelta(180.0, $t['net'], 0.001);
        $this->assertEqualsWithDelta(23.40, $t['vat'], 0.001);
        $this->assertEqualsWithDelta(203.40, $t['gross'], 0.001);
    }

    public function test_no_discount_is_unchanged(): void
    {
        $user = User::factory()->create();
        $inv = $this->make($user, ['lines' => [['qty' => 2, 'unitPrice' => 50, 'vatRate' => 19]]]);

        $t = $this->reports()->invoiceTotals($inv);
        $this->assertEqualsWithDelta(100.0, $t['net'], 0.001);
        $this->assertEqualsWithDelta(19.0, $t['vat'], 0.001);
    }

    public function test_credit_note_reduces_the_vat_return(): void
    {
        $user = User::factory()->create();
        // A discounted invoice + its exact credit note (negated lines, same discount).
        $this->make($user, [
            'number' => '1', 'year' => 2026, 'status' => 'sent',
            'lines' => [['qty' => 1, 'unitPrice' => 100, 'vatRate' => 19]],
            'discount_type' => 'percent', 'discount_value' => 10,
        ]);
        $this->make($user, [
            'number' => '2', 'year' => 2026, 'status' => 'sent', 'type' => 'credit_note',
            'lines' => [['qty' => 1, 'unitPrice' => -100, 'vatRate' => 19]],
            'discount_type' => 'percent', 'discount_value' => 10,
        ]);

        $vat = $this->reports()->vatReturn(2026);
        $this->assertSame(2, $vat['count']);
        $this->assertEqualsWithDelta(0.0, $vat['net'], 0.001);
        $this->assertEqualsWithDelta(0.0, $vat['vat'], 0.001);
    }
}
