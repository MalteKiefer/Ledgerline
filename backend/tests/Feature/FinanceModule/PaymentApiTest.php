<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\InvoiceNumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_routes_are_additive_uuid_scoped_and_protected_by_the_finance_stack(): void
    {
        $routes = [
            'api.finance-v2.payments.index' => ['GET', 'api/v1/finance/payments'],
            'api.finance-v2.payments.store' => ['POST', 'api/v1/finance/payments'],
            'api.finance-v2.payments.show' => ['GET', 'api/v1/finance/payments/{payment}'],
            'api.finance-v2.payments.suggestions.show' => ['GET', 'api/v1/finance/payments/{payment}/suggestions'],
            'api.finance-v2.payments.allocations.store' => ['POST', 'api/v1/finance/payments/{payment}/allocations'],
            'api.finance-v2.payment-allocations.reverse' => ['POST', 'api/v1/finance/payment-allocations/{allocation}/reverse'],
        ];

        foreach ($routes as $name => [$method, $uri]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertSame($uri, $route->uri());
            $this->assertContains($method, $route->methods());
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('abilities:device', $middleware);
            $this->assertContains('module:finance', $middleware);
            $this->assertContains('throttle:120,1', $middleware);
        }

        $this->getJson('/api/v1/finance/payments')->assertUnauthorized();

        $disabled = User::factory()->create(['role' => 'user', 'modules' => ['reports']]);
        $disabledToken = $disabled->createToken('device', ['device'])->plainTextToken;
        $this->withToken($disabledToken)->getJson('/api/v1/finance/payments')->assertForbidden();
    }

    public function test_record_requires_idempotency_and_exact_decimal_amount(): void
    {
        [, $token] = $this->ownerAndToken();

        $this->withToken($token)->postJson('/api/v1/finance/payments', $this->paymentPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);

        $bad = $this->paymentPayload();
        $bad['amount'] = 119.0;
        $this->withToken($token)->withHeader('Idempotency-Key', 'record-1')
            ->postJson('/api/v1/finance/payments', $bad)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $created = $this->withToken($token)->withHeader('Idempotency-Key', 'record-1')
            ->postJson('/api/v1/finance/payments', $this->paymentPayload())
            ->assertCreated()
            ->assertJsonPath('amount_minor', '11900')
            ->assertJsonPath('allocated_minor', '0')
            ->assertJsonPath('unapplied_minor', '11900')
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('version', 0);
        $this->assertIsString($created->json('id'));

        $replay = $this->withToken($token)->withHeader('Idempotency-Key', 'record-1')
            ->postJson('/api/v1/finance/payments', $this->paymentPayload())
            ->assertCreated();
        $this->assertSame($created->json('id'), $replay->json('id'));

        $refund = $this->paymentPayload();
        $refund['amount'] = '-50.00';
        $this->withToken($token)->withHeader('Idempotency-Key', 'record-refund')
            ->postJson('/api/v1/finance/payments', $refund)
            ->assertCreated()
            ->assertJsonPath('amount_minor', '-5000');
    }

    public function test_list_supports_pagination_envelope(): void
    {
        [, $token] = $this->ownerAndToken();
        $this->withToken($token)->withHeader('Idempotency-Key', 'k1')->postJson('/api/v1/finance/payments', $this->paymentPayload())->assertCreated();
        $second = $this->paymentPayload();
        $second['reference'] = 'second';
        $this->withToken($token)->withHeader('Idempotency-Key', 'k2')->postJson('/api/v1/finance/payments', $second)->assertCreated();

        $this->withToken($token)->getJson('/api/v1/finance/payments?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data');
    }

    public function test_show_is_owner_scoped(): void
    {
        [, $token] = $this->ownerAndToken();
        [, $otherToken] = $this->ownerAndToken();
        $created = $this->withToken($token)->withHeader('Idempotency-Key', 'k1')
            ->postJson('/api/v1/finance/payments', $this->paymentPayload())->assertCreated();
        $uuid = $created->json('id');

        $this->withToken($token)->getJson('/api/v1/finance/payments/'.$uuid)->assertOk();

        app('auth')->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/finance/payments/'.$uuid)->assertNotFound();
    }

    public function test_allocate_reverse_and_suggest_use_exact_signed_minor_units(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $this->bindFinalizationFakes();
        $invoiceUuid = $this->finalizedInvoice($token);

        $payment = $this->withToken($token)->withHeader('Idempotency-Key', 'record-1')
            ->postJson('/api/v1/finance/payments', $this->paymentPayload())
            ->assertCreated();
        $paymentUuid = $payment->json('id');

        $suggestions = $this->withToken($token)->getJson('/api/v1/finance/payments/'.$paymentUuid.'/suggestions')
            ->assertOk();
        $this->assertContains($suggestions->json('status'), ['suggested', 'ambiguous', 'none']);

        $this->withToken($token)->withHeader('Idempotency-Key', 'allocate-bad')
            ->postJson('/api/v1/finance/payments/'.$paymentUuid.'/allocations', [
                'lines' => [['invoice_id' => $invoiceUuid, 'amount' => '5000.00']],
            ])
            ->assertUnprocessable();

        $allocated = $this->withToken($token)->withHeader('Idempotency-Key', 'allocate-1')
            ->postJson('/api/v1/finance/payments/'.$paymentUuid.'/allocations', [
                'lines' => [['invoice_id' => $invoiceUuid, 'amount' => '119.00']],
            ])
            ->assertCreated()
            ->assertJsonPath('payment.unapplied_minor', '0')
            ->assertJsonPath('invoices.0.status', 'paid')
            ->assertJsonPath('invoices.0.open_minor', '0');

        $allocationId = $this->firstAllocationId();

        $reversed = $this->withToken($token)->withHeader('Idempotency-Key', 'reverse-1')
            ->postJson('/api/v1/finance/payment-allocations/'.$allocationId.'/reverse')
            ->assertOk()
            ->assertJsonPath('payment.unapplied_minor', '11900')
            ->assertJsonPath('invoices.0.status', 'finalized')
            ->assertJsonPath('invoices.0.open_minor', '11900');

        $this->withToken($token)->withHeader('Idempotency-Key', 'reverse-2')
            ->postJson('/api/v1/finance/payment-allocations/'.$allocationId.'/reverse')
            ->assertUnprocessable();

        $this->assertNotSame($allocated->json('payment.version'), $reversed->json('payment.version'));
    }

    /** @return array{User, string} */
    private function ownerAndToken(): array
    {
        $owner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);

        return [$owner, $owner->createToken('device', ['device'])->plainTextToken];
    }

    private function finalizedInvoice(string $token): string
    {
        $created = $this->withToken($token)->postJson('/api/v1/finance/invoices', $this->invoiceDraftPayload())
            ->assertCreated();
        $uuid = $created->json('id');
        $this->withToken($token)->withHeader('Idempotency-Key', 'finalize-for-payment')
            ->postJson('/api/v1/finance/invoices/'.$uuid.'/finalize')
            ->assertOk();

        return $uuid;
    }

    private function firstAllocationId(): int
    {
        $id = DB::table('finance_payment_allocations')
            ->whereNull('reverses_allocation_id')
            ->orderByDesc('id')
            ->value('id');

        return (int) $id;
    }

    private function bindFinalizationFakes(): void
    {
        app()->instance(DocumentRenderer::class, new class implements DocumentRenderer
        {
            public function render(array $snapshot): string
            {
                return '%PDF-payment-api-test';
            }
        });
        app()->instance(DocumentStorage::class, new class implements DocumentStorage
        {
            public function putPdf(string $seriesUuid, string $bytes, DocumentStorageWrite $write): StoredDocument
            {
                $sha256 = hash('sha256', $bytes);

                return new StoredDocument(
                    'finance/revisions/'.substr($sha256, 0, 2).'/'.$sha256.'.pdf',
                    $sha256,
                );
            }

            public function delete(DocumentStorageWrite $write): void {}
        });
        app()->instance(InvoiceNumberAllocator::class, new class implements InvoiceNumberAllocator
        {
            public function allocate(int $ownerId, string $issueDate): array
            {
                return ['number' => 'RE-2026-PAY01', 'year' => 2026, 'sequence' => 1];
            }
        });
    }

    /** @return array<string, mixed> */
    private function paymentPayload(): array
    {
        return [
            'amount' => '119.00',
            'currency' => 'EUR',
            'received_at' => '2026-08-28T10:00:00+00:00',
            'reference' => 'RE-2026-PAY01',
            'counterparty' => 'ACME',
            'payment_method_id' => null,
            'source_type' => null,
            'source_key' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function invoiceDraftPayload(): array
    {
        return [
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'currency' => 'EUR',
            'customer' => ['name' => 'ACME', 'email' => 'billing@example.test'],
            'partner_id' => null,
            'project_id' => null,
            'lines' => [[
                'description' => 'Consulting',
                'quantity' => '1.0000',
                'unit' => 'h',
                'unit_price' => '100.00',
                'tax_rate' => '19.00',
                'kind' => 'service',
                'product_id' => null,
            ]],
            'discount_type' => 'none',
            'discount_value' => null,
        ];
    }
}
