<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\FinanceProduct;
use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\Commands\Invoices\CancelInvoice;
use App\Modules\Finance\Application\Commands\Invoices\FinalizeInvoice;
use App\Modules\Finance\Application\Commands\Payments\AllocatePayment;
use App\Modules\Finance\Application\Commands\Payments\RecordPayment;
use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\CancelInvoiceData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Payments\AllocatePaymentData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationLineData;
use App\Modules\Finance\Application\DTOs\Payments\RecordPaymentData;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\InventoryMovementPort;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Inventory\LegacyStockLedgerAdapter;
use App\Modules\Finance\Infrastructure\Persistence\EloquentIdempotencyStore;
use App\Modules\Finance\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Modules\Finance\Infrastructure\Persistence\LockedInvoiceNumberAllocator;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class InvoiceCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_exactly_reverses_a_discounted_mixed_tax_invoice_without_mutating_the_original(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->update([
            'invoice_number_format' => 'RE-YYYY-NNNN',
            'invoice_next_number' => 1,
        ]);
        $hardware = $this->product((int) $owner->id, 'hardware', '10.0000', true);
        $service = $this->product((int) $owner->id, 'service', '0.0000', false);
        $clock = new InvoiceCancellationClock(new DateTimeImmutable('2026-08-29T10:15:00+02:00'));
        $repository = new EloquentInvoiceRepository(new EloquentIdempotencyStore($clock), $clock);
        $renderer = new InvoiceCancellationRenderer;
        $storage = new InvoiceCancellationStorage;
        $finalize = new FinalizeInvoice(
            $repository,
            new LockedInvoiceNumberAllocator,
            new LegacyStockLedgerAdapter,
            $renderer,
            $storage,
            new NullLogger,
        );
        $originalId = $repository->createDraft(new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME GmbH', 'email' => 'billing@acme.test'],
            lines: [
                new InvoiceLineData('Router', '2.0000', 10_000, 1_900, 'pc', (int) $hardware->id, 'hardware'),
                new InvoiceLineData('Installation', '1.0000', 5_000, 700, 'h', (int) $service->id, 'service'),
            ],
            discount: Discount::percentBasisPoints(1_000, 'EUR'),
            controlNetMinor: 22_500,
            controlVatMinor: 3_735,
            controlGrossMinor: 26_235,
        ));
        $original = $finalize->handle($originalId, new IdempotencyKey('finalize-original-for-cancellation'));
        $payment = app(RecordPayment::class)->handle(new RecordPaymentData(
            5_000,
            'EUR',
            new DateTimeImmutable('2026-08-29T08:00:00+00:00'),
            $original->invoice->number,
            'ACME GmbH',
        ), new IdempotencyKey('record-cancellation-partial-payment'));
        app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment->id,
            [new AllocationLineData($originalId, 5_000)],
        ), new IdempotencyKey('allocate-cancellation-partial-payment'));

        $originalInvoiceBefore = (array) DB::table('finance_invoices')->where('id', $originalId->value)->first();
        $originalRevisionBefore = (array) DB::table('finance_document_revisions')->where('id', $original->revisionId)->first();
        $allocationsBefore = DB::table('finance_payment_allocations')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $batchesBefore = DB::table('finance_payment_allocation_batches')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $originalPdf = $storage->documents[$original->pdfPath];

        $cancelled = (new CancelInvoice($repository, $finalize, $clock))->handle(
            new CancelInvoiceData($originalId),
            new IdempotencyKey('cancel-discounted-invoice'),
        );

        $this->assertSame('credit_note', $cancelled->invoice->kind);
        $this->assertSame('RE-2026-0002', $cancelled->invoice->number);
        $this->assertSame('2026-08-29', $cancelled->invoice->issueDate->format('Y-m-d'));
        $this->assertSame('2026-08-29', $cancelled->invoice->dueDate->format('Y-m-d'));
        $this->assertSame(-22_500, $cancelled->invoice->netMinor);
        $this->assertSame(-3_735, $cancelled->invoice->vatMinor);
        $this->assertSame(-26_235, $cancelled->invoice->grossMinor);
        $this->assertSame(-26_235, $cancelled->invoice->openMinor);
        $cancelledLines = $cancelled->invoice->snapshot['lines'] ?? null;
        $cancelledDiscount = $cancelled->invoice->snapshot['discount'] ?? null;
        $this->assertIsArray($cancelledLines);
        $this->assertIsArray($cancelledDiscount);
        $this->assertSame(['-2.0000', '-1.0000'], array_column($cancelledLines, 'quantity'));
        $this->assertSame(1_000, $cancelledDiscount['basis_points']);
        $this->assertSame($original->invoice->uuid, $cancelled->invoice->sourceKey);
        $this->assertSame($original->revisionId, $cancelled->invoice->sourceRevisionId);
        $this->assertNotSame($original->revisionId, $cancelled->revisionId);
        $this->assertNotSame($original->pdfPath, $cancelled->pdfPath);
        $this->assertNotSame($original->pdfSha256, $cancelled->pdfSha256);
        $this->assertDatabaseHas('finance_invoices', [
            'id' => $cancelled->invoice->id->value,
            'user_id' => $owner->id,
            'kind' => 'credit_note',
            'cancels_invoice_id' => $originalId->value,
            'source_type' => 'cancellation',
            'source_key' => $original->invoice->uuid,
        ]);
        $this->assertDatabaseHas('finance_stock_movements', [
            'user_id' => $owner->id,
            'finance_product_id' => $hardware->id,
            'qty' => '2.0000',
            'reason' => 'sale',
            'ref_type' => 'finance_invoice',
            'ref_id' => $cancelled->invoice->uuid,
        ]);
        $this->assertSame('10.0000', (string) $hardware->fresh()?->stock_qty);
        $this->assertSame('cancelled', $repository->get($originalId)->status);
        $this->assertGreaterThanOrEqual(1, DB::table('finance_document_activities')
            ->where('document_series_id', $originalInvoiceBefore['document_series_id'])
            ->where('type', 'invoice.cancellation.requested')
            ->count());
        $this->assertGreaterThanOrEqual(1, DB::table('finance_document_activities')
            ->where('document_series_id', DB::table('finance_invoices')->where('id', $cancelled->invoice->id->value)->value('document_series_id'))
            ->where('type', 'invoice.finalized')
            ->count());

        $this->assertSame($originalInvoiceBefore, (array) DB::table('finance_invoices')->where('id', $originalId->value)->first());
        $this->assertSame($originalRevisionBefore, (array) DB::table('finance_document_revisions')->where('id', $original->revisionId)->first());
        $this->assertSame($allocationsBefore, DB::table('finance_payment_allocations')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($batchesBefore, DB::table('finance_payment_allocation_batches')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($originalPdf, $storage->documents[$original->pdfPath]);
        $this->assertDatabaseCount('finance_payments', 1);
    }

    public function test_draft_invoice_is_rejected_without_creating_a_cancellation(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$repository, $cancel] = $this->cancellationEnvironment();
        $draft = $repository->createDraft(new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME GmbH'],
            lines: [new InvoiceLineData('Service', '1.0000', 10_000, 1_900, 'h', null, 'service')],
            discount: Discount::none('EUR'),
        ));

        try {
            $cancel->handle(
                new CancelInvoiceData($draft),
                new IdempotencyKey('cancel-draft-invoice'),
            );
            $this->fail('A draft invoice was cancelled.');
        } catch (DomainException $exception) {
            $this->assertSame('invoice_not_cancellable', $exception->getMessage());
        }

        $this->assertDatabaseCount('finance_invoices', 1);
        $this->assertSame(0, DB::table('finance_invoices')->whereNotNull('cancels_invoice_id')->count());
        $this->assertSame(0, DB::table('finance_idempotency_records')->where('operation', 'invoice.cancel')->count());
    }

    public function test_credit_note_cannot_itself_be_cancelled(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$repository, $cancel, $finalize] = $this->cancellationEnvironment();
        $original = $repository->createDraft(new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME GmbH'],
            lines: [new InvoiceLineData('Service', '1.0000', 10_000, 1_900, 'h', null, 'service')],
            discount: Discount::none('EUR'),
        ));
        $finalize->handle($original, new IdempotencyKey('finalize-credit-parent'));
        $credit = $cancel->handle(
            new CancelInvoiceData($original),
            new IdempotencyKey('create-credit-note'),
        );

        try {
            $cancel->handle(
                new CancelInvoiceData($credit->invoice->id),
                new IdempotencyKey('cancel-credit-note'),
            );
            $this->fail('A credit note was cancelled.');
        } catch (DomainException $exception) {
            $this->assertSame('credit_note_cannot_be_cancelled', $exception->getMessage());
        }

        $this->assertSame(2, DB::table('finance_invoices')->count());
    }

    public function test_retries_and_a_different_key_return_the_single_cancellation_while_key_reuse_conflicts(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$repository, $cancel, $finalize, $renderer] = $this->cancellationEnvironment();
        $firstOriginal = $repository->createDraft($this->simpleDraft());
        $secondOriginal = $repository->createDraft($this->simpleDraft());
        $finalize->handle($firstOriginal, new IdempotencyKey('finalize-first-idempotent-cancel'));
        $finalize->handle($secondOriginal, new IdempotencyKey('finalize-second-idempotent-cancel'));
        $key = new IdempotencyKey('cancel-one-original');

        $first = $cancel->handle(new CancelInvoiceData($firstOriginal), $key);
        $sameKeyReplay = $cancel->handle(new CancelInvoiceData($firstOriginal), $key);
        $newKeyReplay = $cancel->handle(
            new CancelInvoiceData($firstOriginal),
            new IdempotencyKey('cancel-one-original-new-transport-key'),
        );

        $this->assertSame($first->invoice->id->value, $sameKeyReplay->invoice->id->value);
        $this->assertSame($first->invoice->id->value, $newKeyReplay->invoice->id->value);
        $this->assertSame(1, DB::table('finance_invoices')
            ->where('cancels_invoice_id', $firstOriginal->value)
            ->count());
        $this->assertSame(3, $renderer->calls);

        try {
            $cancel->handle(new CancelInvoiceData($secondOriginal), $key);
            $this->fail('One cancellation idempotency key accepted a different original invoice.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_conflict', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('finance_invoices')
            ->where('cancels_invoice_id', $secondOriginal->value)
            ->count());
    }

    public function test_paid_invoice_can_be_cancelled_without_fabricating_a_refund_payment(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$repository, $cancel, $finalize] = $this->cancellationEnvironment();
        $originalId = $repository->createDraft($this->simpleDraft());
        $original = $finalize->handle($originalId, new IdempotencyKey('finalize-paid-cancellation'));
        $payment = app(RecordPayment::class)->handle(new RecordPaymentData(
            11_900,
            'EUR',
            new DateTimeImmutable('2026-08-29T08:00:00+00:00'),
            $original->invoice->number,
            'ACME GmbH',
        ), new IdempotencyKey('record-paid-cancellation'));
        app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment->id,
            [new AllocationLineData($originalId, 11_900)],
        ), new IdempotencyKey('allocate-paid-cancellation'));
        $this->assertSame('paid', $repository->get($originalId)->status);

        $credit = $cancel->handle(
            new CancelInvoiceData($originalId),
            new IdempotencyKey('cancel-paid-invoice'),
        );

        $this->assertSame(-11_900, $credit->invoice->grossMinor);
        $this->assertSame(-11_900, $credit->invoice->openMinor);
        $this->assertSame(0, $credit->invoice->allocatedMinor);
        $this->assertSame('cancelled', $repository->get($originalId)->status);
        $this->assertDatabaseCount('finance_payments', 1);
        $this->assertDatabaseCount('finance_payment_allocations', 1);
    }

    public function test_fixed_discount_is_recomputed_as_an_exact_signed_reversal(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$repository, $cancel, $finalize] = $this->cancellationEnvironment();
        $originalId = $repository->createDraft(new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME GmbH'],
            lines: [new InvoiceLineData('Service', '1.0000', 10_000, 1_900, 'h', null, 'service')],
            discount: Discount::fixed(Money::fromMinor(1_000, 'EUR')),
            controlNetMinor: 9_000,
            controlVatMinor: 1_710,
            controlGrossMinor: 10_710,
        ));
        $finalize->handle($originalId, new IdempotencyKey('finalize-fixed-discount-cancellation'));

        $credit = $cancel->handle(
            new CancelInvoiceData($originalId),
            new IdempotencyKey('cancel-fixed-discount-invoice'),
        );

        $this->assertSame(-9_000, $credit->invoice->netMinor);
        $this->assertSame(-1_710, $credit->invoice->vatMinor);
        $this->assertSame(-10_710, $credit->invoice->grossMinor);
        $creditDiscount = $credit->invoice->snapshot['discount'] ?? null;
        $this->assertIsArray($creditDiscount);
        $this->assertSame(-1_000, $creditDiscount['fixed_minor']);
    }

    public function test_pdf_write_is_compensated_and_number_and_inventory_roll_back_before_safe_retry(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$repository, $workingCancel, $finalize, , $storage] = $this->cancellationEnvironment();
        $originalId = $repository->createDraft($this->simpleDraft());
        $original = $finalize->handle($originalId, new IdempotencyKey('finalize-rollback-parent'));
        $clock = new InvoiceCancellationClock(new DateTimeImmutable('2026-08-29T10:15:00+02:00'));
        $failingFinalize = new FinalizeInvoice(
            $repository,
            new LockedInvoiceNumberAllocator,
            new FailingCancellationInventory,
            new InvoiceCancellationRenderer,
            $storage,
            new NullLogger,
        );
        $failingCancel = new CancelInvoice($repository, $failingFinalize, $clock);
        $key = new IdempotencyKey('cancel-with-inventory-failure');

        try {
            $failingCancel->handle(new CancelInvoiceData($originalId), $key);
            $this->fail('The cancellation inventory failure was not observed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('cancellation inventory failed', $exception->getMessage());
        }

        $draft = DB::table('finance_invoices')->where('cancels_invoice_id', $originalId->value)->first();
        $this->assertNotNull($draft);
        $this->assertNull($draft->number);
        $this->assertSame('draft', $draft->workflow_status);
        $this->assertCount(1, $storage->documents);
        $this->assertArrayHasKey($original->pdfPath, $storage->documents);
        $nextSequence = DB::table('finance_invoice_sequences')
            ->where('user_id', $owner->id)
            ->where('year', 2026)
            ->value('next_sequence');
        $this->assertIsInt($nextSequence);
        $this->assertSame(2, $nextSequence);
        $this->assertSame(0, DB::table('finance_stock_movements')
            ->where('ref_id', $draft->uuid)
            ->count());
        $draftId = $draft->id ?? null;
        $this->assertIsInt($draftId);
        try {
            $repository->updateDraft(
                new InvoiceId($draftId),
                $this->simpleDraft(),
                0,
            );
            $this->fail('A cancellation checkpoint draft was edited.');
        } catch (DomainException $exception) {
            $this->assertSame('cancellation_draft_not_editable', $exception->getMessage());
        }

        $recovered = $workingCancel->handle(new CancelInvoiceData($originalId), $key);

        $this->assertSame($draftId, $recovered->invoice->id->value);
        $this->assertSame('finalized', $recovered->invoice->status);
        $this->assertSame('2026-0002', $recovered->invoice->number);
        $this->assertCount(2, $storage->documents);
    }

    public function test_postgresql_serializes_two_keys_to_one_cancellation_draft_when_configured(): void
    {
        $this->withIsolatedPostgresSchema(function (string $postgresUrl, string $schema): void {
            $now = new DateTimeImmutable('2026-08-29T08:15:00+00:00');
            $seriesId = DB::table('finance_document_series')->insertGetId([
                'user_id' => 1,
                'uuid' => '018f4ca3-224d-7d8d-9f00-123456789abc',
                'document_type' => 'invoice',
                'status' => 'finalized',
                'created_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $snapshot = [
                'customer' => ['name' => 'ACME GmbH'],
                'issue_date' => '2026-08-28',
                'due_date' => '2026-09-11',
                'currency' => 'EUR',
                'lines' => [[
                    'description' => 'Service',
                    'quantity' => '1.0000',
                    'quantity_scaled' => 10_000,
                    'unit_price_minor' => 10_000,
                    'tax_rate_basis_points' => 1_900,
                    'unit' => 'h',
                    'product_id' => null,
                    'kind' => 'service',
                ]],
                'discount' => ['basis_points' => 0, 'fixed_minor' => 0, 'currency' => 'EUR'],
                'totals' => ['net_minor' => 10_000, 'vat_minor' => 1_900, 'gross_minor' => 11_900],
            ];
            $revisionId = DB::table('finance_document_revisions')->insertGetId([
                'user_id' => 1,
                'document_series_id' => $seriesId,
                'revision_number' => 1,
                'status' => 'published',
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'net_minor' => 10_000,
                'vat_minor' => 1_900,
                'gross_minor' => 11_900,
                'currency' => 'EUR',
                'pdf_path' => 'finance/revisions/aa/'.str_repeat('a', 64).'.pdf',
                'pdf_sha256' => str_repeat('a', 64),
                'published_at' => $now,
                'created_by' => 1,
                'created_at' => $now,
            ]);
            $invoiceId = DB::table('finance_invoices')->insertGetId([
                'user_id' => 1,
                'uuid' => '018f4ca3-224d-7d8d-9f00-123456789abc',
                'document_series_id' => $seriesId,
                'current_revision_id' => $revisionId,
                'kind' => 'invoice',
                'number' => 'RE-2026-0001',
                'year' => 2026,
                'sequence' => 1,
                'issue_date' => '2026-08-28',
                'due_date' => '2026-09-11',
                'workflow_status' => 'finalized',
                'finalized_at' => $now,
                'allocated_minor' => 0,
                'open_minor' => 11_900,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            Schema::create('finance_task11_cancellation_barrier', static function (Blueprint $table): void {
                $table->string('worker')->primary();
            });

            $workers = [
                $this->startPostgresCancellationWorker($postgresUrl, $schema, 'first', $invoiceId),
                $this->startPostgresCancellationWorker($postgresUrl, $schema, 'second', $invoiceId),
            ];
            $ids = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $decoded = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                $this->assertIsArray($decoded);
                $id = $decoded['invoice_id'] ?? null;
                $this->assertIsInt($id);
                $ids[] = $id;
            }

            $this->assertSame($ids[0], $ids[1]);
            $this->assertSame(1, DB::table('finance_invoices')->where('cancels_invoice_id', $invoiceId)->count());
            $this->assertSame(1, DB::table('finance_document_activities')
                ->where('document_series_id', $seriesId)
                ->where('type', 'invoice.cancellation.requested')
                ->count());
        });
    }

    public function test_cancellation_is_owner_scoped_and_foreign_ids_create_no_checkpoint(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $this->actingAs($owner);
        [$repository, $cancel, $finalize] = $this->cancellationEnvironment();
        $originalId = $repository->createDraft($this->simpleDraft());
        $finalize->handle($originalId, new IdempotencyKey('finalize-owner-scoped-cancellation'));
        $this->actingAs($foreign);

        try {
            $cancel->handle(
                new CancelInvoiceData($originalId),
                new IdempotencyKey('foreign-cancellation-attempt'),
            );
            $this->fail('A foreign owner cancelled an invoice.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('finance_invoices', 1);
        $this->assertSame(0, DB::table('finance_idempotency_records')
            ->where('user_id', $foreign->id)
            ->where('operation', 'invoice.cancel')
            ->count());
    }

    /** @return array{EloquentInvoiceRepository, CancelInvoice, FinalizeInvoice, InvoiceCancellationRenderer, InvoiceCancellationStorage} */
    private function cancellationEnvironment(): array
    {
        $clock = new InvoiceCancellationClock(new DateTimeImmutable('2026-08-29T10:15:00+02:00'));
        $repository = new EloquentInvoiceRepository(new EloquentIdempotencyStore($clock), $clock);
        $renderer = new InvoiceCancellationRenderer;
        $storage = new InvoiceCancellationStorage;
        $finalize = new FinalizeInvoice(
            $repository,
            new LockedInvoiceNumberAllocator,
            new LegacyStockLedgerAdapter,
            $renderer,
            $storage,
            new NullLogger,
        );

        return [$repository, new CancelInvoice($repository, $finalize, $clock), $finalize, $renderer, $storage];
    }

    private function simpleDraft(): InvoiceDraftData
    {
        return new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME GmbH'],
            lines: [new InvoiceLineData('Service', '1.0000', 10_000, 1_900, 'h', null, 'service')],
            discount: Discount::none('EUR'),
        );
    }

    /** @param callable(string, string): void $test */
    private function withIsolatedPostgresSchema(callable $test): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');
        if (! extension_loaded('pdo_pgsql') || ! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped(
                'Set FINANCE_TEST_PGSQL_URL and install pdo_pgsql to run invoice cancellation concurrency.',
            );
        }
        $postgres = config('database.connections.pgsql');
        if (! is_array($postgres)) {
            throw new RuntimeException('PostgreSQL connection configuration is unavailable.');
        }
        $default = DB::getDefaultConnection();
        $connectionName = 'pgsql_invoice_cancellation';
        $schema = 'finance_invoice_task11_'.bin2hex(random_bytes(8));
        config(["database.connections.{$connectionName}" => array_merge($postgres, [
            'url' => $postgresUrl,
            'search_path' => 'public',
        ])]);
        DB::purge($connectionName);
        $connection = DB::connection($connectionName);
        $created = false;

        try {
            $connection->statement("CREATE SCHEMA \"{$schema}\"");
            $created = true;
            $connection->statement("SET search_path TO \"{$schema}\"");
            DB::setDefaultConnection($connectionName);
            Schema::clearResolvedInstance('db.schema');
            Schema::create('users', static function (Blueprint $table): void {
                $table->id();
            });
            foreach ([
                '2026_08_28_100000_create_finance_document_core.php',
                '2026_08_28_110000_create_finance_invoices.php',
            ] as $migrationFile) {
                $migration = require database_path('migrations/'.$migrationFile);
                if (! is_object($migration) || ! is_callable([$migration, 'up'])) {
                    throw new RuntimeException("Finance migration {$migrationFile} is unavailable.");
                }
                $migration->up();
            }
            DB::table('users')->insert(['id' => 1]);
            $test($postgresUrl, $schema);
        } finally {
            DB::setDefaultConnection($default);
            Schema::clearResolvedInstance('db.schema');
            try {
                if ($created) {
                    $connection->statement('SET search_path TO public');
                    $connection->statement("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE");
                }
            } finally {
                DB::purge($connectionName);
            }
        }
    }

    private function startPostgresCancellationWorker(
        string $postgresUrl,
        string $schema,
        string $worker,
        int $invoiceId,
    ): Process {
        $script = <<<'PHP'
            require getcwd().'/vendor/autoload.php';
            $app = require getcwd().'/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            $url = getenv('FINANCE_TEST_PGSQL_URL');
            $schema = getenv('FINANCE_TEST_PGSQL_SCHEMA');
            $worker = getenv('FINANCE_TEST_WORKER');
            if (! is_string($url) || ! is_string($schema) || ! is_string($worker)
                || preg_match('/\Afinance_invoice_task11_[0-9a-f]{16}\z/D', $schema) !== 1) {
                exit(90);
            }
            $base = config('database.connections.pgsql');
            foreach (['pgsql_task11_worker', 'pgsql_task11_barrier'] as $name) {
                config(["database.connections.{$name}" => array_merge(is_array($base) ? $base : [], [
                    'driver' => 'pgsql', 'url' => $url, 'search_path' => $schema,
                ])]);
                \Illuminate\Support\Facades\DB::purge($name);
                \Illuminate\Support\Facades\DB::connection($name)
                    ->statement('SET search_path TO "'.$schema.'"');
            }
            \Illuminate\Support\Facades\DB::setDefaultConnection('pgsql_task11_worker');
            \Illuminate\Support\Facades\Schema::clearResolvedInstance('db.schema');
            \Illuminate\Support\Facades\DB::statement("SET lock_timeout TO '10s'");
            $barrier = \Illuminate\Support\Facades\DB::connection('pgsql_task11_barrier');
            $barrier->table('finance_task11_cancellation_barrier')->insert(['worker' => $worker]);
            $deadline = microtime(true) + 10;
            while ($barrier->table('finance_task11_cancellation_barrier')->count() < 2) {
                if (microtime(true) >= $deadline) {
                    exit(91);
                }
                usleep(20_000);
            }
            $owner = new \App\Models\User;
            $owner->forceFill(['id' => 1]);
            \Illuminate\Support\Facades\Auth::setUser($owner);
            $clock = new readonly class implements \App\Modules\Finance\Application\Ports\Clock {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-08-29T08:15:00+00:00');
                }
            };
            $repository = new \App\Modules\Finance\Infrastructure\Persistence\EloquentInvoiceRepository(
                new \App\Modules\Finance\Infrastructure\Persistence\EloquentIdempotencyStore($clock),
                $clock,
            );
            try {
                $id = $repository->createCancellationDraft(
                    new \App\Modules\Finance\Application\DTOs\Invoices\InvoiceId((int) getenv('FINANCE_TEST_INVOICE_ID')),
                    new \App\Modules\Finance\Application\DTOs\IdempotencyKey('pgsql-cancellation-'.$worker),
                    static fn ($original, int $revisionId, string $snapshotSha256) =>
                        new \App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource(
                            'cancellation',
                            $original->uuid,
                            $revisionId,
                            $snapshotSha256,
                            new \App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData(
                                new \DateTimeImmutable('2026-08-29'),
                                new \DateTimeImmutable('2026-08-29'),
                                'EUR',
                                ['name' => 'ACME GmbH'],
                                [new \App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData(
                                    'Service', '-1.0000', 10_000, 1_900, 'h', null, 'service',
                                )],
                                \App\Modules\Finance\Domain\Shared\Discount::none('EUR'),
                            ),
                        ),
                );
                echo json_encode(['invoice_id' => $id->value], JSON_THROW_ON_ERROR);
                exit(0);
            } catch (Throwable $exception) {
                fwrite(STDERR, $exception::class.':'.$exception->getMessage());
                exit(92);
            }
            PHP;

        $process = new Process([PHP_BINARY, '-r', $script], base_path(), [
            'FINANCE_TEST_PGSQL_URL' => $postgresUrl,
            'FINANCE_TEST_PGSQL_SCHEMA' => $schema,
            'FINANCE_TEST_WORKER' => $worker,
            'FINANCE_TEST_INVOICE_ID' => (string) $invoiceId,
        ], null, 25);
        $process->start();

        return $process;
    }

    private function product(int $ownerId, string $kind, string $stock, bool $tracked): FinanceProduct
    {
        $product = new FinanceProduct;
        $product->forceFill([
            'user_id' => $ownerId,
            'kind' => $kind,
            'name' => ucfirst($kind),
            'price_net' => 100,
            'track_stock' => $tracked,
            'stock_qty' => $stock,
            'version' => 0,
        ])->save();

        return $product;
    }
}

final readonly class InvoiceCancellationClock implements Clock
{
    public function __construct(private DateTimeImmutable $at) {}

    public function now(): DateTimeImmutable
    {
        return $this->at;
    }
}

final class InvoiceCancellationRenderer implements DocumentRenderer
{
    public int $calls = 0;

    /** @param array<array-key, mixed> $snapshot */
    public function render(array $snapshot): string
    {
        $this->calls++;

        return '%PDF-cancellation-'.json_encode($snapshot, JSON_THROW_ON_ERROR);
    }
}

final class InvoiceCancellationStorage implements DocumentStorage
{
    /** @var array<string, string> */
    public array $documents = [];

    public function putPdf(string $seriesUuid, string $bytes, DocumentStorageWrite $write): StoredDocument
    {
        $path = "finance/revisions/{$seriesUuid}/{$write->ownershipToken}.pdf";
        $this->documents[$path] = $bytes;

        return new StoredDocument($path, hash('sha256', $bytes));
    }

    public function delete(DocumentStorageWrite $write): void
    {
        foreach (array_keys($this->documents) as $path) {
            if (str_contains($path, $write->ownershipToken)) {
                unset($this->documents[$path]);
            }
        }
    }
}

final class FailingCancellationInventory implements InventoryMovementPort
{
    public function recordInvoiceSale(
        int $ownerId,
        string $invoiceUuid,
        array $quantityScaledByProduct,
        DateTimeImmutable $occurredAt,
    ): void {
        throw new RuntimeException('cancellation inventory failed');
    }
}
