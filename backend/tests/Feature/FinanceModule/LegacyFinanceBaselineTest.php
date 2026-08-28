<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\FinanceProject;
use App\Models\FinanceQuote;
use App\Models\FinanceTimeEntry;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyFinanceBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_tests_have_a_deterministic_application_key(): void
    {
        $this->assertMatchesRegularExpression('/^base64:/', (string) config('app.key'));

        $user = User::factory()->create();
        UserSetting::for((int) $user->id)->forceFill(['company_smtp_password' => 'test-secret'])->save();

        $this->assertSame('test-secret', UserSetting::for((int) $user->id)->company_smtp_password);
    }

    public function test_a_sent_quote_cannot_be_edited(): void
    {
        $this->signIn();
        $quote = $this->quote(['title' => 'Original']);

        $this->postJson(route('api.finance.quotes.send', $quote))->assertOk();
        $this->putJson(route('api.finance.quotes.update', $quote), ['title' => 'Changed'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'quote_locked');

        $this->assertSame('Original', (string) $quote->fresh()?->title);
        $this->assertSame(1, FinanceQuote::query()->count());
    }

    public function test_quote_conversion_is_idempotent(): void
    {
        $this->signIn();
        $quote = $this->quote();

        $this->postJson(route('api.finance.quotes.send', $quote))->assertOk();
        $first = $this->postJson(route('api.finance.quotes.convert', $quote))
            ->assertCreated()
            ->assertJsonPath('invoice.status', 'draft');
        $invoiceId = (int) $first->json('invoice.id');

        $this->postJson(route('api.finance.quotes.convert', $quote))
            ->assertOk()
            ->assertJsonPath('already', true)
            ->assertJsonPath('invoice.id', $invoiceId);

        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame($invoiceId, (int) $quote->fresh()?->converted_invoice_id);
    }

    public function test_project_time_can_only_be_invoiced_once(): void
    {
        $this->signIn();
        $project = new FinanceProject;
        $project->fill(['name' => 'Baseline project', 'kind' => 'business']);
        $project->save();

        $this->postJson(route('api.finance.projects.time.store', $project), [
            'hours' => 2,
            'hourly_rate' => 100,
        ])->assertCreated();
        $first = $this->postJson(route('api.finance.projects.invoice-time', $project))
            ->assertCreated()
            ->assertJsonPath('entries', 1);

        $this->postJson(route('api.finance.projects.invoice-time', $project))
            ->assertStatus(422)
            ->assertJsonPath('error', 'nothing_to_invoice');

        $invoiceId = (int) $first->json('invoice.id');
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(1, FinanceTimeEntry::query()->where('invoiced_invoice_id', $invoiceId)->count());
    }

    public function test_invoice_finalization_allocates_one_number_on_retry(): void
    {
        $user = $this->signIn();
        UserSetting::for((int) $user->id)->forceFill([
            'invoice_number_format' => 'YYYY-NNNN',
            'invoice_next_number' => 1,
        ])->save();
        $invoice = $this->invoice();

        $first = $this->postJson(route('api.finance.invoices.finalize', $invoice))
            ->assertOk()
            ->json('invoice.number');
        $second = $this->postJson(route('api.finance.invoices.finalize', $invoice))
            ->assertOk()
            ->json('invoice.number');

        $this->assertSame($first, $second);
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(1, Invoice::query()->whereNotNull('number')->count());
    }

    public function test_a_finalized_invoice_can_only_be_cancelled_once(): void
    {
        $user = $this->signIn();
        UserSetting::for((int) $user->id)->forceFill([
            'invoice_number_format' => 'YYYY-NNNN',
            'invoice_next_number' => 1,
        ])->save();
        $invoice = $this->invoice();
        $this->postJson(route('api.finance.invoices.finalize', $invoice))->assertOk();

        $this->postJson(route('api.finance.invoices.storno', $invoice))
            ->assertCreated()
            ->assertJsonPath('invoice.cancels_invoice_id', $invoice->id);
        $this->postJson(route('api.finance.invoices.storno', $invoice))
            ->assertStatus(422)
            ->assertJsonPath('error', 'already_cancelled');

        $this->assertSame(2, Invoice::query()->count());
        $this->assertSame(1, Invoice::query()->where('cancels_invoice_id', $invoice->id)->count());
    }

    /** @param array<string, mixed> $attrs */
    private function quote(array $attrs = []): FinanceQuote
    {
        $quote = new FinanceQuote;
        $quote->fill(array_merge([
            'title' => 'Baseline quote',
            'issue_date' => '2026-05-04',
            'customer' => ['name' => 'Customer'],
            'lines' => [['desc' => 'Service', 'qty' => 1, 'unit' => 'hour', 'unitPrice' => 100, 'vatRate' => 19, 'kind' => 'service', 'productId' => null]],
            'net' => 100,
            'vat' => 19,
            'gross' => 119,
        ], $attrs));
        $quote->save();

        return $quote;
    }

    private function invoice(): Invoice
    {
        $invoice = new Invoice;
        $invoice->fill([
            'status' => 'draft',
            'issue_date' => '2026-05-04',
            'currency' => 'EUR',
            'customer' => ['name' => 'Customer'],
            'lines' => [['desc' => 'Service', 'qty' => 1, 'unit' => 'hour', 'unitPrice' => 100, 'vatRate' => 19, 'kind' => 'service', 'productId' => null]],
            'net' => 100,
            'vat' => 19,
            'gross' => 119,
        ]);
        $invoice->save();

        return $invoice;
    }
}
