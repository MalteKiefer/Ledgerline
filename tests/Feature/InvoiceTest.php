<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(int $customerId, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customerId,
            'issue_date' => '2026-06-01',
            'language' => 'de',
            'currency' => 'EUR',
            'tax_mode' => 'STANDARD',
            'lines' => [
                ['description' => 'Work', 'quantity' => 1, 'unit' => 'h', 'unit_price' => 100, 'tax_rate' => 19],
            ],
        ], $overrides);
    }

    private function createDraft(array $overrides = []): Invoice
    {
        $customer = Customer::factory()->create();
        $this->post(route('finance.invoices.store'), $this->payload($customer->id, $overrides));

        return Invoice::latest('id')->firstOrFail();
    }

    public function test_guests_cannot_access_invoices(): void
    {
        $this->get(route('finance.invoices.index'))->assertRedirect(route('login'));
    }

    public function test_store_creates_a_draft_with_totals(): void
    {
        $this->signIn();
        $invoice = $this->createDraft();

        $this->assertSame(10000, $invoice->net_cents);
        $this->assertSame(1900, $invoice->tax_cents);
        $this->assertSame(11900, $invoice->gross_cents);
        $this->assertSame(1, $invoice->lines()->count());
        $this->assertTrue($invoice->isDraft());
        $this->assertNull($invoice->number);
    }

    public function test_store_requires_a_customer(): void
    {
        $this->signIn();
        $this->post(route('finance.invoices.store'), $this->payload(0, ['customer_id' => '']))
            ->assertSessionHasErrors('customer_id');
    }

    public function test_finalise_assigns_a_gapless_number_from_the_start_number(): void
    {
        $this->signIn();
        CompanyProfile::current()->update(['invoice_number_prefix' => 'RE', 'invoice_number_next' => 100]);

        $first = $this->createDraft();
        $this->post(route('finance.invoices.finalize', $first));
        $second = $this->createDraft();
        $this->post(route('finance.invoices.finalize', $second));

        $this->assertSame('RE-2026-0100', $first->fresh()->number);
        $this->assertSame('RE-2026-0101', $second->fresh()->number);
        $this->assertNotNull($first->fresh()->finalized_at);
        $this->assertSame('SENT', $first->fresh()->status->value);
    }

    public function test_finalise_marks_imported_time_as_billed(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();
        $entry = TimeEntry::factory()->create(['billable' => true, 'billed' => false, 'rate_cents' => 9000, 'minutes' => 60]);

        $this->post(route('finance.invoices.store'), $this->payload($customer->id, [
            'lines' => [],
            'import' => ["time:{$entry->id}"],
        ]));
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame(1, $invoice->lines()->count());

        $this->post(route('finance.invoices.finalize', $invoice));

        $this->assertTrue($entry->fresh()->billed);
    }

    public function test_finalised_invoices_cannot_be_edited(): void
    {
        $this->signIn();
        $invoice = $this->createDraft();
        $this->post(route('finance.invoices.finalize', $invoice));
        $invoice->refresh();

        $this->get(route('finance.invoices.edit', $invoice))->assertForbidden();
        $this->put(route('finance.invoices.update', $invoice), $this->payload($invoice->customer_id))->assertForbidden();
    }

    public function test_the_model_blocks_mutating_a_finalised_invoice(): void
    {
        $this->signIn();
        $invoice = $this->createDraft();
        $this->post(route('finance.invoices.finalize', $invoice));
        $invoice->refresh();

        $this->expectException(RuntimeException::class);
        $invoice->update(['intro_text' => 'tampered']);
    }

    public function test_recording_a_full_payment_marks_it_paid(): void
    {
        $this->signIn();
        $invoice = $this->createDraft();
        $this->post(route('finance.invoices.finalize', $invoice));

        $this->post(route('finance.invoices.payments.store', $invoice), ['amount' => '119.00'])
            ->assertRedirect(route('finance.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(11900, $invoice->paid_cents);
        $this->assertSame('PAID', $invoice->status->value);
    }

    public function test_credit_note_negates_and_cancels_the_original(): void
    {
        $this->signIn();
        $invoice = $this->createDraft();
        $this->post(route('finance.invoices.finalize', $invoice));
        $invoice->refresh();

        $this->post(route('finance.invoices.credit-note', $invoice))->assertRedirect();

        $credit = Invoice::where('parent_invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('CREDIT_NOTE', $credit->type->value);
        $this->assertSame(-11900, $credit->gross_cents);
        $this->assertSame('CANCELLED', $invoice->fresh()->status->value);
    }

    public function test_any_invoice_can_be_trashed_and_restored(): void
    {
        $this->signIn();
        $invoice = $this->createDraft();
        $this->post(route('finance.invoices.finalize', $invoice));
        $invoice->refresh();

        // A finalised (and even paid) invoice can be moved to the trash.
        $invoice->update(['status' => 'PAID', 'paid_cents' => $invoice->gross_cents]);
        $this->delete(route('finance.invoices.destroy', $invoice))
            ->assertRedirect(route('finance.invoices.index'));

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id, 'deleted_at' => null]);

        // It appears in the trash and can be restored.
        $this->get(route('finance.invoices.trash'))->assertOk()->assertSee($invoice->number);
        $this->post(route('finance.invoices.restore', $invoice->id))->assertRedirect();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
    }

    public function test_force_delete_removes_a_trashed_invoice_permanently(): void
    {
        $this->signIn();
        $invoice = $this->createDraft();
        $this->delete(route('finance.invoices.destroy', $invoice));

        $this->delete(route('finance.invoices.force-destroy', $invoice->id))
            ->assertRedirect(route('finance.invoices.trash'));

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_trashed_invoices_are_hidden_from_the_list(): void
    {
        $this->signIn();
        $invoice = $this->createDraft();
        $this->delete(route('finance.invoices.destroy', $invoice));

        $this->assertSame(0, Invoice::count());
        $this->assertSame(1, Invoice::withTrashed()->count());
    }
}
