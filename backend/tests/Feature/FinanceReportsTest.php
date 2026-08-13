<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\FinancePartner;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\CategorySuggester;
use App\Services\Finance\FinanceDuplicates;
use App\Services\Finance\FinanceReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the server-side finance analytics to the exact figures the client's
 * finance-stats.js produces (cent-for-cent), so switching the UI to the server
 * never shifts a VAT number. Also covers owner isolation + the read-only
 * duplicate detection and merchant->category suggestions.
 */
class FinanceReportsTest extends TestCase
{
    use RefreshDatabase;

    private function seedInvoices(User $user): void
    {
        $this->actingAs($user);
        // Line-based invoice: net 100, vat 19, gross 119 (rate 19), Q1.
        Invoice::create([
            'number' => '1', 'seq' => 1, 'year' => 2026, 'status' => 'sent',
            'issue_date' => '2026-03-15', 'imported' => false, 'currency' => 'EUR',
            'gross' => 119, 'net' => 100, 'vat' => 19,
            'customer' => ['name' => 'ACME'],
            'lines' => [['qty' => 1, 'unitPrice' => 100, 'vatRate' => 19]],
        ]);
        // Imported invoice: exact gross 70.93 @ 19% -> vat 11.33, net 59.60, Q2.
        Invoice::create([
            'number' => '2', 'seq' => 2, 'year' => 2026, 'status' => 'paid',
            'issue_date' => '2026-06-01', 'imported' => true, 'vat_rate' => 19, 'currency' => 'EUR',
            'gross' => 70.93, 'net' => 59.60, 'vat' => 11.33,
            'customer' => ['name' => 'Beta'],
            'lines' => [],
        ]);
        // A draft (must be excluded from realized revenue).
        Invoice::create([
            'number' => null, 'year' => 2026, 'status' => 'draft',
            'issue_date' => '2026-04-01', 'imported' => false, 'currency' => 'EUR',
            'gross' => 500, 'net' => 500, 'vat' => 0,
            'customer' => ['name' => 'DraftCo'],
            'lines' => [['qty' => 1, 'unitPrice' => 500, 'vatRate' => 0]],
        ]);
    }

    public function test_vat_return_matches_the_client_figures(): void
    {
        $user = User::factory()->create();
        $this->seedInvoices($user);

        $vat = app(FinanceReports::class)->vatReturn(2026);

        // Imported inv: gross 70.93 @19% -> vat round(70.93*19/119)=11.32, net 59.61.
        // Totals: net 100+59.61=159.61, vat 19+11.32=30.32, gross 189.93.
        $this->assertSame(2026, $vat['year']);
        $this->assertSame(2, $vat['count']);              // draft excluded
        $this->assertEqualsWithDelta(159.61, $vat['net'], 0.001);
        $this->assertEqualsWithDelta(30.32, $vat['vat'], 0.001);
        $this->assertEqualsWithDelta(189.93, $vat['gross'], 0.001);
        // Single rate bucket (19%): vat 30.32, net derived from vat = 100 + 11.32/0.19.
        $this->assertCount(1, $vat['byRate']);
        $this->assertEqualsWithDelta(19.0, $vat['byRate'][0]['rate'], 0.001);
        $this->assertEqualsWithDelta(30.32, $vat['byRate'][0]['vat'], 0.001);
        $this->assertEqualsWithDelta(159.58, $vat['byRate'][0]['net'], 0.01);
        // Quarters: Q1 net 100/vat 19, Q2 net 59.61/vat 11.32.
        $this->assertEqualsWithDelta(100.0, $vat['quarters'][0]['net'], 0.001);
        $this->assertEqualsWithDelta(19.0, $vat['quarters'][0]['vat'], 0.001);
        $this->assertEqualsWithDelta(59.61, $vat['quarters'][1]['net'], 0.001);
        $this->assertEqualsWithDelta(11.32, $vat['quarters'][1]['vat'], 0.001);
    }

    public function test_year_kpis_and_active_years(): void
    {
        $user = User::factory()->create();
        $this->seedInvoices($user);

        $reports = app(FinanceReports::class);
        $kpis = $reports->yearKpis(2026);
        $this->assertEqualsWithDelta(159.61, $kpis['net'], 0.001);
        $this->assertSame(2, $kpis['count']);
        // avg = net/count = 79.805 — a half-cent boundary; allow ±1 cent since
        // display-only (JS Math.round-with-EPSILON vs PHP round can split .xx5).
        $this->assertEqualsWithDelta(79.81, $kpis['avg'], 0.02);
        $this->assertSame(2, $kpis['customers']);   // ACME + Beta (draft excluded)
        $this->assertNull($kpis['growthPct']);       // no 2025 revenue

        $this->assertSame([2026], $reports->activeYears());
    }

    public function test_reports_are_owner_scoped(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->seedInvoices($a);

        $this->actingAs($b);
        $vat = app(FinanceReports::class)->vatReturn(2026);
        $this->assertSame(0, $vat['count']);
        $this->assertEqualsWithDelta(0.0, $vat['net'], 0.001);
        $this->assertSame([], app(FinanceReports::class)->activeYears());
    }

    public function test_duplicate_detection_flags_same_date_amount_customer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        // Distinct numbers (the partial unique index forbids a repeated active
        // number/year), same date + gross + customer -> a near-duplicate the
        // number index can't catch. This is the case worth surfacing.
        Invoice::create(['number' => '5', 'year' => 2026, 'status' => 'sent', 'issue_date' => '2026-01-01', 'gross' => 42, 'net' => 42, 'vat' => 0, 'imported' => false, 'currency' => 'EUR', 'customer' => ['name' => 'ACME'], 'lines' => []]);
        Invoice::create(['number' => '6', 'year' => 2026, 'status' => 'sent', 'issue_date' => '2026-01-01', 'gross' => 42, 'net' => 42, 'vat' => 0, 'imported' => false, 'currency' => 'EUR', 'customer' => ['name' => 'ACME'], 'lines' => []]);

        $dupes = app(FinanceDuplicates::class)->detect();
        $groups = array_filter($dupes['invoices'], fn (array $g): bool => $g['reason'] === 'same_date_amount_customer');
        $this->assertNotEmpty($groups);
        $this->assertCount(2, array_values($groups)[0]['ids']);
    }

    public function test_category_suggestion_from_partner_rule(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        FinancePartner::create(['name' => 'Netcup GmbH', 'category' => 'Hosting', 'kind' => 'business']);
        $pm = PaymentMethod::create(['type' => 'bank', 'name' => 'Main', 'business' => true]);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-05-01', 'amount' => -9.99,
            'vat_cat' => '', 'counterparty' => 'NETCUP Deutschland', 'sig' => 'sig-1',
        ]);

        $suggestions = app(CategorySuggester::class)->suggestions();
        $this->assertCount(1, $suggestions);
        $this->assertSame('Hosting', $suggestions[0]['suggested_category']);
    }
}
