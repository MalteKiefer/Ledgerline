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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegacyFinanceBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_project_document_and_time_routes_remain_available_during_the_shadow_cutover(): void
    {
        $contracts = [
            'api.finance.data' => ['GET', 'api/v1/finance/data'],
            'api.finance.projects.store' => ['POST', 'api/v1/finance/projects'],
            'api.finance.projects.update' => ['PUT', 'api/v1/finance/projects/{project}'],
            'api.finance.projects.move' => ['POST', 'api/v1/finance/projects/{project}/move'],
            'api.finance.projects.attachments' => ['GET', 'api/v1/finance/projects/{project}/attachments'],
            'api.finance.projects.destroy' => ['DELETE', 'api/v1/finance/projects/{project}'],
            'api.finance.projects.restore' => ['POST', 'api/v1/finance/projects/{id}/restore'],
            'api.finance.projects.force' => ['DELETE', 'api/v1/finance/projects/{id}/force'],
            'api.finance.projects.plan' => ['GET', 'api/v1/finance/projects/{project}/plan'],
            'api.finance.projects.tasks.store' => ['POST', 'api/v1/finance/projects/{project}/tasks'],
            'api.finance.projects.tasks.reorder' => ['POST', 'api/v1/finance/projects/{project}/tasks/reorder'],
            'api.finance.project-tasks.update' => ['PUT', 'api/v1/finance/project-tasks/{task}'],
            'api.finance.project-tasks.destroy' => ['DELETE', 'api/v1/finance/project-tasks/{task}'],
            'api.finance.projects.time.store' => ['POST', 'api/v1/finance/projects/{project}/time'],
            'api.finance.time-entries.update' => ['PUT', 'api/v1/finance/time-entries/{entry}'],
            'api.finance.time-entries.destroy' => ['DELETE', 'api/v1/finance/time-entries/{entry}'],
            'api.finance.projects.invoice-time' => ['POST', 'api/v1/finance/projects/{project}/invoice-time'],
            'api.finance.quotes.project' => ['POST', 'api/v1/finance/quotes/{quote}/project'],
        ];

        foreach ($contracts as $name => [$method, $uri]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, $name);
            $this->assertSame($uri, $route->uri(), $name);
            $this->assertContains($method, $route->methods(), $name);
        }
    }

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

        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame($invoiceId, (int) $quote->fresh()?->converted_finance_invoice_id);
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
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(1, FinanceTimeEntry::query()->where('invoiced_finance_invoice_id', $invoiceId)->count());
    }

    // Idempotent finalize-number-allocation and single-cancellation-per-invoice
    // used to be tested here against the now-deleted legacy FinanceController::
    // finalizeInvoice/stornoInvoice routes. They are covered equivalently against
    // the finance-v2 invoice module -- the only invoice writer left -- by
    // tests/Feature/FinanceModule/InvoiceFinalizationTest.php::
    // test_finalization_is_atomic_exact_and_idempotent() and every test in
    // tests/Feature/FinanceModule/InvoiceCancellationTest.php.

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
}
