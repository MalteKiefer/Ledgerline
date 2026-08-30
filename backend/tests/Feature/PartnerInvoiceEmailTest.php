<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FinancePartner;
use App\Models\User;
use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Application\Ports\InvoiceNumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A business partner may carry a dedicated invoice email (Rechnungs-E-Mail),
 * separate from the general email. It is stored/returned via the partner CRUD,
 * validated, and — when an invoice's customer snapshot carries `invoiceEmail` —
 * used as the recipient when delivering the invoice, falling back to the
 * general customer email when absent.
 */
class PartnerInvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_partner_stores_and_returns_invoice_email(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('finance.partners.store'), [
            'name' => 'ACME GmbH',
            'email' => 'hello@acme.example',
            'invoice_email' => 'billing@acme.example',
        ])
            ->assertCreated()
            ->assertJsonPath('partner.invoice_email', 'billing@acme.example');

        $partner = FinancePartner::query()->firstOrFail();
        $this->assertSame('billing@acme.example', $partner->invoice_email);
    }

    public function test_invalid_invoice_email_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('finance.partners.store'), [
            'name' => 'ACME GmbH',
            'invoice_email' => 'not-an-email',
        ])->assertInvalid(['invoice_email']);
    }

    /**
     * The legacy FinanceController::emailInvoice route this test exercised is
     * gone (Task 17 cutover); the invoiceEmail-over-email recipient preference
     * it proved now lives in EloquentInvoiceRepository::assertDeliveryReady(),
     * used by every finance-v2 delivery (invoice send and reminder alike). This
     * test proves the same precedence through the finance-v2 API instead of the
     * retired legacy one.
     */
    public function test_delivery_prefers_customer_invoice_email_over_the_general_address(): void
    {
        [$owner, $token] = $this->financeV2OwnerAndToken();
        $this->bindFinanceV2FinalizationFakes();

        $uuid = $this->withToken($token)->postJson('/api/v1/finance/invoices', $this->financeV2DraftPayload([
            'name' => 'ACME', 'email' => 'general@example.com', 'invoiceEmail' => 'billing@example.com',
        ]))->assertCreated()->json('id');
        $this->withToken($token)->withHeader('Idempotency-Key', 'finalize-1')
            ->postJson('/api/v1/finance/invoices/'.$uuid.'/finalize')->assertOk();

        $this->withToken($token)->withHeader('Idempotency-Key', 'send-1')
            ->postJson('/api/v1/finance/invoices/'.$uuid.'/deliveries', [])
            ->assertStatus(202)
            ->assertJsonPath('recipient', 'billing@example.com');
    }

    public function test_delivery_falls_back_to_customer_email_when_no_invoice_email(): void
    {
        [$owner, $token] = $this->financeV2OwnerAndToken();
        $this->bindFinanceV2FinalizationFakes();

        $uuid = $this->withToken($token)->postJson('/api/v1/finance/invoices', $this->financeV2DraftPayload([
            'name' => 'ACME', 'email' => 'general@example.com',
        ]))->assertCreated()->json('id');
        $this->withToken($token)->withHeader('Idempotency-Key', 'finalize-1')
            ->postJson('/api/v1/finance/invoices/'.$uuid.'/finalize')->assertOk();

        $this->withToken($token)->withHeader('Idempotency-Key', 'send-1')
            ->postJson('/api/v1/finance/invoices/'.$uuid.'/deliveries', [])
            ->assertStatus(202)
            ->assertJsonPath('recipient', 'general@example.com');
    }

    /** @return array{User, string} */
    private function financeV2OwnerAndToken(): array
    {
        $owner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);

        return [$owner, $owner->createToken('device', ['device'])->plainTextToken];
    }

    private function bindFinanceV2FinalizationFakes(): void
    {
        app()->instance(DocumentRenderer::class, new class implements DocumentRenderer
        {
            public function render(array $snapshot): string
            {
                return '%PDF-partner-invoice-email-test';
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
            /** @return array{number: string, year: int, sequence: int} */
            public function allocate(int $ownerId, string $issueDate): array
            {
                return ['number' => 'RE-2026-PIE01', 'year' => 2026, 'sequence' => 1];
            }
        });
        app()->instance(InvoiceMailer::class, new class implements InvoiceMailer
        {
            public function assertConfigured(int $ownerId): void {}

            public function dispatch(int $ownerId, DeliveryId $deliveryId): void {}

            public function assertDocumentReady(string $path, string $sha256): void {}
        });
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    private function financeV2DraftPayload(array $customer): array
    {
        return [
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'currency' => 'EUR',
            'customer' => $customer,
            'partner_id' => null,
            'project_id' => null,
            'lines' => [[
                'description' => 'Consulting', 'quantity' => '1.0000', 'unit' => 'h',
                'unit_price' => '100.00', 'tax_rate' => '19.00', 'kind' => 'service', 'product_id' => null,
            ]],
            'discount_type' => 'none',
            'discount_value' => null,
        ];
    }
}
