<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\FinanceProject;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Finance\FinanceReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tax core (R3a): §19 Kleinunternehmer flow, the unified USt-Voranmeldung
 * (output VAT from invoices − input VAT from bank transactions = Zahllast), and
 * the simplified EÜR (income − expenses = profit). Cent-exact + owner-scoped;
 * both new endpoints require module:finance and a bearer token on the API.
 */
class TaxReportsTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('phone', ['device'])->plainTextToken;
    }

    /**
     * Output invoice (net 200 @19% → vat 38) + input expense (gross 119 @19% → vat 19).
     *
     * The invoice is PAID and booked to a payment date: vatAdvanceReturn()
     * defaults to Ist-Versteuerung, which only counts what has actually been
     * received. An unpaid invoice legitimately contributes nothing there.
     */
    private function seedData(User $user): void
    {
        $this->actingAs($user);
        Invoice::create([
            'number' => '1', 'seq' => 1, 'year' => 2026, 'status' => 'paid',
            'issue_date' => '2026-02-10', 'paid_at' => '2026-02-20', 'imported' => false, 'currency' => 'EUR',
            'gross' => 238, 'net' => 200, 'vat' => 38,
            'customer' => ['name' => 'ACME'],
            'lines' => [['qty' => 1, 'unitPrice' => 200, 'vatRate' => 19]],
        ]);
        $pm = PaymentMethod::create(['type' => 'bank', 'name' => 'Main', 'business' => true]);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-02-15', 'amount' => -119,
            'vat_cat' => '19', 'counterparty' => 'Hoster', 'sig' => 'sig-exp',
        ]);
    }

    public function test_vat_advance_combines_output_and_input_into_payable(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);

        $r = app(FinanceReports::class)->vatAdvanceReturn(2026);

        $this->assertSame(2026, $r['year']);
        $this->assertNull($r['quarter']);
        $this->assertEqualsWithDelta(200.0, $r['net'], 0.001);
        $this->assertEqualsWithDelta(38.0, $r['outputVat'], 0.001);
        $this->assertEqualsWithDelta(19.0, $r['inputVat'], 0.001);
        $this->assertEqualsWithDelta(19.0, $r['payable'], 0.001); // 38 − 19
        $this->assertFalse($r['small_business']);

        // Everything is Q1 → the same when scoped to quarter 1, zero for quarter 2.
        $this->assertEqualsWithDelta(19.0, app(FinanceReports::class)->vatAdvanceReturn(2026, 1)['payable'], 0.001);
        $q2 = app(FinanceReports::class)->vatAdvanceReturn(2026, 2);
        $this->assertEqualsWithDelta(0.0, $q2['outputVat'], 0.001);
        $this->assertEqualsWithDelta(0.0, $q2['inputVat'], 0.001);
        $this->assertEqualsWithDelta(0.0, $q2['payable'], 0.001);
    }

    public function test_small_business_zeroes_output_vat(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);
        UserSetting::for($user->id)->update(['small_business' => true]);

        $reports = app(FinanceReports::class);
        $r = $reports->vatAdvanceReturn(2026);
        $this->assertTrue($r['small_business']);
        $this->assertEqualsWithDelta(0.0, $r['outputVat'], 0.001);   // §19: no VAT collected
        $this->assertEqualsWithDelta(238.0, $r['net'], 0.001);        // turnover = gross
        $this->assertEqualsWithDelta(-19.0, $r['payable'], 0.001);    // only Vorsteuer

        // The VAT return itself reports zero VAT owed for a KU (turnover only).
        $vat = $reports->vatReturn(2026);
        $this->assertEqualsWithDelta(0.0, $vat['vat'], 0.001);
        $this->assertEqualsWithDelta(238.0, $vat['net'], 0.001);
    }

    public function test_euer_computes_income_minus_expenses_equals_profit(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);
        // A manual project expense of 50 in the same year.
        FinanceProject::create([
            'name' => 'Build', 'kind' => 'business',
            'expenses' => [['id' => 'e1', 'amount' => 50, 'date' => '2026-03-01', 'category' => 'Material']],
        ]);

        $e = app(FinanceReports::class)->euer(2026);

        $this->assertSame(2026, $e['year']);
        $this->assertEqualsWithDelta(200.0, $e['income']['total'], 0.001);      // invoice net
        $this->assertEqualsWithDelta(150.0, $e['expenses']['total'], 0.001);    // 100 (tx net) + 50 (project)
        $this->assertEqualsWithDelta(50.0, $e['profit'], 0.001);                // 200 − 150
        $this->assertFalse($e['small_business']);
        // Income grouped by customer, expenses by category.
        $this->assertSame('ACME', $e['income']['byCategory'][0]['name']);
        $this->assertNotEmpty($e['expenses']['byCategory']);
    }

    /**
     * A project ledger row can be a Zubuchung (`direction: 'in'` — a refund
     * booked on the project). It must REDUCE the expense side, not add to it;
     * a legacy row without `direction` stays an outflow.
     */
    public function test_euer_treats_a_project_credit_row_as_a_reduction(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);
        FinanceProject::create([
            'name' => 'Build', 'kind' => 'business',
            'expenses' => [
                ['id' => 'e1', 'amount' => 250, 'direction' => 'out', 'date' => '2026-03-01', 'category' => 'Material'],
                ['id' => 'e2', 'amount' => 70, 'direction' => 'in', 'date' => '2026-03-05', 'category' => 'Material'],
                ['id' => 'e3', 'amount' => 30, 'date' => '2026-03-09', 'category' => 'Material'], // legacy: no direction
            ],
        ]);

        $e = app(FinanceReports::class)->euer(2026);

        // 100 (tx net) + 250 − 70 + 30 = 310
        $this->assertEqualsWithDelta(310.0, $e['expenses']['total'], 0.001);
        $this->assertEqualsWithDelta(-110.0, $e['profit'], 0.001); // 200 − 310
    }

    public function test_reports_are_owner_scoped(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->seedData($a);

        $this->actingAs($b);
        $this->assertEqualsWithDelta(0.0, app(FinanceReports::class)->vatAdvanceReturn(2026)['payable'], 0.001);
        $this->assertEqualsWithDelta(0.0, app(FinanceReports::class)->euer(2026)['profit'], 0.001);
    }

    public function test_endpoints_require_bearer_and_module_on_the_api(): void
    {
        $this->getJson(route('api.finance.reports.vat-advance'))->assertUnauthorized();
        $this->getJson(route('api.finance.reports.euer'))->assertUnauthorized();

        $blocked = User::factory()->create(['role' => 'user', 'modules' => ['reports']]);
        $this->getJson(route('api.finance.reports.vat-advance'), ['Authorization' => 'Bearer '.$this->token($blocked)])
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $allowed = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $this->getJson(route('api.finance.reports.vat-advance'), ['Authorization' => 'Bearer '.$this->token($allowed)])
            ->assertOk()->assertJsonStructure(['year', 'outputVat', 'inputVat', 'payable']);
        $this->getJson(route('api.finance.reports.euer'), ['Authorization' => 'Bearer '.$this->token($allowed)])
            ->assertOk()->assertJsonStructure(['year', 'income' => ['total'], 'expenses' => ['total'], 'profit']);
    }

    public function test_web_endpoints_return_json(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson(route('finance.reports.vat-advance', ['year' => 2026]))->assertOk()->assertJsonPath('year', 2026);
        $this->getJson(route('finance.reports.euer', ['year' => 2026]))->assertOk()->assertJsonPath('year', 2026);
    }

    public function test_small_business_flag_persists_via_web_and_api_company(): void
    {
        // Web: toggle on, then off (the toggle is always rendered on the form).
        $user = $this->signIn();
        $this->put(route('settings.company.update'), ['small_business' => 1])->assertRedirect();
        $this->assertTrue(UserSetting::for($user->id)->fresh()->small_business);
        $this->put(route('settings.company.update'), [])->assertRedirect();
        $this->assertFalse(UserSetting::for($user->id)->fresh()->small_business);

        // API: partial update sets it and echoes it back (fresh guard — no web session bleed).
        $this->app['auth']->forgetGuards();
        $api = User::factory()->create();
        $this->putJson('/api/v1/company', ['small_business' => true], ['Authorization' => 'Bearer '.$this->token($api)])
            ->assertOk()->assertJsonPath('company.small_business', true);
        $this->assertTrue(UserSetting::for($api->id)->fresh()->small_business);
    }
}
