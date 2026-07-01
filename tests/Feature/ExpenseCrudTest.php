<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-06-01',
            'description' => 'Cloud server',
            'category' => ExpenseCategory::HOSTING->value,
            'amount' => '119.00',
            'currency' => 'EUR',
            'tax_rate' => 19,
            'payment_status' => PaymentStatus::OPEN->value,
        ], $overrides);
    }

    public function test_guests_cannot_access_expenses(): void
    {
        $this->get(route('finance.expenses.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_expenses_with_totals(): void
    {
        $this->signIn();
        Expense::factory()->create(['description' => 'Findable expense', 'amount_cents' => 5000, 'currency' => 'EUR']);

        $this->get(route('finance.expenses.index'))
            ->assertOk()
            ->assertSee('Findable expense')
            ->assertSee('Total (EUR)');
    }

    public function test_store_converts_amount_to_cents_and_derives_vat(): void
    {
        $this->signIn();

        $this->post(route('finance.expenses.store'), $this->payload([
            'description' => 'ERP Rollout',
            'billable' => '1',
            'labels' => ['Cloud', 'AWS'],
        ]))->assertRedirect();

        $expense = Expense::firstWhere('description', 'ERP Rollout');

        $this->assertSame(11900, $expense->amount_cents);
        $this->assertSame(1900, $expense->tax_cents); // 11900 * 19 / 119
        $this->assertSame(10000, $expense->net()->cents);
        $this->assertTrue($expense->billable);
        $this->assertSame(['Cloud', 'AWS'], $expense->labels);
    }

    public function test_store_can_link_a_customer(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create();

        $this->post(route('finance.expenses.store'), $this->payload(['customer_id' => $customer->id]))
            ->assertRedirect();

        $this->assertSame($customer->id, Expense::firstWhere('description', 'Cloud server')->customer_id);
    }

    public function test_store_requires_description_amount_and_category(): void
    {
        $this->signIn();

        $this->post(route('finance.expenses.store'), $this->payload(['description' => '', 'amount' => '', 'category' => '']))
            ->assertSessionHasErrors(['description', 'amount', 'category']);
    }

    public function test_update_and_destroy(): void
    {
        $this->signIn();
        $expense = Expense::factory()->create(['description' => 'Old']);

        $this->put(route('finance.expenses.update', $expense), $this->payload(['description' => 'New']))
            ->assertRedirect(route('finance.expenses.show', $expense));
        $this->assertSame('New', $expense->fresh()->description);

        $this->delete(route('finance.expenses.destroy', $expense))->assertRedirect(route('finance.expenses.index'));
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_index_can_be_filtered_by_category(): void
    {
        $this->signIn();
        Expense::factory()->create(['description' => 'Server', 'category' => ExpenseCategory::HOSTING->value]);
        Expense::factory()->create(['description' => 'Laptop', 'category' => ExpenseCategory::HARDWARE->value]);

        $this->get(route('finance.expenses.index', ['category' => ExpenseCategory::HOSTING->value]))
            ->assertOk()
            ->assertSee('Server')
            ->assertDontSee('Laptop');
    }

    public function test_can_attach_a_document_to_an_expense(): void
    {
        Storage::fake('files');
        $this->signIn();
        $expense = Expense::factory()->create();

        $this->post(route('finance.expenses.files.store', $expense), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('finance.expenses.show', $expense));

        $file = File::firstWhere('name', 'receipt.pdf');
        $this->assertTrue($file->attachable->is($expense));
        $this->assertSame($this->team->id, $file->team_id);
    }
}
