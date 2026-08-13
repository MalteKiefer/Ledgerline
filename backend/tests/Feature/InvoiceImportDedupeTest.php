<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An imported invoice must get its `year` populated from the issue date so the
 * (user_id, year, number) unique index actually catches duplicate imports — a
 * NULL year is treated as distinct by the DB and previously let the same invoice
 * through twice (the source of the "possible duplicate" reports).
 */
class InvoiceImportDedupeTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_invoice_gets_year_from_issue_date(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('finance.invoices.store'), [
            'number' => 'R-00103', 'status' => 'paid', 'imported' => true,
            'issue_date' => '2020-04-27', 'currency' => 'EUR',
            'gross' => 47.60, 'vat_rate' => 19,
            'customer' => ['name' => 'STN Nürnberg'], 'lines' => [],
        ])->assertCreated()->assertJsonPath('invoice.year', 2020);
    }

    public function test_duplicate_import_is_rejected_by_the_unique_index(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = [
            'number' => 'R-00103', 'status' => 'paid', 'imported' => true,
            'issue_date' => '2020-04-27', 'currency' => 'EUR',
            'gross' => 47.60, 'vat_rate' => 19,
            'customer' => ['name' => 'STN Nürnberg'], 'lines' => [],
        ];
        $this->postJson(route('finance.invoices.store'), $payload)->assertCreated();

        // The second identical import must not create a second row — the
        // (user_id, year, number) unique index rejects it (year now populated).
        try {
            $this->postJson(route('finance.invoices.store'), $payload);
        } catch (QueryException) {
            // expected — the DB rejected the duplicate.
        }

        $this->assertSame(1, Invoice::query()->where('number', 'R-00103')->count());
    }
}
