<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\Invoice as LegacyInvoiceModel;
use App\Models\User;
use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\InvoiceNumberAllocator;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyInvoiceReadProjection;
use App\Services\Finance\FinanceReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Proves the finance-v2 -> legacy projection feeds FinanceReports' existing,
 * already-trusted per-line totals/discount/VAT-rate computation without
 * drifting from what finance-v2 itself already computed exactly in minor
 * units — the whole point of reusing invoiceTotals() unmodified instead of
 * writing a second, parallel computation that could quietly disagree.
 */
final class LegacyInvoiceReadProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_as_invoice_models_reproduces_the_exact_finance_v2_totals_through_finance_reports(): void
    {
        $owner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $token = $owner->createToken('device', ['device'])->plainTextToken;

        // Two rates + a global amount discount — the exact shape InvoiceDiscountTest
        // exercises for a genuine legacy row, so this proves the same result reaches
        // FinanceReports via the finance-v2 path too.
        $response = $this->withToken($token)->postJson('/api/v1/finance/invoices', [
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'currency' => 'EUR',
            'customer' => ['name' => 'ACME'],
            'partner_id' => null,
            'project_id' => null,
            'lines' => [
                ['description' => 'A', 'quantity' => '1.0000', 'unit' => 'x', 'unit_price' => '100.00', 'tax_rate' => '19.00', 'kind' => 'service', 'product_id' => null],
                ['description' => 'B', 'quantity' => '1.0000', 'unit' => 'x', 'unit_price' => '100.00', 'tax_rate' => '7.00', 'kind' => 'service', 'product_id' => null],
            ],
            'discount_type' => 'fixed',
            'discount_value' => '20.00',
        ])->assertCreated();

        $expectedNetMinor = $this->numericJson($response, 'totals.net_minor');
        $expectedVatMinor = $this->numericJson($response, 'totals.vat_minor');
        $expectedGrossMinor = $this->numericJson($response, 'totals.gross_minor');
        // net 200 - 20 = 180; VAT = 90*19% + 90*7% = 17.10 + 6.30 = 23.40; gross 203.40.
        $this->assertSame(18000, $expectedNetMinor);
        $this->assertSame(2340, $expectedVatMinor);
        $this->assertSame(20340, $expectedGrossMinor);

        $financeInvoiceId = $this->requiredInvoiceId($owner);

        $this->actingAs($owner);
        $models = app(LegacyInvoiceReadProjection::class)->asInvoiceModels((int) $owner->id);
        $this->assertCount(1, $models);
        $model = $models->first();
        $this->assertNotNull($model);

        // Negated id: never collides with a genuine legacy invoices.id.
        $this->assertSame(-$financeInvoiceId, $model->id);
        $this->assertSame('draft', $model->status);

        $totals = app(FinanceReports::class)->invoiceTotals($model);
        $this->assertEqualsWithDelta($expectedNetMinor / 100, $totals['net'], 0.001);
        $this->assertEqualsWithDelta($expectedVatMinor / 100, $totals['vat'], 0.001);
        $this->assertEqualsWithDelta($expectedGrossMinor / 100, $totals['gross'], 0.001);
        $this->assertEqualsWithDelta(17.10, $totals['vatByRate']['19'], 0.001);
        $this->assertEqualsWithDelta(6.30, $totals['vatByRate']['7'], 0.001);
    }

    public function test_as_invoice_models_reports_paid_at_from_the_latest_allocation_and_never_collides_with_legacy_ids(): void
    {
        $owner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $token = $owner->createToken('device', ['device'])->plainTextToken;
        $this->bindFinalizationFakes();

        // A genuine legacy invoice with the SAME numeric id a finance-v2 invoice
        // could plausibly also have (both tables autoincrement from 1).
        $this->actingAs($owner);
        $legacy = LegacyInvoiceModel::create([
            'status' => 'sent', 'issue_date' => '2026-01-01', 'imported' => false, 'currency' => 'EUR',
            'customer' => ['name' => 'Legacy Co'], 'lines' => [['qty' => 1, 'unitPrice' => 50, 'vatRate' => 19]],
        ]);

        $created = $this->withToken($token)->postJson('/api/v1/finance/invoices', [
            'issue_date' => '2026-08-28', 'due_date' => '2026-09-11', 'currency' => 'EUR',
            'customer' => ['name' => 'ACME'], 'partner_id' => null, 'project_id' => null,
            'lines' => [['description' => 'A', 'quantity' => '1.0000', 'unit' => 'x', 'unit_price' => '100.00', 'tax_rate' => '19.00', 'kind' => 'service', 'product_id' => null]],
            'discount_type' => 'none', 'discount_value' => null,
        ])->assertCreated();
        $invoiceUuid = $created->json('id');
        $this->assertIsString($invoiceUuid);
        $financeInvoiceId = $this->requiredInvoiceId($owner);
        $this->assertSame($legacy->id, $financeInvoiceId, 'fixture assumes both autoincrement ids start at 1');

        $this->withToken($token)->withHeader('Idempotency-Key', 'finalize-1')
            ->postJson('/api/v1/finance/invoices/'.$invoiceUuid.'/finalize')
            ->assertOk();

        $payment = $this->withToken($token)->withHeader('Idempotency-Key', 'record-1')
            ->postJson('/api/v1/finance/payments', [
                'amount' => '119.00', 'currency' => 'EUR', 'received_at' => '2026-09-05T10:00:00+00:00',
                'reference' => null, 'counterparty' => null, 'payment_method_id' => null,
                'source_type' => null, 'source_key' => null,
            ])->assertCreated();
        $paymentId = $payment->json('id');
        $this->assertIsString($paymentId);

        $this->withToken($token)->withHeader('Idempotency-Key', 'allocate-1')
            ->postJson('/api/v1/finance/payments/'.$paymentId.'/allocations', [
                'lines' => [['invoice_id' => $invoiceUuid, 'amount' => '119.00']],
            ])
            ->assertCreated()
            ->assertJsonPath('invoices.0.status', 'paid');

        $models = app(LegacyInvoiceReadProjection::class)->asInvoiceModels((int) $owner->id);
        $this->assertCount(1, $models);
        $model = $models->first();
        $this->assertNotNull($model);

        $this->assertSame(-$financeInvoiceId, $model->id);
        $this->assertNotSame($legacy->id, $model->id, 'a finance-v2 pseudo-invoice must never share an id with a real legacy invoice');
        $this->assertSame('paid', $model->status);
        $this->assertNotNull($model->paid_at);
        $this->assertSame('2026-09-05', $model->paid_at->format('Y-m-d'));
    }

    /** @param  TestResponse<JsonResponse>  $response */
    private function numericJson(TestResponse $response, string $path): int
    {
        $value = $response->json($path);
        if (! is_numeric($value)) {
            throw new \RuntimeException('Expected a numeric JSON value at '.$path);
        }

        return (int) $value;
    }

    private function requiredInvoiceId(User $owner): int
    {
        $id = DB::table('finance_invoices')->where('user_id', $owner->id)->value('id');
        if (! is_numeric($id)) {
            throw new \RuntimeException('Expected a finance_invoices row for this owner.');
        }

        return (int) $id;
    }

    private function bindFinalizationFakes(): void
    {
        app()->instance(DocumentRenderer::class, new class implements DocumentRenderer
        {
            public function render(array $snapshot): string
            {
                return '%PDF-projection-test';
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
                return ['number' => 'RE-2026-0001', 'year' => 2026, 'sequence' => 1];
            }
        });
    }
}
