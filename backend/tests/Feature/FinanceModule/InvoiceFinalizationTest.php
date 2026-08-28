<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\FinanceProduct;
use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\Commands\Invoices\FinalizeInvoice;
use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\InventoryMovementPort;
use App\Modules\Finance\Application\Ports\InvoiceNumberAllocator;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Infrastructure\Inventory\LegacyStockLedgerAdapter;
use App\Modules\Finance\Infrastructure\Persistence\EloquentIdempotencyStore;
use App\Modules\Finance\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Modules\Finance\Infrastructure\Persistence\LockedInvoiceNumberAllocator;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\SystemClock;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class InvoiceFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_hardening_covers_fresh_install_and_existing_upgrade_with_safe_down_up(): void
    {
        $this->assertFourDecimalStockStorage();
        $this->assertTrue(Schema::hasIndex(
            'finance_stock_movements',
            'finance_stock_movements_invoice_sale_unique',
            'unique',
        ));

        $freshInstallMigration = require database_path(
            'migrations/2027_02_28_100100_guarantee_invoice_stock_idempotency.php',
        );
        $upgradeMigration = require database_path(
            'migrations/2026_08_28_110050_harden_invoice_stock_idempotency.php',
        );

        $freshInstallMigration->down();
        if (DB::getDriverName() === 'pgsql') {
            $this->assertSame(3, $this->numericScale('finance_products', 'stock_qty'));
        }
        $this->assertFalse(Schema::hasIndex(
            'finance_stock_movements',
            'finance_stock_movements_invoice_sale_unique',
            'unique',
        ));

        $upgradeMigration->up();
        $this->assertFourDecimalStockStorage();
        $this->assertTrue(Schema::hasIndex(
            'finance_stock_movements',
            'finance_stock_movements_invoice_sale_unique',
            'unique',
        ));

        $upgradeMigration->down();
        $freshInstallMigration->up();
        $this->assertFourDecimalStockStorage();
        $this->assertTrue(Schema::hasIndex(
            'finance_stock_movements',
            'finance_stock_movements_invoice_sale_unique',
            'unique',
        ));
    }

    public function test_stock_hardening_down_refuses_lossy_narrowing_without_schema_change(): void
    {
        $owner = User::factory()->create();
        $hardware = $this->product((int) $owner->id, 'hardware', '0.0001', true);
        $migration = require database_path(
            'migrations/2027_02_28_100100_guarantee_invoice_stock_idempotency.php',
        );

        try {
            $migration->down();
            $this->fail('A lossy scale-four stock migration rollback was accepted.');
        } catch (\LogicException $exception) {
            $this->assertSame(
                'Invoice stock quantities cannot be safely narrowed to scale 3.',
                $exception->getMessage(),
            );
        }

        $this->assertFourDecimalStockStorage();
        $this->assertTrue(Schema::hasIndex(
            'finance_stock_movements',
            'finance_stock_movements_invoice_sale_unique',
            'unique',
        ));
        $this->assertSame('0.0001', (string) $hardware->fresh()?->stock_qty);
    }

    public function test_sqlite_stock_hardening_up_keeps_the_invoice_index_partial(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite table-rebuild index semantics are SQLite-specific.');
        }
        $owner = User::factory()->create();
        $hardware = $this->product((int) $owner->id, 'hardware', '1.0000', true);
        $migration = require database_path(
            'migrations/2027_02_28_100100_guarantee_invoice_stock_idempotency.php',
        );

        $migration->up();

        foreach (['first return', 'second return'] as $note) {
            DB::table('finance_stock_movements')->insert([
                'user_id' => $owner->id,
                'finance_product_id' => $hardware->id,
                'qty' => '0.0001',
                'reason' => 'return',
                'ref_type' => 'finance_invoice',
                'ref_id' => 'same-non-sale-reference',
                'note' => $note,
                'occurred_at' => now(),
                'created_at' => now(),
            ]);
        }

        $this->assertSame(2, DB::table('finance_stock_movements')
            ->where('ref_id', 'same-non-sale-reference')
            ->count());
    }

    public function test_finalization_is_atomic_exact_and_idempotent(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->update([
            'company_name' => 'Ledgerline GmbH',
            'company_address' => "Main Street 1\n10115 Berlin",
            'company_email' => 'billing@example.test',
            'invoice_number_format' => 'RE-YYYY-NNNN',
            'invoice_next_number' => 7,
        ]);
        $hardware = $this->product((int) $owner->id, 'hardware', '10.0000', true);
        $service = $this->product((int) $owner->id, 'service', '0.0000', false);
        $repository = $this->repository();
        $invoiceId = $repository->createDraft(new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME'],
            lines: [
                new InvoiceLineData('Switch part A', '1.2500', 10_000, 1_900, 'pc', (int) $hardware->id, 'hardware'),
                new InvoiceLineData('Switch part B', '0.7500', 10_000, 1_900, 'pc', (int) $hardware->id, 'hardware'),
                new InvoiceLineData('Installation', '2.0000', 5_000, 1_900, 'h', (int) $service->id, 'service'),
            ],
            discount: Discount::none('EUR'),
            controlNetMinor: 30_000,
            controlVatMinor: 5_700,
            controlGrossMinor: 35_700,
        ));
        $renderer = new InvoiceFinalizationRenderer;
        $storage = new InvoiceFinalizationStorage;
        $command = $this->command($repository, $renderer, $storage);
        $key = new IdempotencyKey('finalize-invoice-atomic-1');

        $finalized = $command->handle($invoiceId, $key);
        $replayed = $command->handle($invoiceId, $key);

        $this->assertSame($finalized->invoice->id->value, $replayed->invoice->id->value);
        $this->assertSame('RE-2026-0007', $finalized->invoice->number);
        $this->assertSame('finalized', $finalized->invoice->status);
        $this->assertSame(1, $finalized->invoice->version);
        $this->assertSame($finalized->revisionId, $replayed->revisionId);
        $this->assertSame($finalized->pdfPath, $replayed->pdfPath);
        $this->assertSame($finalized->pdfSha256, $replayed->pdfSha256);
        $this->assertSame(1, $renderer->calls);
        $this->assertSame(1, $storage->puts);
        $this->assertSame('invoice', $renderer->snapshot['document_type']);
        $this->assertSame('RE-2026-0007', $renderer->snapshot['document_number']);
        $this->assertSame('Ledgerline GmbH', $renderer->snapshot['company']['name']);
        $this->assertSame(35_700, $renderer->snapshot['totals']['gross_minor']);
        $this->assertDatabaseHas('finance_invoices', [
            'id' => $invoiceId->value,
            'number' => 'RE-2026-0007',
            'year' => 2026,
            'sequence' => 7,
            'workflow_status' => 'finalized',
            'version' => 1,
        ]);
        $this->assertDatabaseHas('finance_document_revisions', [
            'id' => $finalized->revisionId,
            'status' => 'published',
            'pdf_path' => $finalized->pdfPath,
            'pdf_sha256' => $finalized->pdfSha256,
        ]);
        $this->assertSame(1, DB::table('finance_document_activities')->where('type', 'invoice.finalized')->count());
        $this->assertSame(1, DB::table('finance_document_activities')->where('type', 'revision.published')->count());
        $this->assertDatabaseHas('finance_stock_movements', [
            'user_id' => $owner->id,
            'finance_product_id' => $hardware->id,
            'qty' => '-2.0000',
            'reason' => 'sale',
            'ref_type' => 'finance_invoice',
            'ref_id' => $finalized->invoice->uuid,
        ]);
        $this->assertSame(1, DB::table('finance_stock_movements')->where('ref_id', $finalized->invoice->uuid)->count());
        $this->assertSame('8.0000', (string) $hardware->fresh()?->stock_qty);
        $this->assertSame('0.0000', (string) $service->fresh()?->stock_qty);
        $this->assertDatabaseHas('finance_invoice_sequences', [
            'user_id' => $owner->id,
            'series_key' => 'invoice',
            'year' => 2026,
            'next_sequence' => 8,
        ]);
    }

    public function test_invalid_canonical_input_fails_before_number_allocation(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $repository = $this->repository();
        $invoiceId = $repository->createDraft($this->simpleDraft());
        $invoice = DB::table('finance_invoices')->where('id', $invoiceId->value)->first();
        $this->assertNotNull($invoice);
        $snapshot = json_decode((string) DB::table('finance_document_revisions')
            ->where('id', $invoice->current_revision_id)
            ->value('snapshot'), true, flags: JSON_THROW_ON_ERROR);
        $snapshot['lines'][0]['quantity'] = 'not-a-decimal';
        DB::table('finance_document_revisions')
            ->where('id', $invoice->current_revision_id)
            ->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        $numbers = new InvoiceFinalizationNumberAllocator;

        try {
            $this->command(
                $repository,
                new InvoiceFinalizationRenderer,
                new InvoiceFinalizationStorage,
                $numbers,
            )->handle($invoiceId, new IdempotencyKey('invalid-finalization-input'));
            $this->fail('Invalid canonical input crossed the number-allocation boundary.');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, $numbers->calls);
        $this->assertDraftWasNotFinalized($invoiceId->value, (int) $invoice->current_revision_id);
        $this->assertSame(0, DB::table('finance_idempotency_records')->where('user_id', $owner->id)->count());
    }

    public function test_every_failure_stage_rolls_back_database_effects_and_compensates_storage(): void
    {
        foreach (['number', 'render', 'storage', 'inventory', 'commit'] as $stage) {
            $owner = User::factory()->create();
            $this->actingAs($owner);
            $repository = $this->repository();
            $product = $this->product((int) $owner->id, 'hardware', '5.0000', true);
            $invoiceId = $repository->createDraft($this->simpleDraft((int) $product->id));
            $invoice = DB::table('finance_invoices')->where('id', $invoiceId->value)->first();
            $this->assertNotNull($invoice);
            $numbers = new InvoiceFinalizationNumberAllocator;
            $renderer = new InvoiceFinalizationRenderer;
            $storage = new InvoiceFinalizationStorage;
            $inventory = $stage === 'inventory'
                ? new FailingInvoiceInventory
                : new LegacyStockLedgerAdapter;
            if ($stage === 'number') {
                $numbers->failure = new RuntimeException('number failed');
            } elseif ($stage === 'render') {
                $renderer->failure = new RuntimeException('render failed');
            } elseif ($stage === 'storage') {
                $storage->failureAfterWrite = new RuntimeException('storage failed');
            } elseif ($stage === 'commit') {
                DB::unprepared(<<<'SQL'
                    CREATE TRIGGER invoice_finalization_commit_failure
                    BEFORE INSERT ON finance_document_activities
                    WHEN NEW.type = 'invoice.finalized'
                    BEGIN
                        SELECT RAISE(ABORT, 'invoice_finalization_commit_failure');
                    END
                    SQL);
            }

            try {
                $this->command($repository, $renderer, $storage, $numbers, $inventory)
                    ->handle($invoiceId, new IdempotencyKey("finalization-failure-{$stage}"));
                $this->fail("The {$stage} finalization failure was not observed.");
            } catch (\Throwable $exception) {
                $this->assertStringContainsString($stage, $exception->getMessage());
            } finally {
                if ($stage === 'commit') {
                    DB::unprepared('DROP TRIGGER IF EXISTS invoice_finalization_commit_failure');
                }
            }

            $this->assertDraftWasNotFinalized($invoiceId->value, (int) $invoice->current_revision_id);
            $this->assertSame(0, DB::table('finance_invoice_sequences')->where('user_id', $owner->id)->count());
            $this->assertSame(0, DB::table('finance_stock_movements')->where('user_id', $owner->id)->count());
            $this->assertSame(0, DB::table('finance_document_activities')
                ->where('user_id', $owner->id)
                ->where('type', 'invoice.finalized')
                ->count());
            $this->assertSame([], $storage->documents);
            $this->assertSame('5.0000', (string) $product->fresh()?->stock_qty);
            $this->assertSame(0, DB::table('finance_idempotency_records')->where('user_id', $owner->id)->count());
        }
    }

    public function test_inventory_sale_is_scale_four_owner_scoped_hardware_only_and_idempotent(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $hardware = $this->product((int) $owner->id, 'hardware', '1.0000', true);
        $service = $this->product((int) $owner->id, 'service', '0.0000', false);
        $foreignHardware = $this->product((int) $foreign->id, 'hardware', '1.0000', true);
        $adapter = new LegacyStockLedgerAdapter;
        $uuid = '018f4ca3-224d-7d8d-9f03-000000000071';
        $at = new DateTimeImmutable('2026-08-28T12:00:00+00:00');

        $adapter->recordInvoiceSale((int) $owner->id, $uuid, [(int) $hardware->id => 1], $at);
        $adapter->recordInvoiceSale((int) $owner->id, $uuid, [(int) $hardware->id => 1], $at);

        $this->assertSame(1, DB::table('finance_stock_movements')->where('ref_id', $uuid)->count());
        $this->assertSame('-0.0001', (string) DB::table('finance_stock_movements')
            ->where('ref_id', $uuid)
            ->selectRaw('CAST(qty AS TEXT) AS qty_exact')
            ->value('qty_exact'));
        $this->assertSame('0.9999', (string) DB::table('finance_products')
            ->where('id', $hardware->id)
            ->selectRaw('CAST(stock_qty AS TEXT) AS stock_qty_exact')
            ->value('stock_qty_exact'));
        $this->assertSame('0.9999', (string) $hardware->fresh()?->stock_qty);

        try {
            DB::table('finance_stock_movements')->insert([
                'user_id' => $owner->id,
                'finance_product_id' => $hardware->id,
                'qty' => '-0.0001',
                'reason' => 'sale',
                'ref_type' => 'finance_invoice',
                'ref_id' => $uuid,
                'occurred_at' => $at,
                'created_at' => $at,
            ]);
            $this->fail('The database accepted a duplicate invoice sale movement.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        foreach ([$service, $foreignHardware] as $invalidProduct) {
            try {
                $adapter->recordInvoiceSale(
                    (int) $owner->id,
                    '018f4ca3-224d-7d8d-9f03-000000000072',
                    [(int) $invalidProduct->id => 10_000],
                    $at,
                );
                $this->fail('A non-hardware or foreign product crossed the inventory boundary.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_invoice_inventory_rejects_scale_four_storage_overflow_atomically(): void
    {
        $owner = User::factory()->create();
        $hardware = $this->product((int) $owner->id, 'hardware', '999999999999.9999', true);
        $adapter = new LegacyStockLedgerAdapter;

        try {
            $adapter->recordInvoiceSale(
                (int) $owner->id,
                '018f4ca3-224d-7d8d-9f03-000000000073',
                [(int) $hardware->id => -1],
                new DateTimeImmutable('2026-08-28T12:00:00+00:00'),
            );
            $this->fail('An overflowing invoice inventory movement was accepted.');
        } catch (\DomainException $exception) {
            $this->assertSame('inventory_quantity_overflow', $exception->getMessage());
        }

        $this->assertSame('999999999999.9999', (string) $hardware->fresh()?->stock_qty);
        $this->assertSame(0, DB::table('finance_stock_movements')->where('user_id', $owner->id)->count());
    }

    public function test_zero_net_hardware_quantity_is_omitted_after_exact_aggregation(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $hardware = $this->product((int) $owner->id, 'hardware', '1.0000', true);
        $repository = $this->repository();
        $invoiceId = $repository->createDraft(new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME'],
            lines: [
                new InvoiceLineData('Hardware sale', '1.0000', 10_000, 1_900, 'pc', (int) $hardware->id, 'hardware'),
                new InvoiceLineData('Hardware return', '-1.0000', 10_000, 1_900, 'pc', (int) $hardware->id, 'hardware'),
            ],
            discount: Discount::none('EUR'),
            controlNetMinor: 0,
            controlVatMinor: 0,
            controlGrossMinor: 0,
        ));

        $finalized = $this->command(
            $repository,
            new InvoiceFinalizationRenderer,
            new InvoiceFinalizationStorage,
        )->handle($invoiceId, new IdempotencyKey('finalize-zero-net-hardware'));

        $this->assertSame('finalized', $finalized->invoice->status);
        $this->assertSame(0, DB::table('finance_stock_movements')->where('ref_id', $finalized->invoice->uuid)->count());
        $this->assertSame('1.0000', (string) $hardware->fresh()?->stock_qty);
    }

    public function test_hardware_cannot_be_marked_as_service_to_bypass_inventory(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $hardware = $this->product((int) $owner->id, 'hardware', '2.0000', true);
        $repository = $this->repository();
        $invoiceId = $repository->createDraft($this->productDraft((int) $hardware->id, 'service'));

        try {
            $this->command(
                $repository,
                new InvoiceFinalizationRenderer,
                new InvoiceFinalizationStorage,
            )->handle($invoiceId, new IdempotencyKey('hardware-marked-service'));
            $this->fail('Hardware marked as service bypassed authoritative inventory classification.');
        } catch (\DomainException $exception) {
            $this->assertSame('invoice_inventory_kind_mismatch', $exception->getMessage());
        }

        $this->assertDatabaseHas('finance_invoices', [
            'id' => $invoiceId->value,
            'workflow_status' => 'draft',
            'number' => null,
        ]);
        $this->assertSame(0, DB::table('finance_invoice_sequences')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_stock_movements')->where('user_id', $owner->id)->count());
    }

    public function test_hardware_with_no_snapshot_kind_uses_the_locked_product_kind(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $hardware = $this->product((int) $owner->id, 'hardware', '2.0000', true);
        $repository = $this->repository();
        $invoiceId = $repository->createDraft($this->productDraft((int) $hardware->id, null));

        $finalized = $this->command(
            $repository,
            new InvoiceFinalizationRenderer,
            new InvoiceFinalizationStorage,
        )->handle($invoiceId, new IdempotencyKey('hardware-without-kind'));

        $this->assertSame('hardware', $finalized->invoice->snapshot['lines'][0]['kind']);
        $this->assertDatabaseHas('finance_stock_movements', [
            'user_id' => $owner->id,
            'finance_product_id' => $hardware->id,
            'qty' => '-1.0000',
            'ref_type' => 'finance_invoice',
            'ref_id' => $finalized->invoice->uuid,
        ]);
        $this->assertSame('1.0000', (string) $hardware->fresh()?->stock_qty);
    }

    public function test_service_cannot_be_marked_as_hardware_to_create_inventory(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $service = $this->product((int) $owner->id, 'service', '0.0000', false);
        $repository = $this->repository();
        $invoiceId = $repository->createDraft($this->productDraft((int) $service->id, 'hardware'));

        try {
            $this->command(
                $repository,
                new InvoiceFinalizationRenderer,
                new InvoiceFinalizationStorage,
            )->handle($invoiceId, new IdempotencyKey('service-marked-hardware'));
            $this->fail('Service marked as hardware crossed authoritative inventory classification.');
        } catch (\DomainException $exception) {
            $this->assertSame('invoice_inventory_kind_mismatch', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('finance_invoice_sequences')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_stock_movements')->where('user_id', $owner->id)->count());
    }

    public function test_source_contract_survives_finalization_and_published_revision_is_immutable(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $repository = $this->repository();
        $source = new InvoiceDraftSource(
            'quote_revision',
            'quote-71:revision-4',
            71,
            hash('sha256', 'quote revision 71'),
            $this->simpleDraft(),
        );
        $created = $repository->createDraftFromSource(
            $source,
            new IdempotencyKey('create-source-before-finalization-71'),
        );

        $finalized = $this->command(
            $repository,
            new InvoiceFinalizationRenderer,
            new InvoiceFinalizationStorage,
        )->handle($created->id, new IdempotencyKey('finalize-source-71'));

        $this->assertSame($source->sourceType, $finalized->invoice->snapshot['source']['type']);
        $this->assertSame($source->sourceKey, $finalized->invoice->snapshot['source']['key']);
        $this->assertSame($source->sourceRevisionId, $finalized->invoice->snapshot['source']['revision_id']);
        $this->assertSame($source->sourceSnapshotSha256, $finalized->invoice->snapshot['source']['snapshot_sha256']);

        $revision = DocumentRevisionRecord::query()->withoutGlobalScopes()->findOrFail($finalized->revisionId);
        try {
            $revision->forceFill(['snapshot' => ['rewritten' => true]])->save();
            $this->fail('A finalized invoice revision was rewritten.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $replayedSource = $repository->createDraftFromSource(
            $source,
            new IdempotencyKey('source-after-finalization-71'),
        );
        $this->assertSame($finalized->invoice->id->value, $replayedSource->id->value);
        $this->assertSame('finalized', $replayedSource->status);
    }

    public function test_committed_number_allocations_are_never_reused_and_are_owner_year_scoped(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        UserSetting::for((int) $owner->id)->update([
            'invoice_number_format' => 'RE-YYYY-NNNN',
            'invoice_next_number' => 5,
        ]);
        UserSetting::for((int) $otherOwner->id)->update([
            'invoice_number_format' => 'RE-YYYY-NNNN',
            'invoice_next_number' => 5,
        ]);
        $allocator = new LockedInvoiceNumberAllocator;

        $first = $allocator->allocate((int) $owner->id, '2026-08-28');
        $second = $allocator->allocate((int) $owner->id, '2026-08-29');
        $other = $allocator->allocate((int) $otherOwner->id, '2026-08-28');
        $nextYear = $allocator->allocate((int) $owner->id, '2027-01-02');

        $this->assertSame(['number' => 'RE-2026-0005', 'year' => 2026, 'sequence' => 5], $first);
        $this->assertSame(['number' => 'RE-2026-0006', 'year' => 2026, 'sequence' => 6], $second);
        $this->assertSame(['number' => 'RE-2026-0005', 'year' => 2026, 'sequence' => 5], $other);
        $this->assertSame(['number' => 'RE-2027-0005', 'year' => 2027, 'sequence' => 5], $nextYear);
    }

    public function test_postgresql_concurrent_first_number_allocation_is_distinct_and_contiguous_when_configured(): void
    {
        $this->withIsolatedPostgresNumberSchema(function (string $postgresUrl, string $schema): void {
            DB::table('users')->insert(['id' => 1]);
            DB::table('user_settings')->insert([
                'user_id' => 1,
                'invoice_number_format' => 'RE-YYYY-NNNN',
                'invoice_next_number' => 1,
            ]);
            DB::statement('CREATE TABLE finance_task7_number_barrier (worker varchar(32) PRIMARY KEY)');
            $processes = [
                $this->startPostgresNumberWorker($postgresUrl, $schema, 'first'),
                $this->startPostgresNumberWorker($postgresUrl, $schema, 'second'),
            ];
            $results = [];
            foreach ($processes as $process) {
                $exitCode = $process->wait();
                $this->assertSame(0, $exitCode, $process->getErrorOutput().$process->getOutput());
                $result = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
                $this->assertIsArray($result);
                $results[] = $result;
            }

            $sequences = [(int) $results[0]['sequence'], (int) $results[1]['sequence']];
            sort($sequences, SORT_NUMERIC);
            $numbers = [(string) $results[0]['number'], (string) $results[1]['number']];
            sort($numbers, SORT_STRING);
            $this->assertSame([1, 2], $sequences);
            $this->assertSame(['RE-2026-0001', 'RE-2026-0002'], $numbers);
            $this->assertSame(3, (int) DB::table('finance_invoice_sequences')
                ->where('user_id', 1)
                ->where('series_key', 'invoice')
                ->where('year', 2026)
                ->value('next_sequence'));
        });
    }

    private function repository(): EloquentInvoiceRepository
    {
        $clock = new SystemClock;

        return new EloquentInvoiceRepository(new EloquentIdempotencyStore($clock), $clock);
    }

    private function command(
        EloquentInvoiceRepository $repository,
        InvoiceFinalizationRenderer $renderer,
        InvoiceFinalizationStorage $storage,
        ?InvoiceNumberAllocator $numbers = null,
        ?InventoryMovementPort $inventory = null,
    ): FinalizeInvoice {
        return new FinalizeInvoice(
            $repository,
            $numbers ?? new LockedInvoiceNumberAllocator,
            $inventory ?? new LegacyStockLedgerAdapter,
            $renderer,
            $storage,
            new NullLogger,
        );
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

    private function simpleDraft(?int $productId = null): InvoiceDraftData
    {
        return new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME'],
            lines: [new InvoiceLineData(
                'Hardware',
                '1.0000',
                10_000,
                1_900,
                'pc',
                $productId,
                $productId === null ? 'service' : 'hardware',
            )],
            discount: Discount::none('EUR'),
        );
    }

    private function productDraft(int $productId, ?string $snapshotKind): InvoiceDraftData
    {
        return new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME'],
            lines: [new InvoiceLineData(
                'Product line',
                '1.0000',
                10_000,
                1_900,
                'pc',
                $productId,
                $snapshotKind,
            )],
            discount: Discount::none('EUR'),
        );
    }

    private function assertDraftWasNotFinalized(int $invoiceId, int $revisionId): void
    {
        $this->assertDatabaseHas('finance_invoices', [
            'id' => $invoiceId,
            'number' => null,
            'year' => null,
            'sequence' => null,
            'workflow_status' => 'draft',
            'finalized_at' => null,
            'version' => 0,
        ]);
        $this->assertDatabaseHas('finance_document_revisions', [
            'id' => $revisionId,
            'status' => 'draft',
            'pdf_path' => null,
            'pdf_sha256' => null,
            'published_at' => null,
        ]);
    }

    private function numericScale(string $table, string $column): int
    {
        if (DB::getDriverName() === 'pgsql') {
            $schema = DB::scalar('SELECT current_schema()');
            if (! is_string($schema) || $schema === '') {
                return -1;
            }
            $scale = DB::table('information_schema.columns')
                ->where('table_schema', $schema)
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->value('numeric_scale');

            return is_numeric($scale) ? (int) $scale : -1;
        }

        $definition = collect(DB::select("PRAGMA table_info('{$table}')"))
            ->first(static fn (object $item): bool => ($item->name ?? null) === $column);
        if ($definition === null
            || ! is_string($definition->type ?? null)
            || preg_match('/\A(?:decimal|numeric)\(\d+,\s*(\d+)\)\z/i', $definition->type, $matches) !== 1) {
            return -1;
        }

        return (int) $matches[1];
    }

    private function assertFourDecimalStockStorage(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->assertSame(4, $this->numericScale('finance_products', 'stock_qty'));
            $this->assertSame(4, $this->numericScale('finance_products', 'stock_min'));
            $this->assertSame(4, $this->numericScale('finance_stock_movements', 'qty'));

            return;
        }

        foreach ([
            ['finance_products', 'stock_qty'],
            ['finance_products', 'stock_min'],
            ['finance_stock_movements', 'qty'],
        ] as [$table, $column]) {
            $definition = collect(DB::select("PRAGMA table_info('{$table}')"))
                ->first(static fn (object $item): bool => ($item->name ?? null) === $column);
            $this->assertNotNull($definition);
            $this->assertSame('text', strtolower((string) $definition->type));
        }
    }

    /** @param callable(string, string): void $test */
    private function withIsolatedPostgresNumberSchema(callable $test): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');
        if (! extension_loaded('pdo_pgsql') || ! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped(
                'Set FINANCE_TEST_PGSQL_URL and install pdo_pgsql to run the invoice number concurrency contract.',
            );
        }
        $postgresConfig = config('database.connections.pgsql');
        if (! is_array($postgresConfig)) {
            throw new RuntimeException('PostgreSQL connection configuration is unavailable.');
        }

        $defaultConnection = DB::getDefaultConnection();
        $connectionName = 'pgsql_invoice_task7';
        $schema = 'finance_invoice_task7_'.bin2hex(random_bytes(8));
        config([
            "database.connections.{$connectionName}" => array_merge(
                $postgresConfig,
                ['url' => $postgresUrl, 'search_path' => 'public'],
            ),
        ]);
        DB::purge($connectionName);
        $connection = DB::connection($connectionName);
        $schemaCreated = false;

        try {
            $connection->statement("CREATE SCHEMA \"{$schema}\"");
            $schemaCreated = true;
            $connection->statement("SET search_path TO \"{$schema}\"");
            DB::setDefaultConnection($connectionName);
            Schema::clearResolvedInstance('db.schema');
            Schema::create('users', static function (Blueprint $table): void {
                $table->id();
            });
            Schema::create('user_settings', static function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->primary();
                $table->string('invoice_number_format', 40)->nullable();
                $table->unsignedInteger('invoice_next_number')->default(1);
            });
            Schema::create('finance_partners', static function (Blueprint $table): void {
                $table->id();
            });
            foreach ([
                '2026_08_28_100000_create_finance_document_core.php',
                '2026_08_28_110000_create_finance_invoices.php',
                '2027_02_28_100000_create_finance_products.php',
                '2026_08_28_110050_harden_invoice_stock_idempotency.php',
            ] as $migrationFile) {
                $migration = require database_path('migrations/'.$migrationFile);
                $migration->up();
            }
            $this->assertSame(4, $this->numericScale('finance_products', 'stock_qty'));
            $this->assertTrue(Schema::hasIndex(
                'finance_stock_movements',
                'finance_stock_movements_invoice_sale_unique',
                'unique',
            ));

            $test($postgresUrl, $schema);
        } finally {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            DB::setDefaultConnection($defaultConnection);
            Schema::clearResolvedInstance('db.schema');
            try {
                if ($schemaCreated) {
                    $connection->statement('SET search_path TO public');
                    $connection->statement("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE");
                }
            } finally {
                DB::purge($connectionName);
            }
        }
    }

    private function startPostgresNumberWorker(string $postgresUrl, string $schema, string $worker): Process
    {
        $script = <<<'PHP'
            require getcwd().'/vendor/autoload.php';
            $app = require getcwd().'/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            $url = getenv('FINANCE_TEST_PGSQL_URL');
            $schema = getenv('FINANCE_TEST_PGSQL_SCHEMA');
            $worker = getenv('FINANCE_TEST_NUMBER_WORKER');
            if (! is_string($url) || ! is_string($schema) || ! is_string($worker)
                || preg_match('/\Afinance_invoice_task7_[0-9a-f]{16}\z/D', $schema) !== 1) {
                fwrite(STDERR, 'invalid-postgres-number-worker-configuration');
                exit(90);
            }

            $base = config('database.connections.pgsql');
            $base = is_array($base) ? $base : [];
            foreach (['pgsql_task7_worker', 'pgsql_task7_barrier'] as $connectionName) {
                config([
                    "database.connections.{$connectionName}" => array_merge(
                        $base,
                        ['driver' => 'pgsql', 'url' => $url, 'search_path' => $schema],
                    ),
                ]);
                \Illuminate\Support\Facades\DB::purge($connectionName);
                \Illuminate\Support\Facades\DB::connection($connectionName)
                    ->statement('SET search_path TO "'.$schema.'"');
            }
            \Illuminate\Support\Facades\DB::setDefaultConnection('pgsql_task7_worker');
            \Illuminate\Support\Facades\Schema::clearResolvedInstance('db.schema');
            \Illuminate\Support\Facades\DB::statement("SET lock_timeout TO '10s'");
            \Illuminate\Support\Facades\DB::statement("SET statement_timeout TO '20s'");

            $barrier = \Illuminate\Support\Facades\DB::connection('pgsql_task7_barrier');
            $barrier->table('finance_task7_number_barrier')->insert(['worker' => $worker]);
            $deadline = microtime(true) + 10.0;
            while ((int) $barrier->table('finance_task7_number_barrier')->count() < 2) {
                if (microtime(true) >= $deadline) {
                    fwrite(STDERR, 'postgres-number-barrier-timeout');
                    exit(91);
                }
                usleep(20_000);
            }

            try {
                $result = (new \App\Modules\Finance\Infrastructure\Persistence\LockedInvoiceNumberAllocator)
                    ->allocate(1, '2026-08-28');
                echo json_encode($result, JSON_THROW_ON_ERROR);
                exit(0);
            } catch (Throwable $exception) {
                fwrite(STDERR, $exception::class.':'.$exception->getMessage());
                exit(92);
            }
            PHP;

        $process = new Process(
            [PHP_BINARY, '-r', $script],
            base_path(),
            [
                'FINANCE_TEST_PGSQL_URL' => $postgresUrl,
                'FINANCE_TEST_PGSQL_SCHEMA' => $schema,
                'FINANCE_TEST_NUMBER_WORKER' => $worker,
            ],
            null,
            25,
        );
        $process->start();

        return $process;
    }
}

final class InvoiceFinalizationRenderer implements DocumentRenderer
{
    public int $calls = 0;

    /** @var array<array-key, mixed> */
    public array $snapshot = [];

    public ?\Throwable $failure = null;

    public function render(array $snapshot): string
    {
        $this->calls++;
        $this->snapshot = $snapshot;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return '%PDF-invoice-finalization';
    }
}

final class InvoiceFinalizationStorage implements DocumentStorage
{
    public int $puts = 0;

    /** @var array<string, string> */
    public array $documents = [];

    public ?\Throwable $failureAfterWrite = null;

    public function putPdf(
        string $seriesUuid,
        string $bytes,
        DocumentStorageWrite $write,
    ): StoredDocument {
        $this->puts++;
        $path = "finance/revisions/{$seriesUuid}/{$write->ownershipToken}.pdf";
        $this->documents[$path] = $bytes;
        if ($this->failureAfterWrite !== null) {
            throw $this->failureAfterWrite;
        }

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

final class InvoiceFinalizationNumberAllocator implements InvoiceNumberAllocator
{
    public int $calls = 0;

    public ?\Throwable $failure = null;

    public function allocate(int $ownerId, string $issueDate): array
    {
        $this->calls++;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return ['number' => 'TEST-2026-0001', 'year' => 2026, 'sequence' => 1];
    }
}

final class FailingInvoiceInventory implements InventoryMovementPort
{
    public function recordInvoiceSale(
        int $ownerId,
        string $invoiceUuid,
        array $quantityScaledByProduct,
        DateTimeImmutable $occurredAt,
    ): void {
        throw new RuntimeException('inventory failed');
    }
}
