<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cancelling a finalized invoice with a credit note (Storno / Gutschrift): the
 * Storno creates a NEW numbered credit_note with negated lines referencing the
 * original, the original stays untouched, only finalized non-credit-notes can be
 * cancelled (and only once), and the binding is owner-scoped.
 */
class InvoiceStornoTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(User $user, array $attrs = []): Invoice
    {
        $this->actingAs($user);
        UserSetting::for((int) $user->id)->update(['invoice_number_format' => 'YYYY-NNNN', 'invoice_next_number' => 1]);

        return Invoice::create(array_merge([
            'number' => '2026-0001', 'seq' => 1, 'year' => 2026, 'status' => 'sent',
            'issue_date' => '2026-03-15', 'imported' => false, 'currency' => 'EUR',
            'gross' => 119, 'net' => 100, 'vat' => 19,
            'customer' => ['name' => 'ACME', 'email' => 'client@example.com'],
            'lines' => [['desc' => 'Consulting', 'qty' => 1, 'unit' => 'h', 'unitPrice' => 100, 'vatRate' => 19]],
        ], $attrs));
    }

    public function test_storno_creates_numbered_credit_note_with_negated_lines(): void
    {
        $user = User::factory()->create();
        $orig = $this->invoice($user);

        $credit = $this->postJson(route('finance.invoices.storno', $orig->id))
            ->assertCreated()
            ->assertJsonPath('invoice.type', 'credit_note')
            ->assertJsonPath('invoice.cancels_invoice_id', $orig->id)
            ->json('invoice');

        // It is a real numbered document: its own slot in the GoBD sequence.
        $this->assertSame('2026-0002', $credit['number']);
        $this->assertSame(2, $credit['seq']);
        $this->assertSame(2026, $credit['year']);
        $this->assertSame('sent', $credit['status']);

        // Lines are negated (net = qty * unitPrice → flipped unitPrice).
        $this->assertEqualsWithDelta(-100.0, (float) $credit['lines'][0]['unitPrice'], 0.001);
        // Money columns reverse the original exactly.
        $this->assertEqualsWithDelta(-119.0, (float) $credit['gross'], 0.001);
        $this->assertEqualsWithDelta(-100.0, (float) $credit['net'], 0.001);
        $this->assertEqualsWithDelta(-19.0, (float) $credit['vat'], 0.001);

        // The original is never edited or deleted (GoBD immutability).
        $fresh = $orig->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('2026-0001', $fresh->number);
        $this->assertSame('invoice', $fresh->type);
        $this->assertNull($fresh->deleted_at);

        // Audit trail.
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.storno']);
    }

    public function test_only_a_finalized_invoice_can_be_cancelled(): void
    {
        $user = User::factory()->create();
        $draft = $this->invoice($user, ['number' => null, 'seq' => null, 'status' => 'draft']);

        $this->postJson(route('finance.invoices.storno', $draft->id))
            ->assertStatus(422)->assertJsonPath('error', 'not_finalized');
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_cannot_cancel_the_same_invoice_twice(): void
    {
        $user = User::factory()->create();
        $orig = $this->invoice($user);

        $this->postJson(route('finance.invoices.storno', $orig->id))->assertCreated();
        $this->postJson(route('finance.invoices.storno', $orig->id))
            ->assertStatus(422)->assertJsonPath('error', 'already_cancelled');
    }

    public function test_a_credit_note_cannot_itself_be_cancelled(): void
    {
        $user = User::factory()->create();
        $orig = $this->invoice($user);
        $creditId = $this->postJson(route('finance.invoices.storno', $orig->id))->json('invoice.id');

        $this->postJson(route('finance.invoices.storno', $creditId))
            ->assertStatus(422)->assertJsonPath('error', 'already_credit_note');
    }

    public function test_storno_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $orig = $this->invoice($owner);

        $this->actingAs(User::factory()->create());
        $this->postJson(route('finance.invoices.storno', $orig->id))->assertNotFound();
        // No credit note was created for the owner (count under the owner's scope).
        $this->actingAs($owner);
        $this->assertSame(1, Invoice::query()->count());
    }
}
