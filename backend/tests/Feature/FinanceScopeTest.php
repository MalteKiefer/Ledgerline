<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\FinanceProject;
use App\Models\FinanceReceipt;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\FinanceReports;
use App\Support\FinanceScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Business vs private across accounts, bookings and receipts.
 *
 * The two things that must hold: an unmarked row follows its account (so opening
 * a private account costs nothing on the rows already there), and nothing
 * private ever reaches a tax report.
 */
class FinanceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_existing_account_is_business_without_being_told(): void
    {
        $this->actingAs(User::factory()->create());
        // No scope passed — the column default is what every row created before
        // this feature existed will read as.
        $pm = PaymentMethod::create(['type' => 'bank', 'name' => 'Geschäftskonto']);

        $this->assertSame('business', $pm->fresh()?->scope);
        $this->assertSame(FinanceScope::BUSINESS, FinanceScope::ofAccount($pm));
    }

    public function test_a_booking_follows_its_account_and_an_override_wins(): void
    {
        $this->actingAs(User::factory()->create());
        $private = PaymentMethod::create(['type' => 'bank', 'name' => 'Privat', 'scope' => 'private']);
        $business = PaymentMethod::create(['type' => 'bank', 'name' => 'Firma', 'scope' => 'business']);

        $inherited = BankTransaction::create(['payment_method_id' => $private->id, 'date' => '2026-04-01', 'amount' => -20]);
        $overridden = BankTransaction::create(['payment_method_id' => $business->id, 'date' => '2026-04-02', 'amount' => -30, 'scope' => 'private']);
        $plain = BankTransaction::create(['payment_method_id' => $business->id, 'date' => '2026-04-03', 'amount' => -40]);

        $this->assertSame('private', FinanceScope::ofTransaction($inherited->fresh() ?? $inherited));
        $this->assertSame('private', FinanceScope::ofTransaction($overridden->fresh() ?? $overridden));
        $this->assertSame('business', FinanceScope::ofTransaction($plain->fresh() ?? $plain));
    }

    public function test_a_receipt_follows_its_booking_and_a_loose_one_is_business(): void
    {
        $this->actingAs(User::factory()->create());
        $private = PaymentMethod::create(['type' => 'bank', 'name' => 'Privat', 'scope' => 'private']);
        $tx = BankTransaction::create(['payment_method_id' => $private->id, 'date' => '2026-04-01', 'amount' => -20]);

        $linked = new FinanceReceipt;
        $linked->forceFill(['user_id' => (int) auth()->id(), 'blob_path' => 'invoices/a', 'name' => 'a.pdf', 'bank_transaction_id' => $tx->id])->save();
        $loose = new FinanceReceipt;
        $loose->forceFill(['user_id' => (int) auth()->id(), 'blob_path' => 'invoices/b', 'name' => 'b.pdf'])->save();

        $this->assertSame('private', FinanceScope::ofReceipt($linked->fresh() ?? $linked));
        $this->assertSame('business', FinanceScope::ofReceipt($loose->fresh() ?? $loose));
    }

    public function test_private_spending_stays_out_of_the_tax_reports(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $business = PaymentMethod::create(['type' => 'bank', 'name' => 'Firma', 'scope' => 'business']);
        $private = PaymentMethod::create(['type' => 'bank', 'name' => 'Privat', 'scope' => 'private']);

        // 119 gross @19% = 19.00 input VAT, 100.00 expense — the only row that counts.
        BankTransaction::create(['payment_method_id' => $business->id, 'date' => '2026-02-10', 'amount' => -119, 'vat_cat' => '19', 'counterparty' => 'Hoster']);
        // Same shape, private account: must contribute nothing.
        BankTransaction::create(['payment_method_id' => $private->id, 'date' => '2026-02-11', 'amount' => -238, 'vat_cat' => '19', 'counterparty' => 'Supermarkt']);
        // Business account, but a private purchase: also nothing.
        BankTransaction::create(['payment_method_id' => $business->id, 'date' => '2026-02-12', 'amount' => -357, 'vat_cat' => '19', 'scope' => 'private', 'counterparty' => 'Möbelhaus']);

        $reports = app(FinanceReports::class);
        $vat = $reports->vatAdvanceReturn(2026);
        $euer = $reports->euer(2026);

        $this->assertSame(19.0, round((float) $vat['inputVat'], 2), 'only the business booking carries input VAT');
        $this->assertSame(100.0, round((float) $euer['expenses']['total'], 2), 'only the business booking is an expense');
    }

    public function test_a_private_project_is_not_an_expense(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        // Regression: euer() used to walk every project, so hand-entered spending
        // on a private project landed in the tax return.
        FinanceProject::create([
            'name' => 'Hausbau', 'kind' => 'private',
            'expenses' => [['id' => 'e1', 'amount' => 250, 'date' => '2026-05-05', 'direction' => 'out', 'title' => 'Bagger']],
        ]);
        FinanceProject::create([
            'name' => 'Kundenprojekt', 'kind' => 'business',
            'expenses' => [['id' => 'e2', 'amount' => 40, 'date' => '2026-05-06', 'direction' => 'out', 'title' => 'Material']],
        ]);

        $euer = app(FinanceReports::class)->euer(2026);

        $this->assertSame(40.0, round((float) $euer['expenses']['total'], 2));
    }

    public function test_the_data_payload_states_the_effective_scope(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $private = PaymentMethod::create(['type' => 'bank', 'name' => 'Privat', 'scope' => 'private']);
        BankTransaction::create(['payment_method_id' => $private->id, 'date' => '2026-04-01', 'amount' => -20]);

        // The client must not have to re-derive the inheritance rule.
        $res = $this->getJson(route('api.finance.data'))->assertOk();
        $this->assertSame('private', $res->json('transactions.0.effective_scope'));
    }
}
