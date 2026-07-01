<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IncomeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_income(): void
    {
        $this->get(route('finance.income-entries.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_entries_with_totals(): void
    {
        $this->signIn();
        IncomeEntry::factory()->create(['description' => 'Retainer', 'amount_cents' => 50000, 'currency' => 'EUR']);

        $this->get(route('finance.income-entries.index'))
            ->assertOk()
            ->assertSee('Retainer')
            ->assertSee('Total (EUR)');
    }

    public function test_store_converts_amount_to_cents(): void
    {
        $this->signIn();

        $this->post(route('finance.income-entries.store'), [
            'date' => '2026-06-01',
            'description' => 'Deposit',
            'amount' => '1500.00',
            'currency' => 'EUR',
        ])->assertRedirect(route('finance.income-entries.index'));

        $this->assertSame(150000, IncomeEntry::firstWhere('description', 'Deposit')->amount_cents);
    }

    public function test_store_requires_description_and_amount(): void
    {
        $this->signIn();

        $this->post(route('finance.income-entries.store'), ['date' => '2026-06-01', 'currency' => 'EUR'])
            ->assertSessionHasErrors(['description', 'amount']);
    }

    public function test_update_and_destroy(): void
    {
        $this->signIn();
        $entry = IncomeEntry::factory()->create(['description' => 'Old']);

        $this->put(route('finance.income-entries.update', $entry), [
            'date' => '2026-06-02',
            'description' => 'New',
            'amount' => '200.00',
            'currency' => 'EUR',
        ])->assertRedirect(route('finance.income-entries.index'));
        $this->assertSame('New', $entry->fresh()->description);

        $this->delete(route('finance.income-entries.destroy', $entry))->assertRedirect();
        $this->assertDatabaseMissing('income_entries', ['id' => $entry->id]);
    }
}
