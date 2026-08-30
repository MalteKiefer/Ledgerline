<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Application\Ports\InvoiceNumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_routes_are_additive_uuid_scoped_and_protected_by_the_finance_stack(): void
    {
        $routes = [
            'api.finance-v2.invoices.index' => ['GET', 'api/v1/finance/invoices'],
            'api.finance-v2.invoices.store' => ['POST', 'api/v1/finance/invoices'],
            'api.finance-v2.invoices.show' => ['GET', 'api/v1/finance/invoices/{invoice}'],
            'api.finance-v2.invoices.update' => ['PATCH', 'api/v1/finance/invoices/{invoice}'],
            'api.finance-v2.invoices.destroy' => ['DELETE', 'api/v1/finance/invoices/{invoice}'],
            'api.finance-v2.invoices.finalize' => ['POST', 'api/v1/finance/invoices/{invoice}/finalize'],
            'api.finance-v2.invoices.deliveries.store' => ['POST', 'api/v1/finance/invoices/{invoice}/deliveries'],
            'api.finance-v2.invoices.reminders.store' => ['POST', 'api/v1/finance/invoices/{invoice}/reminders'],
            'api.finance-v2.invoices.cancel' => ['POST', 'api/v1/finance/invoices/{invoice}/cancel'],
            'api.finance-v2.invoices.revisions.index' => ['GET', 'api/v1/finance/invoices/{invoice}/revisions'],
            'api.finance-v2.invoices.revisions.pdf' => ['GET', 'api/v1/finance/invoices/{invoice}/revisions/{revision}/pdf'],
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

        $this->getJson('/api/v1/finance/invoices')->assertUnauthorized();

        $disabled = User::factory()->create(['role' => 'user', 'modules' => ['reports']]);
        $disabledToken = $disabled->createToken('device', ['device'])->plainTextToken;
        $this->withToken($disabledToken)->getJson('/api/v1/finance/invoices')->assertForbidden();
    }

    public function test_create_requires_exact_input_and_never_accepts_workflow_fields(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $payload = $this->draftPayload();
        $payload['status'] = 'paid';
        $payload['number'] = 'RE-2026-9999';

        $response = $this->withToken($token)->postJson('/api/v1/finance/invoices', $payload)
            ->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('number', null)
            ->assertJsonPath('version', 0)
            ->assertJsonPath('totals.net_minor', '25000')
            ->assertJsonPath('totals.vat_minor', '4750')
            ->assertJsonPath('totals.gross_minor', '29750')
            ->assertJsonPath('totals.currency', 'EUR')
            ->assertJsonMissingPath('user_id');

        $this->assertIsString($response->json('id'));
        $this->assertSame(1, DB::table('finance_invoices')->where('user_id', $owner->id)->count());

        $bad = $this->draftPayload();
        $bad['lines'][0]['quantity'] = 2.5;
        $this->withToken($token)->postJson('/api/v1/finance/invoices', $bad)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.quantity']);
    }

    public function test_list_supports_filters_and_pagination_envelope(): void
    {
        [, $token] = $this->ownerAndToken();
        $this->withToken($token)->postJson('/api/v1/finance/invoices', $this->draftPayload())->assertCreated();
        $this->withToken($token)->postJson('/api/v1/finance/invoices', $this->draftPayload())->assertCreated();

        $this->withToken($token)->getJson('/api/v1/finance/invoices?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson('/api/v1/finance/invoices?status=draft')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->withToken($token)->getJson('/api/v1/finance/invoices?status=sent')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_show_is_owner_scoped(): void
    {
        [, $token] = $this->ownerAndToken();
        [, $otherToken] = $this->ownerAndToken();
        $created = $this->withToken($token)->postJson('/api/v1/finance/invoices', $this->draftPayload())
            ->assertCreated();
        $uuid = $created->json('id');

        $this->withToken($token)->getJson('/api/v1/finance/invoices/'.$uuid)
            ->assertOk()
            ->assertJsonPath('id', $uuid);

        app('auth')->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/finance/invoices/'.$uuid)
            ->assertNotFound();
    }

    public function test_update_and_delete_draft_use_optimistic_versioning(): void
    {
        [, $token] = $this->ownerAndToken();
        $created = $this->withToken($token)->postJson('/api/v1/finance/invoices', $this->draftPayload())
            ->assertCreated();
        $uuid = $created->json('id');

        $payload = $this->draftPayload('Updated line');
        $payload['version'] = 0;
        $this->withToken($token)->patchJson('/api/v1/finance/invoices/'.$uuid, $payload)
            ->assertOk()
            ->assertJsonPath('version', 1);

        $stale = $this->draftPayload();
        $stale['version'] = 0;
        $this->withToken($token)->patchJson('/api/v1/finance/invoices/'.$uuid, $stale)
            ->assertConflict()
            ->assertJsonPath('error', 'invoice_version_conflict')
            ->assertJsonPath('current.version', 1);

        $this->withToken($token)->deleteJson('/api/v1/finance/invoices/'.$uuid, ['version' => 0])
            ->assertConflict();
        $this->withToken($token)->deleteJson('/api/v1/finance/invoices/'.$uuid, ['version' => 1])
            ->assertNoContent();
        $this->withToken($token)->getJson('/api/v1/finance/invoices/'.$uuid)->assertNotFound();
    }

    public function test_finalize_deliver_and_cancel_use_idempotency_and_expose_no_storage_internals(): void
    {
        [$owner, $token] = $this->ownerAndToken();
        $this->bindFinalizationFakes();
        app()->instance(InvoiceMailer::class, new class implements InvoiceMailer
        {
            public function assertConfigured(int $ownerId): void {}

            public function dispatch(int $ownerId, DeliveryId $deliveryId): void {}

            public function assertDocumentReady(string $path, string $sha256): void {}
        });
        $created = $this->withToken($token)->postJson('/api/v1/finance/invoices', $this->draftPayload())
            ->assertCreated();
        $uuid = $created->json('id');

        $this->withToken($token)->postJson('/api/v1/finance/invoices/'.$uuid.'/finalize')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);

        $finalized = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'finalize-1')
            ->postJson('/api/v1/finance/invoices/'.$uuid.'/finalize')
            ->assertOk()
            ->assertJsonPath('status', 'finalized')
            ->assertJsonMissingPath('revision.pdf_path')
            ->assertJsonMissingPath('snapshot.pdf_path');
        $this->assertIsString($finalized->json('number'));
        $this->assertIsString($finalized->json('revision.pdf_sha256'));

        $replay = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'finalize-1')
            ->postJson('/api/v1/finance/invoices/'.$uuid.'/finalize')
            ->assertOk();
        $this->assertSame($finalized->json('number'), $replay->json('number'));

        $delivery = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'send-1')
            ->postJson('/api/v1/finance/invoices/'.$uuid.'/deliveries', ['recipient' => 'customer@example.test'])
            ->assertStatus(202)
            ->assertJsonPath('kind', 'invoice')
            ->assertJsonPath('recipient', 'customer@example.test')
            ->assertJsonMissingPath('idempotency_key_hash');
        $this->assertIsString($delivery->json('id'));

        $cancelled = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'cancel-1')
            ->postJson('/api/v1/finance/invoices/'.$uuid.'/cancel')
            ->assertCreated()
            ->assertJsonPath('kind', 'credit_note');
        $this->assertNotSame($uuid, $cancelled->json('id'));

        $original = $this->withToken($token)->getJson('/api/v1/finance/invoices/'.$uuid)->assertOk();
        $this->assertSame('cancelled', $original->json('status'));
        $this->assertSame('29750', $original->json('totals.gross_minor'));

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'cancel-2')
            ->postJson('/api/v1/finance/invoices/'.$cancelled->json('id').'/cancel')
            ->assertUnprocessable()
            ->assertJsonPath('error', 'credit_note_cannot_be_cancelled');
    }

    /** @return array{User, string} */
    private function ownerAndToken(): array
    {
        $owner = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);

        return [$owner, $owner->createToken('device', ['device'])->plainTextToken];
    }

    private function bindFinalizationFakes(): void
    {
        app()->instance(DocumentRenderer::class, new class implements DocumentRenderer
        {
            public function render(array $snapshot): string
            {
                return '%PDF-invoice-api-test';
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
            private int $sequence = 0;

            public function allocate(int $ownerId, string $issueDate): array
            {
                $this->sequence++;

                return ['number' => sprintf('RE-2026-%04d', $this->sequence), 'year' => 2026, 'sequence' => $this->sequence];
            }
        });
    }

    /** @return array<string, mixed> */
    private function draftPayload(string $description = 'Consulting'): array
    {
        return [
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'currency' => 'EUR',
            'customer' => ['name' => 'ACME', 'email' => 'billing@example.test'],
            'partner_id' => null,
            'project_id' => null,
            'lines' => [[
                'description' => $description,
                'quantity' => '2.5000',
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
