<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\FileEntry;
use App\Models\User;
use App\Modules\Finance\Application\Commands\Projects\AttachProjectDocument;
use App\Modules\Finance\Application\Commands\Projects\DetachProjectDocument;
use App\Modules\Finance\Application\DTOs\Projects\OperationReservation;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentPage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentView;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentCatalog;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use App\Modules\Finance\Application\Queries\Projects\ListProjectDocuments;
use App\Modules\Finance\Infrastructure\Catalog\CompositeProjectDocumentCatalog;
use App\Modules\Finance\Infrastructure\Compatibility\FinanceSeriesDocumentSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyBankReceiptDocumentSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyBankTransactionDocumentSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyFileDocumentSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyFinanceReceiptDocumentSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyGalleryPhotoDocumentSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyInvoiceDocumentSource;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectDocumentRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ProjectDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_source_is_owner_scoped_and_exposes_only_allowlisted_metadata(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $file = $this->file($owner, 'Contract.pdf');
        $source = new LegacyFileDocumentSource;

        $metadata = $source->resolve((int) $owner->id, new ProjectDocumentSourceRef('file', "file:{$file->id}"));

        $this->assertSame('Contract.pdf', $metadata->title);
        $this->assertSame('files.rel.raw', $metadata->capabilityRoute);
        $this->assertSame(
            ['source_type', 'source_reference', 'title', 'mime', 'size', 'sha256', 'document_type', 'document_label', 'occurred_at'],
            array_keys($metadata->snapshot()),
        );
        $this->assertStringNotContainsString('storage_path', json_encode($metadata->snapshot(), JSON_THROW_ON_ERROR));

        $this->expectException(ModelNotFoundException::class);
        $source->resolve((int) $foreign->id, new ProjectDocumentSourceRef('file', "file:{$file->id}"));
    }

    public function test_source_reference_and_search_filters_reject_unbounded_or_unknown_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProjectDocumentSourceFilter(1, sourceTypes: ['raw_path'], perPage: 101);
    }

    public function test_uuid_source_references_are_canonicalized_to_lowercase(): void
    {
        $uuid = strtoupper((string) Str::uuid());
        $receiptUuid = strtoupper((string) Str::uuid());

        $series = new ProjectDocumentSourceRef('finance_series', $uuid, 7);
        $receipt = new ProjectDocumentSourceRef('bank_transaction_receipt', 'bank-transaction-receipt:9:'.$receiptUuid);

        $this->assertSame(strtolower($uuid), $series->sourceReference);
        $this->assertSame('bank-transaction-receipt:9:'.strtolower($receiptUuid), $receipt->sourceReference);
    }

    public function test_service_provider_binds_repository_and_one_shared_complete_catalog(): void
    {
        $repository = app(ProjectDocumentRepository::class);
        $catalog = app(ProjectDocumentCatalog::class);

        $this->assertInstanceOf(EloquentProjectDocumentRepository::class, $repository);
        $this->assertInstanceOf(CompositeProjectDocumentCatalog::class, $catalog);
        $this->assertSame($catalog, app(ProjectDocumentSource::class));
        foreach (ProjectDocumentSourceFilter::TYPES as $type) {
            $this->assertTrue($catalog->supports($type));
        }
    }

    public function test_composite_catalog_rejects_ambiguous_adapters_and_merges_deterministically(): void
    {
        $adapter = new class implements ProjectDocumentSource
        {
            public function supports(string $sourceType): bool
            {
                return $sourceType === 'file';
            }

            public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
            {
                return new ProjectDocumentMetadata($ref, 'A', null, null, null, 'file', null, new DateTimeImmutable('2026-01-01'));
            }

            public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
            {
                return new ProjectDocumentSourcePage([$this->resolve($ownerId, new ProjectDocumentSourceRef('file', 'file:1'))], null);
            }
        };
        $catalog = new CompositeProjectDocumentCatalog([$adapter]);
        $this->assertSame(['file:1'], array_map(static fn (ProjectDocumentMetadata $item): string => $item->source->sourceReference, $catalog->search(1, new ProjectDocumentSourceFilter(1, sourceTypes: ['file']))->items));

        $this->expectException(\LogicException::class);
        (new CompositeProjectDocumentCatalog([$adapter, $adapter]))->resolve(1, new ProjectDocumentSourceRef('file', 'file:1'));
    }

    public function test_composite_catalog_cursor_merges_every_source_beyond_one_hundred_without_duplicates(): void
    {
        $catalog = new CompositeProjectDocumentCatalog([
            $this->pagedSource('file', 125),
            $this->pagedSource('legacy_invoice', 125),
        ]);
        $cursor = null;
        $seen = [];

        do {
            $page = $catalog->search(1, new ProjectDocumentSourceFilter(1, sourceTypes: ['file', 'legacy_invoice'], cursor: $cursor, perPage: 17));
            foreach ($page->items as $item) {
                $seen[] = $item->source->sourceType.':'.$item->source->sourceReference;
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $expected = [];
        for ($index = 0; $index < 125; $index++) {
            $id = $index + 1;
            $expected[] = 'file:file:'.$id;
            $expected[] = 'legacy_invoice:legacy-invoice:'.$id;
        }
        $this->assertSame($expected, $seen);
        $this->assertCount(250, array_unique($seen));
    }

    public function test_composite_catalog_orders_by_full_microseconds_then_type_across_cursor_pages(): void
    {
        $catalog = new CompositeProjectDocumentCatalog([
            $this->singleItemSource('file', 'file:2', '2026-08-29 09:00:00.000001'),
            $this->singleItemSource('legacy_invoice', 'legacy-invoice:10', '2026-08-29 09:00:00.000002'),
            $this->singleItemSource('gallery_photo', 'gallery-photo:3', '2026-08-29 09:00:00.000001'),
        ]);
        $cursor = null;
        $references = [];

        do {
            $page = $catalog->search(1, new ProjectDocumentSourceFilter(
                1,
                sourceTypes: ['file', 'legacy_invoice', 'gallery_photo'],
                cursor: $cursor,
                perPage: 1,
            ));
            foreach ($page->items as $item) {
                $references[] = $item->source->sourceReference;
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $this->assertSame(['legacy-invoice:10', 'file:2', 'gallery-photo:3'], $references);
    }

    public function test_composite_keeps_known_heads_unemitted_across_multiple_empty_embedded_frontiers(): void
    {
        $receiptReference = 'bank-transaction-receipt:1:00000000-0000-4000-8000-000000000001';
        $catalog = new CompositeProjectDocumentCatalog([
            $this->scriptedSource('bank_transaction_receipt', [
                [],
                [],
                [[$receiptReference, '2026-08-29 09:00:00.000000']],
            ]),
            $this->scriptedSource('file', [
                [['file:1', '2026-08-29 08:00:00.000000']],
            ]),
        ]);
        $filter = static fn (?string $cursor): ProjectDocumentSourceFilter => new ProjectDocumentSourceFilter(
            1,
            sourceTypes: ['bank_transaction_receipt', 'file'],
            cursor: $cursor,
            perPage: 2,
        );

        $first = $catalog->search(1, $filter(null));
        $second = $catalog->search(1, $filter($first->nextCursor));
        $third = $catalog->search(1, $filter($second->nextCursor));

        $this->assertSame([], $first->items);
        $this->assertNotNull($first->nextCursor);
        $this->assertSame([], $second->items);
        $this->assertNotNull($second->nextCursor);
        $this->assertSame(
            [$receiptReference, 'file:1'],
            array_map(static fn (ProjectDocumentMetadata $item): string => $item->source->sourceReference, $third->items),
        );
        $this->assertNull($third->nextCursor);
    }

    public function test_composite_returns_a_partial_page_before_crossing_an_unresolved_frontier(): void
    {
        $newestReceipt = 'bank-transaction-receipt:1:00000000-0000-4000-8000-000000000010';
        $hiddenReceipt = 'bank-transaction-receipt:1:00000000-0000-4000-8000-000000000009';
        $catalog = new CompositeProjectDocumentCatalog([
            $this->scriptedSource('bank_transaction_receipt', [
                [[$newestReceipt, '2026-08-29 10:00:00.000000']],
                [],
                [[$hiddenReceipt, '2026-08-29 09:00:00.000000']],
            ]),
            $this->scriptedSource('file', [
                [['file:8', '2026-08-29 08:00:00.000000']],
            ]),
        ]);

        $first = $catalog->search(1, new ProjectDocumentSourceFilter(1, sourceTypes: ['bank_transaction_receipt', 'file'], perPage: 3));
        $second = $catalog->search(1, new ProjectDocumentSourceFilter(1, sourceTypes: ['bank_transaction_receipt', 'file'], cursor: $first->nextCursor, perPage: 3));

        $this->assertSame([$newestReceipt], array_map(static fn (ProjectDocumentMetadata $item): string => $item->source->sourceReference, $first->items));
        $this->assertNotNull($first->nextCursor);
        $this->assertSame(
            [$hiddenReceipt, 'file:8'],
            array_map(static fn (ProjectDocumentMetadata $item): string => $item->source->sourceReference, $second->items),
        );
        $this->assertNull($second->nextCursor);
    }

    public function test_composite_returns_a_full_page_without_probing_the_next_frontier(): void
    {
        $newestReceipt = 'bank-transaction-receipt:1:00000000-0000-4000-8000-000000000010';
        $hiddenReceipt = 'bank-transaction-receipt:1:00000000-0000-4000-8000-000000000009';
        $catalog = new CompositeProjectDocumentCatalog([
            $this->scriptedSource('bank_transaction_receipt', [
                [[$newestReceipt, '2026-08-29 10:00:00.000000']],
                [],
                [[$hiddenReceipt, '2026-08-29 09:00:00.000000']],
            ]),
            $this->scriptedSource('file', [
                [['file:8', '2026-08-29 08:00:00.000000']],
            ]),
        ]);
        $cursor = null;
        $pages = [];

        do {
            $page = $catalog->search(1, new ProjectDocumentSourceFilter(
                1,
                sourceTypes: ['bank_transaction_receipt', 'file'],
                cursor: $cursor,
                perPage: 1,
            ));
            $pages[] = array_map(static fn (ProjectDocumentMetadata $item): string => $item->source->sourceReference, $page->items);
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $this->assertSame([[$newestReceipt], [], [$hiddenReceipt], ['file:8']], $pages);
    }

    public function test_full_catalog_has_exactly_one_owner_scoped_adapter_for_every_supported_type(): void
    {
        $owner = User::factory()->create();
        $catalog = new CompositeProjectDocumentCatalog([
            new FinanceSeriesDocumentSource,
            new LegacyInvoiceDocumentSource,
            new LegacyFileDocumentSource,
            new LegacyGalleryPhotoDocumentSource,
            new LegacyFinanceReceiptDocumentSource,
            new LegacyBankTransactionDocumentSource,
            new LegacyBankReceiptDocumentSource,
        ]);

        foreach (ProjectDocumentSourceFilter::TYPES as $type) {
            $this->assertTrue($catalog->supports($type));
        }
        $this->assertSame([], $catalog->search((int) $owner->id, new ProjectDocumentSourceFilter((int) $owner->id))->items);
    }

    public function test_finance_series_pins_owner_and_revision_and_only_exposes_an_existing_invoice_route(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        [$uuid, $revisionId] = $this->financeSeries($owner, 'invoice', 'INV-2026-1');
        [, $otherRevisionId] = $this->financeSeries($owner, 'invoice', 'INV-2026-2');
        $ref = new ProjectDocumentSourceRef('finance_series', strtoupper($uuid), $revisionId);

        $available = (new FinanceSeriesDocumentSource)->resolve((int) $owner->id, $ref);
        $withoutRoute = (new FinanceSeriesDocumentSource(static fn (string $route): bool => false))->resolve((int) $owner->id, $ref);

        $this->assertSame('api.finance-v2.invoices.revisions.pdf', $available->capabilityRoute);
        $this->assertSame(['invoice' => strtolower($uuid), 'revision' => 1], $available->capabilityParameters);
        $this->assertNull($withoutRoute->capabilityRoute);
        $this->assertSame([], $withoutRoute->capabilityParameters);

        foreach ([
            [(int) $foreign->id, $ref],
            [(int) $owner->id, new ProjectDocumentSourceRef('finance_series', $uuid, $otherRevisionId)],
        ] as [$ownerId, $invalidRef]) {
            try {
                (new FinanceSeriesDocumentSource)->resolve($ownerId, $invalidRef);
                $this->fail('Foreign or cross-series revision was resolved.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_finance_series_requires_a_real_pdf_object_before_exposing_pdf_metadata_or_capability(): void
    {
        $owner = User::factory()->create();
        [$uuid, $revisionId] = $this->financeSeries($owner, 'invoice', 'INV-NO-PDF');
        DB::table('finance_document_revisions')->where('id', $revisionId)->update(['pdf_path' => null]);

        $metadata = (new FinanceSeriesDocumentSource)->resolve(
            (int) $owner->id,
            new ProjectDocumentSourceRef('finance_series', $uuid, $revisionId),
        );

        $this->assertNull($metadata->mime);
        $this->assertNull($metadata->capabilityRoute);
        $this->assertSame([], $metadata->capabilityParameters);
    }

    public function test_embedded_bank_receipt_parses_canonical_reference_without_leaking_blob_and_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $paymentMethodId = (int) DB::table('payment_methods')->insertGetId([
            'user_id' => $owner->id, 'type' => 'bank', 'name' => 'Account', 'business' => true,
            'version' => 0, 'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
        ]);
        $receiptUuid = (string) Str::uuid();
        $transactionId = (int) DB::table('bank_transactions')->insertGetId([
            'user_id' => $owner->id, 'payment_method_id' => $paymentMethodId, 'date' => '2026-08-28',
            'amount' => '12.34', 'counterparty' => 'Supplier',
            'receipts' => json_encode([['id' => $receiptUuid, 'name' => 'Embedded.pdf', 'mime' => 'application/pdf', 'size' => 321, 'blob_path' => 'secret/blob.pdf', 'ocr' => 'secret text']], JSON_THROW_ON_ERROR),
            'version' => 0, 'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
        ]);
        $source = new LegacyBankReceiptDocumentSource;
        $ref = new ProjectDocumentSourceRef('bank_transaction_receipt', "bank-transaction-receipt:{$transactionId}:{$receiptUuid}");

        $metadata = $source->resolve((int) $owner->id, $ref);

        $this->assertSame('Embedded.pdf', $metadata->title);
        $this->assertSame(['transaction' => $transactionId, 'receipt' => $receiptUuid], $metadata->capabilityParameters);
        $serialized = json_encode($metadata->snapshot(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('blob_path', $serialized);
        $this->assertStringNotContainsString('ocr', $serialized);

        $this->expectException(ModelNotFoundException::class);
        $source->resolve((int) $foreign->id, $ref);
    }

    public function test_embedded_bank_receipt_cursor_reaches_transactions_beyond_one_hundred(): void
    {
        $owner = User::factory()->create();
        $paymentMethodId = (int) DB::table('payment_methods')->insertGetId([
            'user_id' => $owner->id, 'type' => 'bank', 'name' => 'Paged account', 'business' => true,
            'version' => 0, 'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
        ]);
        $rows = [];
        for ($index = 0; $index < 101; $index++) {
            $receiptUuid = (string) Str::uuid();
            $rows[] = [
                'user_id' => $owner->id, 'payment_method_id' => $paymentMethodId, 'date' => '2026-08-28',
                'amount' => '1.00', 'counterparty' => 'Paged supplier',
                'receipts' => json_encode([['id' => $receiptUuid, 'name' => 'Receipt '.$index]], JSON_THROW_ON_ERROR),
                'version' => 0, 'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
            ];
        }
        DB::table('bank_transactions')->insert($rows);
        $source = new LegacyBankReceiptDocumentSource;
        $cursor = null;
        $references = [];

        do {
            $page = $source->search((int) $owner->id, new ProjectDocumentSourceFilter((int) $owner->id, sourceTypes: ['bank_transaction_receipt'], cursor: $cursor, perPage: 30));
            foreach ($page->items as $item) {
                $references[] = $item->source->sourceReference;
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $this->assertCount(101, $references);
        $this->assertCount(101, array_unique($references));
    }

    public function test_embedded_receipt_search_scans_only_one_fixed_transaction_batch_per_call(): void
    {
        $owner = User::factory()->create();
        $paymentMethodId = (int) DB::table('payment_methods')->insertGetId([
            'user_id' => $owner->id, 'type' => 'bank', 'name' => 'Bounded account', 'business' => true,
            'version' => 0, 'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
        ]);
        $rows = [];
        for ($index = 0; $index < 120; $index++) {
            $rows[] = [
                'user_id' => $owner->id, 'payment_method_id' => $paymentMethodId, 'date' => '2026-08-28',
                'amount' => '1.00', 'counterparty' => 'Bounded supplier',
                'receipts' => json_encode([['id' => (string) Str::uuid(), 'name' => 'Receipt '.$index]], JSON_THROW_ON_ERROR),
                'version' => 0, 'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
            ];
        }
        DB::table('bank_transactions')->insert($rows);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $page = (new CompositeProjectDocumentCatalog([new LegacyBankReceiptDocumentSource]))->search(
            (int) $owner->id,
            new ProjectDocumentSourceFilter((int) $owner->id, q: 'does-not-match', sourceTypes: ['bank_transaction_receipt'], perPage: 100),
        );
        $transactionReads = array_values(array_filter(
            DB::getQueryLog(),
            static fn (array $query): bool => str_contains(strtolower((string) $query['query']), 'bank_transactions'),
        ));
        DB::disableQueryLog();

        $this->assertSame([], $page->items);
        $this->assertNotNull($page->nextCursor);
        $this->assertCount(1, $transactionReads);
    }

    public function test_legacy_invoice_gallery_finance_receipt_and_bank_transaction_are_owner_scoped_and_deleted_aware(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $now = '2026-08-29 08:00:00';
        $invoiceId = (int) DB::table('invoices')->insertGetId([
            'user_id' => $owner->id, 'number' => 'INV-42', 'status' => 'final', 'currency' => 'EUR',
            'issue_date' => '2026-08-28', 'pdf_path' => 'secret/invoice.pdf', 'imported' => false,
            'version_seq' => 0, 'version' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $photoId = (int) DB::table('gallery_photos')->insertGetId([
            'user_id' => $owner->id, 'name' => 'Photo.jpg', 'mime' => 'image/jpeg',
            'size' => 50, 'storage_path' => 'secret/photo.jpg', 'favorite' => false, 'version' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $receiptId = (int) DB::table('finance_receipts')->insertGetId([
            'user_id' => $owner->id, 'blob_path' => 'secret/receipt.pdf', 'name' => 'Receipt.pdf',
            'mime' => 'application/pdf', 'size' => 60, 'kind' => 'receipt', 'ocr' => 'do not leak',
            'version' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $paymentMethodId = (int) DB::table('payment_methods')->insertGetId([
            'user_id' => $owner->id, 'type' => 'bank', 'name' => 'Owner account', 'business' => true,
            'version' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $transactionId = (int) DB::table('bank_transactions')->insertGetId([
            'user_id' => $owner->id, 'payment_method_id' => $paymentMethodId, 'date' => '2026-08-28',
            'amount' => '7.00', 'counterparty' => 'Vendor', 'purpose' => 'Private details',
            'version' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $cases = [
            [new LegacyInvoiceDocumentSource, new ProjectDocumentSourceRef('legacy_invoice', 'legacy-invoice:'.$invoiceId), 'finance.invoices.pdf'],
            [new LegacyGalleryPhotoDocumentSource, new ProjectDocumentSourceRef('gallery_photo', 'gallery-photo:'.$photoId), 'gallery.raw'],
            [new LegacyFinanceReceiptDocumentSource, new ProjectDocumentSourceRef('finance_receipt', 'finance-receipt:'.$receiptId), 'finance.receipts.raw'],
            [new LegacyBankTransactionDocumentSource, new ProjectDocumentSourceRef('bank_transaction', 'bank-transaction:'.$transactionId), 'finance.index'],
        ];

        foreach ($cases as [$source, $ref, $route]) {
            $metadata = $source->resolve((int) $owner->id, $ref);
            $this->assertSame('available', $metadata->availability);
            $this->assertSame($route, $metadata->capabilityRoute);
            $serialized = json_encode($metadata->snapshot(), JSON_THROW_ON_ERROR);
            foreach (['storage_path', 'pdf_path', 'blob_path', 'ocr', 'purpose'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $serialized);
            }
            try {
                $source->resolve((int) $foreign->id, $ref);
                $this->fail('Foreign source was resolved.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        DB::table('invoices')->where('id', $invoiceId)->update(['deleted_at' => $now]);
        DB::table('gallery_photos')->where('id', $photoId)->update(['deleted_at' => $now]);
        DB::table('finance_receipts')->where('id', $receiptId)->update(['deleted_at' => $now]);
        DB::table('bank_transactions')->where('id', $transactionId)->update(['deleted_at' => $now]);
        foreach ($cases as [$source, $ref]) {
            $metadata = $source->resolve((int) $owner->id, $ref);
            $this->assertSame('deleted', $metadata->availability);
            $this->assertNull($metadata->capabilityRoute);
        }
    }

    public function test_all_seven_adapters_use_the_same_natural_reference_tie_breaker(): void
    {
        $owner = User::factory()->create();
        $at = '2026-08-29 08:00:00.123456';
        [$seriesA] = $this->financeSeries($owner, 'quote', 'Q-A');
        [$seriesB] = $this->financeSeries($owner, 'quote', 'Q-B');
        $fileA = $this->file($owner, 'A.pdf');
        $fileB = $this->file($owner, 'B.pdf');
        DB::table('files')->whereIn('id', [$fileA->id, $fileB->id])->update(['created_at' => $at]);
        $invoiceIds = [];
        $photoIds = [];
        $receiptIds = [];
        foreach (['A', 'B'] as $suffix) {
            $invoiceIds[] = (int) DB::table('invoices')->insertGetId([
                'user_id' => $owner->id, 'number' => 'INV-'.$suffix, 'status' => 'draft', 'currency' => 'EUR',
                'issue_date' => null, 'pdf_path' => null, 'imported' => false, 'version_seq' => 0, 'version' => 0,
                'created_at' => $at, 'updated_at' => $at,
            ]);
            $photoIds[] = (int) DB::table('gallery_photos')->insertGetId([
                'user_id' => $owner->id, 'name' => 'Photo '.$suffix, 'mime' => 'image/jpeg', 'size' => 1,
                'storage_path' => 'private/photo-'.$suffix, 'favorite' => false, 'version' => 0,
                'created_at' => $at, 'updated_at' => $at,
            ]);
            $receiptIds[] = (int) DB::table('finance_receipts')->insertGetId([
                'user_id' => $owner->id, 'blob_path' => 'private/receipt-'.$suffix, 'name' => 'Receipt '.$suffix,
                'mime' => 'application/pdf', 'size' => 1, 'kind' => 'receipt', 'version' => 0,
                'created_at' => $at, 'updated_at' => $at,
            ]);
        }
        $paymentMethodId = (int) DB::table('payment_methods')->insertGetId([
            'user_id' => $owner->id, 'type' => 'bank', 'name' => 'Tie account', 'business' => true,
            'version' => 0, 'created_at' => $at, 'updated_at' => $at,
        ]);
        $transactionIds = [];
        $receiptUuids = [];
        foreach (['A', 'B'] as $suffix) {
            $receiptUuid = strtolower((string) Str::uuid());
            $receiptUuids[] = $receiptUuid;
            $transactionIds[] = (int) DB::table('bank_transactions')->insertGetId([
                'user_id' => $owner->id, 'payment_method_id' => $paymentMethodId, 'date' => '2026-08-29',
                'amount' => '1.00', 'counterparty' => 'Tie '.$suffix,
                'receipts' => json_encode([['id' => $receiptUuid, 'name' => 'Embedded '.$suffix]], JSON_THROW_ON_ERROR),
                'version' => 0, 'created_at' => $at, 'updated_at' => $at,
            ]);
        }
        $seriesRefs = [$seriesA, $seriesB];
        sort($seriesRefs);
        $cases = [
            [new FinanceSeriesDocumentSource, 'finance_series', $seriesRefs],
            [new LegacyInvoiceDocumentSource, 'legacy_invoice', array_map(static fn (int $id): string => 'legacy-invoice:'.$id, $invoiceIds)],
            [new LegacyFileDocumentSource, 'file', ['file:'.$fileA->id, 'file:'.$fileB->id]],
            [new LegacyGalleryPhotoDocumentSource, 'gallery_photo', array_map(static fn (int $id): string => 'gallery-photo:'.$id, $photoIds)],
            [new LegacyFinanceReceiptDocumentSource, 'finance_receipt', array_map(static fn (int $id): string => 'finance-receipt:'.$id, $receiptIds)],
            [new LegacyBankTransactionDocumentSource, 'bank_transaction', array_map(static fn (int $id): string => 'bank-transaction:'.$id, $transactionIds)],
            [new LegacyBankReceiptDocumentSource, 'bank_transaction_receipt', [
                'bank-transaction-receipt:'.$transactionIds[0].':'.$receiptUuids[0],
                'bank-transaction-receipt:'.$transactionIds[1].':'.$receiptUuids[1],
            ]],
        ];

        foreach ($cases as [$source, $type, $expected]) {
            $this->assertSame(
                $expected,
                $this->sourceReferences($source, (int) $owner->id, $type),
                'Unexpected canonical order for '.$type,
            );
        }
        $firstFile = (new LegacyFileDocumentSource)->resolve((int) $owner->id, new ProjectDocumentSourceRef('file', 'file:'.$fileA->id));
        $this->assertSame('123456', $firstFile->occurredAt?->format('u'));
    }

    public function test_invoice_search_uses_pdf_path_and_issue_date_fallback_and_null_mime_is_other(): void
    {
        $owner = User::factory()->create();
        $withoutPdf = (int) DB::table('invoices')->insertGetId([
            'user_id' => $owner->id, 'number' => 'INV-OTHER', 'status' => 'draft', 'currency' => 'EUR',
            'issue_date' => null, 'pdf_path' => null, 'imported' => false, 'version_seq' => 0, 'version' => 0,
            'created_at' => '2026-08-29 08:00:00.123456', 'updated_at' => '2026-08-29 08:00:00.123456',
        ]);
        $withPdf = (int) DB::table('invoices')->insertGetId([
            'user_id' => $owner->id, 'number' => 'INV-PDF', 'status' => 'final', 'currency' => 'EUR',
            'issue_date' => null, 'pdf_path' => 'private/invoice.pdf', 'imported' => false, 'version_seq' => 0, 'version' => 0,
            'created_at' => '2026-08-29 08:00:00.654321', 'updated_at' => '2026-08-29 08:00:00.654321',
        ]);
        DB::table('files')->insert([
            'user_id' => $owner->id, 'name' => 'Unknown file', 'mime' => null, 'size' => 1,
            'storage_path' => 'private/unknown', 'favorite' => false, 'version' => 0,
            'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
        ]);
        DB::table('finance_receipts')->insert([
            'user_id' => $owner->id, 'blob_path' => 'private/unknown-receipt', 'name' => 'Unknown receipt',
            'mime' => null, 'size' => 1, 'kind' => 'receipt', 'version' => 0,
            'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
        ]);
        $invoices = new LegacyInvoiceDocumentSource;

        $other = $invoices->search((int) $owner->id, new ProjectDocumentSourceFilter(
            (int) $owner->id,
            sourceTypes: ['legacy_invoice'],
            mimeGroups: ['other'],
            from: new DateTimeImmutable('2026-08-29 08:00:00.100000'),
            to: new DateTimeImmutable('2026-08-29 08:00:00.200000'),
        ));
        $pdf = $invoices->search((int) $owner->id, new ProjectDocumentSourceFilter(
            (int) $owner->id,
            sourceTypes: ['legacy_invoice'],
            mimeGroups: ['pdf'],
        ));

        $this->assertSame(['legacy-invoice:'.$withoutPdf], array_map(static fn (ProjectDocumentMetadata $item): string => $item->source->sourceReference, $other->items));
        $this->assertNull($other->items[0]->mime);
        $this->assertSame(['legacy-invoice:'.$withPdf], array_map(static fn (ProjectDocumentMetadata $item): string => $item->source->sourceReference, $pdf->items));
        $this->assertCount(1, (new LegacyFileDocumentSource)->search((int) $owner->id, new ProjectDocumentSourceFilter((int) $owner->id, sourceTypes: ['file'], mimeGroups: ['other']))->items);
        $this->assertCount(1, (new LegacyFinanceReceiptDocumentSource)->search((int) $owner->id, new ProjectDocumentSourceFilter((int) $owner->id, sourceTypes: ['finance_receipt'], mimeGroups: ['other']))->items);
    }

    public function test_attach_replay_is_stable_and_a_different_key_cannot_duplicate_an_active_link(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Evidence.pdf');
        $command = new AttachProjectDocument(
            new LegacyFileDocumentSource,
            new EloquentProjectDocumentRepository,
            app(ProjectOperationRepository::class),
        );
        $at = new DateTimeImmutable('2026-08-29 09:00:00');
        $ref = new ProjectDocumentSourceRef('file', "file:{$file->id}");

        $first = $command->handle($project, $ref, 'file', (int) $owner->id, $at, 'attach-1');
        $replay = $command->handle($project, $ref, 'file', (int) $owner->id, $at, 'attach-1');

        $this->assertSame($first->linkId, $replay->linkId);
        $this->assertSame(1, DB::table('finance_project_document_links')->count());
        $this->assertSame(1, DB::table('finance_project_activities')->where('type', 'project.document_attached')->count());

        try {
            $command->handle($project, $ref, 'file', (int) $owner->id, $at, 'attach-2');
            $this->fail('Duplicate active attachment was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('document_already_attached', $exception->getMessage());
        }
    }

    public function test_attach_idempotency_digest_and_result_preserve_microseconds(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Microseconds.pdf');
        $command = new AttachProjectDocument(new LegacyFileDocumentSource, new EloquentProjectDocumentRepository, app(ProjectOperationRepository::class));
        $ref = new ProjectDocumentSourceRef('file', 'file:'.$file->id);

        $first = $command->handle($project, $ref, 'file', (int) $owner->id, new DateTimeImmutable('2026-08-29 09:00:00.123456'), 'attach-microseconds');
        $this->assertSame('123456', $first->attachedAt->format('u'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('idempotency_key_reused');
        $command->handle($project, $ref, 'file', (int) $owner->id, new DateTimeImmutable('2026-08-29 09:00:00.654321'), 'attach-microseconds');
    }

    public function test_attachment_roles_reject_every_incompatible_source_kind_before_writing(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Role.pdf');
        $metadata = (new LegacyFileDocumentSource)->resolve((int) $owner->id, new ProjectDocumentSourceRef('file', "file:{$file->id}"));
        $repository = new EloquentProjectDocumentRepository;
        $at = new DateTimeImmutable('2026-08-29 09:00:00');

        foreach (['source_quote', 'quote', 'invoice', 'payment', 'receipt', 'photo'] as $role) {
            try {
                $repository->attach($project, $metadata, $role, (int) $owner->id, $at);
                $this->fail("Incompatible role {$role} was accepted.");
            } catch (DomainException $exception) {
                $this->assertSame('document_role_incompatible', $exception->getMessage());
            }
        }
        $attached = $repository->attach($project, $metadata, 'other', (int) $owner->id, $at);

        $this->assertSame('other', $attached->role);
        $this->assertSame(1, DB::table('finance_project_document_links')->count());
    }

    public function test_attach_replay_refreshes_current_metadata_and_deleted_availability_without_changing_snapshot(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Original.pdf');
        $source = new LegacyFileDocumentSource;
        $command = new AttachProjectDocument($source, new EloquentProjectDocumentRepository, app(ProjectOperationRepository::class));
        $ref = new ProjectDocumentSourceRef('file', "file:{$file->id}");
        $at = new DateTimeImmutable('2026-08-29 09:00:00');

        $command->handle($project, $ref, 'file', (int) $owner->id, $at, 'refresh-replay');
        $file->update(['name' => 'Renamed.pdf']);
        $renamed = $command->handle($project, $ref, 'file', (int) $owner->id, $at, 'refresh-replay');
        $file->delete();
        $deleted = $command->handle($project, $ref, 'file', (int) $owner->id, $at, 'refresh-replay');

        $this->assertSame('Original.pdf', $renamed->snapshot['title']);
        $this->assertSame('Renamed.pdf', $renamed->current?->title);
        $this->assertSame('available', $renamed->availability);
        $this->assertNull($deleted->current);
        $this->assertSame('deleted', $deleted->availability);
    }

    public function test_attach_recovers_the_same_link_after_crash_before_operation_completion(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Crash.pdf');
        $source = new LegacyFileDocumentSource;
        $operations = $this->crashingOperations(app(ProjectOperationRepository::class));
        $command = new AttachProjectDocument($source, new EloquentProjectDocumentRepository, $operations);
        $ref = new ProjectDocumentSourceRef('file', "file:{$file->id}");
        $at = new DateTimeImmutable('2026-08-29 09:00:00');

        try {
            $command->handle($project, $ref, 'file', (int) $owner->id, $at, 'attach-crash');
            $this->fail('Simulated completion crash was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated_completion_crash', $exception->getMessage());
        }

        $recovered = $command->handle($project, $ref, 'file', (int) $owner->id, $at, 'attach-crash');

        $this->assertSame(1, $recovered->linkId);
        $this->assertSame(1, DB::table('finance_project_document_links')->count());
        $this->assertSame(1, DB::table('finance_project_activities')->where('type', 'project.document_attached')->count());
        $this->assertSame('succeeded', DB::table('finance_project_operations')->where('idempotency_key', 'attach-crash')->value('state'));
        $operationId = (int) DB::table('finance_project_operations')->where('idempotency_key', 'attach-crash')->value('id');
        $this->assertSame($operationId, (int) DB::table('finance_project_document_links')->value('attached_operation_id'));
    }

    public function test_attach_retry_never_adopts_a_link_created_by_another_operation(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Owned-checkpoint.pdf');
        $source = new LegacyFileDocumentSource;
        $repository = new EloquentProjectDocumentRepository;
        $operations = app(ProjectOperationRepository::class);
        $at = new DateTimeImmutable('2026-08-29 09:00:00.123456');
        $ref = new ProjectDocumentSourceRef('file', "file:{$file->id}");
        $crashing = new AttachProjectDocument($source, $repository, $this->crashAfterReservation($operations));

        try {
            $crashing->handle($project, $ref, 'file', (int) $owner->id, $at, 'attach-operation-a');
            $this->fail('Simulated pre-mutation crash was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated_pre_mutation_crash', $exception->getMessage());
        }

        $winner = (new AttachProjectDocument($source, $repository, $operations))->handle(
            $project,
            $ref,
            'file',
            (int) $owner->id,
            $at,
            'attach-operation-b',
        );

        try {
            (new AttachProjectDocument($source, $repository, $operations))->handle(
                $project,
                $ref,
                'file',
                (int) $owner->id,
                $at,
                'attach-operation-a',
            );
            $this->fail('The abandoned operation adopted another operation\'s link.');
        } catch (DomainException $exception) {
            $this->assertSame('operation_in_progress', $exception->getMessage());
        }

        $winnerOperationId = (int) DB::table('finance_project_operations')->where('idempotency_key', 'attach-operation-b')->value('id');
        $this->assertSame($winner->linkId, (int) DB::table('finance_project_document_links')->value('id'));
        $this->assertSame($winnerOperationId, (int) DB::table('finance_project_document_links')->value('attached_operation_id'));
        $this->assertNull(DB::table('finance_project_operations')->where('idempotency_key', 'attach-operation-a')->value('result'));
    }

    public function test_failed_attach_retry_keeps_duplicate_semantics_when_another_operation_wins(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Failed-attach-checkpoint.pdf');
        $realSource = new LegacyFileDocumentSource;
        $operations = app(ProjectOperationRepository::class);
        $repository = new EloquentProjectDocumentRepository;
        $failingSource = $this->failFirstResolveSource($realSource);
        $ref = new ProjectDocumentSourceRef('file', 'file:'.$file->id);
        $at = new DateTimeImmutable('2026-08-29 09:00:00.123456');

        try {
            (new AttachProjectDocument($failingSource, $repository, $operations))->handle(
                $project, $ref, 'file', (int) $owner->id, $at, 'failed-attach-operation-a',
            );
            $this->fail('The injected pre-mutation failure was not raised.');
        } catch (DomainException $exception) {
            $this->assertSame('source_temporarily_unavailable', $exception->getMessage());
        }
        (new AttachProjectDocument($realSource, $repository, $operations))->handle(
            $project, $ref, 'file', (int) $owner->id, $at, 'failed-attach-operation-b',
        );

        try {
            (new AttachProjectDocument($failingSource, $repository, $operations))->handle(
                $project, $ref, 'file', (int) $owner->id, $at, 'failed-attach-operation-a',
            );
            $this->fail('The failed operation adopted another operation\'s link.');
        } catch (DomainException $exception) {
            $this->assertSame('document_already_attached', $exception->getMessage());
        }
        $this->assertSame('failed', DB::table('finance_project_operations')->where('idempotency_key', 'failed-attach-operation-a')->value('state'));
        $this->assertSame(1, DB::table('finance_project_document_links')->count());
    }

    public function test_detach_recovers_the_same_history_row_after_crash_before_operation_completion(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Detach-crash.pdf');
        $source = new LegacyFileDocumentSource;
        $repository = new EloquentProjectDocumentRepository;
        $realOperations = app(ProjectOperationRepository::class);
        $linked = (new AttachProjectDocument($source, $repository, $realOperations))->handle(
            $project, new ProjectDocumentSourceRef('file', "file:{$file->id}"), 'file', (int) $owner->id,
            new DateTimeImmutable('2026-08-29 09:00:00'), 'detach-crash-setup',
        );
        $operations = $this->crashingOperations($realOperations);
        $command = new DetachProjectDocument($repository, $operations, $source);
        $at = new DateTimeImmutable('2026-08-29 09:01:00');

        try {
            $command->handle($project, $linked->linkId, (int) $owner->id, $at, 'detach-crash');
            $this->fail('Simulated completion crash was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated_completion_crash', $exception->getMessage());
        }

        $recovered = $command->handle($project, $linked->linkId, (int) $owner->id, $at, 'detach-crash');

        $this->assertNotNull($recovered->detachedAt);
        $this->assertSame(1, DB::table('finance_project_document_links')->count());
        $this->assertSame(1, DB::table('finance_project_activities')->where('type', 'project.document_detached')->count());
        $this->assertSame('succeeded', DB::table('finance_project_operations')->where('idempotency_key', 'detach-crash')->value('state'));
        $operationId = (int) DB::table('finance_project_operations')->where('idempotency_key', 'detach-crash')->value('id');
        $this->assertSame($operationId, (int) DB::table('finance_project_document_links')->value('detached_operation_id'));
    }

    public function test_detach_retry_never_adopts_a_detach_created_by_another_operation(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Detach-owned-checkpoint.pdf');
        $source = new LegacyFileDocumentSource;
        $repository = new EloquentProjectDocumentRepository;
        $operations = app(ProjectOperationRepository::class);
        $at = new DateTimeImmutable('2026-08-29 09:00:00.123456');
        $linked = (new AttachProjectDocument($source, $repository, $operations))->handle(
            $project,
            new ProjectDocumentSourceRef('file', "file:{$file->id}"),
            'file',
            (int) $owner->id,
            $at,
            'detach-operation-setup',
        );
        $crashing = new DetachProjectDocument($repository, $this->crashAfterReservation($operations), $source);

        try {
            $crashing->handle($project, $linked->linkId, (int) $owner->id, $at->modify('+1 second'), 'detach-operation-a');
            $this->fail('Simulated pre-mutation crash was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated_pre_mutation_crash', $exception->getMessage());
        }

        $winner = (new DetachProjectDocument($repository, $operations, $source))->handle(
            $project,
            $linked->linkId,
            (int) $owner->id,
            $at->modify('+1 second'),
            'detach-operation-b',
        );

        try {
            (new DetachProjectDocument($repository, $operations, $source))->handle(
                $project,
                $linked->linkId,
                (int) $owner->id,
                $at->modify('+1 second'),
                'detach-operation-a',
            );
            $this->fail('The abandoned operation adopted another operation\'s detach.');
        } catch (DomainException $exception) {
            $this->assertSame('operation_in_progress', $exception->getMessage());
        }

        $winnerOperationId = (int) DB::table('finance_project_operations')->where('idempotency_key', 'detach-operation-b')->value('id');
        $this->assertSame($winner->linkId, $linked->linkId);
        $this->assertSame($winnerOperationId, (int) DB::table('finance_project_document_links')->value('detached_operation_id'));
        $this->assertNull(DB::table('finance_project_operations')->where('idempotency_key', 'detach-operation-a')->value('result'));
    }

    public function test_failed_detach_retry_keeps_already_detached_semantics_when_another_operation_wins(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Failed-detach-checkpoint.pdf');
        $source = new LegacyFileDocumentSource;
        $operations = app(ProjectOperationRepository::class);
        $repository = new EloquentProjectDocumentRepository;
        $linked = (new AttachProjectDocument($source, $repository, $operations))->handle(
            $project,
            new ProjectDocumentSourceRef('file', 'file:'.$file->id),
            'file',
            (int) $owner->id,
            new DateTimeImmutable('2026-08-29 09:00:00'),
            'failed-detach-setup',
        );
        $failingRepository = $this->failFirstDetachRepository($repository);
        $at = new DateTimeImmutable('2026-08-29 09:01:00.123456');

        try {
            (new DetachProjectDocument($failingRepository, $operations, $source))->handle(
                $project, $linked->linkId, (int) $owner->id, $at, 'failed-detach-operation-a',
            );
            $this->fail('The injected pre-mutation failure was not raised.');
        } catch (DomainException $exception) {
            $this->assertSame('detach_temporarily_unavailable', $exception->getMessage());
        }
        (new DetachProjectDocument($repository, $operations, $source))->handle(
            $project, $linked->linkId, (int) $owner->id, $at, 'failed-detach-operation-b',
        );

        try {
            (new DetachProjectDocument($failingRepository, $operations, $source))->handle(
                $project, $linked->linkId, (int) $owner->id, $at, 'failed-detach-operation-a',
            );
            $this->fail('The failed operation adopted another operation\'s detach.');
        } catch (DomainException $exception) {
            $this->assertSame('document_already_detached', $exception->getMessage());
        }
        $this->assertSame('failed', DB::table('finance_project_operations')->where('idempotency_key', 'failed-detach-operation-a')->value('state'));
        $this->assertSame(1, DB::table('finance_project_activities')->where('type', 'project.document_detached')->count());
    }

    public function test_operation_identity_migration_is_reversible_and_owner_composite_constraints_reject_foreign_operations(): void
    {
        $migration = require database_path('migrations/2027_03_04_130000_add_operation_identity_to_project_document_links.php');

        $this->assertTrue(Schema::hasColumns('finance_project_document_links', ['attached_operation_id', 'detached_operation_id']));
        $migration->down();
        $this->assertFalse(Schema::hasColumn('finance_project_document_links', 'attached_operation_id'));
        $this->assertFalse(Schema::hasColumn('finance_project_document_links', 'detached_operation_id'));
        $migration->up();

        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Foreign-operation.pdf');
        $foreignProject = $this->project($foreign);
        $foreignReservation = app(ProjectOperationRepository::class)->reserve(
            (int) $foreign->id,
            'project.document.attach',
            'foreign-operation',
            str_repeat('a', 64),
            $foreignProject,
        );
        $projectRecordId = (int) DB::table('finance_project_records')->where('uuid', $project->uuid)->value('id');

        try {
            DB::table('finance_project_document_links')->insert([
                'user_id' => $owner->id,
                'project_id' => $projectRecordId,
                'source_type' => 'file',
                'source_reference' => 'file:'.$file->id,
                'document_series_id' => null,
                'pinned_revision_id' => null,
                'role' => 'file',
                'metadata_snapshot' => json_encode(['title' => 'Foreign operation'], JSON_THROW_ON_ERROR),
                'attached_operation_id' => $foreignReservation->recordId,
                'detached_operation_id' => null,
                'attached_by' => $owner->id,
                'attached_at' => '2026-08-29 09:00:00.123456',
                'detached_by' => null,
                'detached_at' => null,
            ]);
            $this->fail('A foreign owner operation was accepted as attach identity.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $linked = app(AttachProjectDocument::class)->handle(
            $project,
            new ProjectDocumentSourceRef('file', 'file:'.$file->id),
            'file',
            (int) $owner->id,
            new DateTimeImmutable('2026-08-29 09:00:00.123456'),
            'owner-operation-link',
        );
        try {
            DB::table('finance_project_document_links')->where('id', $linked->linkId)->update([
                'detached_by' => $owner->id,
                'detached_at' => '2026-08-29 09:01:00.123456',
                'detached_operation_id' => $foreignReservation->recordId,
            ]);
            $this->fail('A foreign owner operation was accepted as detach identity.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_operation_identity_preserves_link_history_but_owner_deletion_cascades_the_whole_graph(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Cascade-history.pdf');
        $linked = app(AttachProjectDocument::class)->handle(
            $project,
            new ProjectDocumentSourceRef('file', 'file:'.$file->id),
            'file',
            (int) $owner->id,
            new DateTimeImmutable('2026-08-29 09:00:00.123456'),
            'cascade-history-attach',
        );
        $operationId = (int) DB::table('finance_project_document_links')->where('id', $linked->linkId)->value('attached_operation_id');

        try {
            DB::table('finance_project_operations')->where('id', $operationId)->delete();
            $this->fail('An operation referenced by immutable link history was deleted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        DB::table('users')->where('id', $owner->id)->delete();
        $this->assertSame(0, DB::table('finance_project_document_links')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_project_operations')->where('user_id', $owner->id)->count());
    }

    public function test_postgresql_concurrent_same_and_different_keys_serialize_to_one_active_link_when_configured(): void
    {
        $this->withIsolatedPostgresDocumentSchema(function (string $postgresUrl, string $schema): void {
            $uuid = (string) Str::uuid();
            $now = '2026-08-29 09:00:00';
            $projectRecordId = (int) DB::table('finance_project_records')->insertGetId([
                'user_id' => 1, 'uuid' => $uuid, 'parent_project_id' => null, 'source_type' => null,
                'source_id' => null, 'name' => 'Concurrent', 'kind' => 'business', 'status' => 'planned',
                'partner_reference' => null, 'starts_on' => null, 'due_on' => null, 'budget_minor' => null,
                'currency' => 'EUR', 'version' => 0, 'archived_at' => null, 'created_by' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $fileId = (int) DB::table('files')->insertGetId([
                'user_id' => 1, 'name' => 'Concurrent.pdf', 'mime' => 'application/pdf', 'size' => 1,
                'storage_path' => 'private/concurrent.pdf', 'sha256' => str_repeat('c', 64),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $project = new ProjectId(1, $uuid);
            $ref = new ProjectDocumentSourceRef('file', 'file:'.$fileId);
            DB::beginTransaction();
            $processes = [];

            try {
                DB::table('finance_project_records')->where('id', $projectRecordId)->lockForUpdate()->first();
                $winner = app(AttachProjectDocument::class)->handle($project, $ref, 'file', 1, new DateTimeImmutable($now), 'concurrent-same');
                $processes = [
                    $this->startPostgresDocumentWorker($postgresUrl, $schema, $uuid, $fileId, 'concurrent-same'),
                    $this->startPostgresDocumentWorker($postgresUrl, $schema, $uuid, $fileId, 'concurrent-different'),
                ];
                foreach ($processes as $process) {
                    $this->waitForDocumentWorkerReady($process);
                }
                DB::commit();
                foreach ($processes as $process) {
                    $process->wait();
                }
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                foreach ($processes as $process) {
                    if ($process->isRunning()) {
                        $process->stop(1.0);
                    }
                }
            }

            $this->assertSame(1, $winner->linkId);
            $this->assertStringContainsString('"link_id":1', $processes[0]->getOutput());
            $this->assertStringContainsString('"error":"document_already_attached"', $processes[1]->getOutput());
            $this->assertSame(1, DB::table('finance_project_document_links')->count());
            $this->assertSame(1, DB::table('finance_project_activities')->where('type', 'project.document_attached')->count());
        });
    }

    public function test_detach_is_idempotent_append_only_and_reattach_creates_a_new_history_row(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Receipt.pdf');
        $repository = new EloquentProjectDocumentRepository;
        $operations = app(ProjectOperationRepository::class);
        $attach = new AttachProjectDocument(new LegacyFileDocumentSource, $repository, $operations);
        $detach = new DetachProjectDocument($repository, $operations, new LegacyFileDocumentSource);
        $ref = new ProjectDocumentSourceRef('file', "file:{$file->id}");
        $at = new DateTimeImmutable('2026-08-29 09:00:00');
        $linked = $attach->handle($project, $ref, 'file', (int) $owner->id, $at, 'attach-a');

        $detached = $detach->handle($project, $linked->linkId, (int) $owner->id, $at->modify('+1 minute'), 'detach-a');
        $replay = $detach->handle($project, $linked->linkId, (int) $owner->id, $at->modify('+1 minute'), 'detach-a');
        $replacement = $attach->handle($project, $ref, 'file', (int) $owner->id, $at->modify('+2 minutes'), 'attach-b');

        $this->assertNotNull($detached->detachedAt);
        $this->assertSame($detached->linkId, $replay->linkId);
        $this->assertNotSame($linked->linkId, $replacement->linkId);
        $this->assertSame(2, DB::table('finance_project_document_links')->count());
    }

    public function test_detach_idempotency_digest_and_result_preserve_microseconds(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Detach-microseconds.pdf');
        $source = new LegacyFileDocumentSource;
        $repository = new EloquentProjectDocumentRepository;
        $operations = app(ProjectOperationRepository::class);
        $linked = (new AttachProjectDocument($source, $repository, $operations))->handle(
            $project,
            new ProjectDocumentSourceRef('file', 'file:'.$file->id),
            'file',
            (int) $owner->id,
            new DateTimeImmutable('2026-08-29 09:00:00'),
            'detach-microseconds-setup',
        );
        $command = new DetachProjectDocument($repository, $operations, $source);

        $first = $command->handle($project, $linked->linkId, (int) $owner->id, new DateTimeImmutable('2026-08-29 09:01:00.123456'), 'detach-microseconds');
        $this->assertSame('123456', $first->detachedAt?->format('u'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('idempotency_key_reused');
        $command->handle($project, $linked->linkId, (int) $owner->id, new DateTimeImmutable('2026-08-29 09:01:00.654321'), 'detach-microseconds');
    }

    public function test_list_uses_historical_snapshot_when_the_owned_source_was_deleted(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Historical.pdf');
        $repository = new EloquentProjectDocumentRepository;
        $attach = new AttachProjectDocument(
            new LegacyFileDocumentSource,
            $repository,
            app(ProjectOperationRepository::class),
        );
        $ref = new ProjectDocumentSourceRef('file', "file:{$file->id}");
        $attach->handle($project, $ref, 'file', (int) $owner->id, new DateTimeImmutable('2026-08-29 09:00:00'), 'attach-history');
        $file->delete();

        $page = (new ListProjectDocuments($repository, new LegacyFileDocumentSource))->handle(
            new ProjectDocumentFilter($project),
        );

        $this->assertCount(1, $page->items);
        $this->assertSame('deleted', $page->items[0]->availability);
        $this->assertSame('Historical.pdf', $page->items[0]->snapshot['title']);
        $this->assertNull($page->items[0]->current);
    }

    public function test_list_state_filters_and_sanitizes_untrusted_historical_snapshots(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $projectRecordId = (int) DB::table('finance_project_records')->where('uuid', $project->uuid)->value('id');
        $file = $this->file($owner, 'Active.pdf');
        $repository = new EloquentProjectDocumentRepository;
        $source = new LegacyFileDocumentSource;
        $operations = app(ProjectOperationRepository::class);
        $attach = new AttachProjectDocument($source, $repository, $operations);
        $detach = new DetachProjectDocument($repository, $operations, $source);
        $at = new DateTimeImmutable('2026-08-29 09:00:00');
        $linked = $attach->handle($project, new ProjectDocumentSourceRef('file', "file:{$file->id}"), 'file', (int) $owner->id, $at, 'state-attach');
        $detach->handle($project, $linked->linkId, (int) $owner->id, $at->modify('+1 minute'), 'state-detach');
        DB::table('finance_project_document_links')->insert([
            'user_id' => $owner->id, 'project_id' => $projectRecordId, 'source_type' => 'file',
            'source_reference' => 'file:999999', 'document_series_id' => null, 'pinned_revision_id' => null,
            'role' => 'other', 'metadata_snapshot' => json_encode(['title' => 'Migrated', 'mime' => 'application/pdf', 'storage_path' => 'secret', 'ocr' => 'leak', 'nested' => ['secret']], JSON_THROW_ON_ERROR),
            'attached_by' => $owner->id, 'attached_at' => $at->modify('+2 minutes'), 'detached_by' => null, 'detached_at' => null,
        ]);
        $query = new ListProjectDocuments($repository, $source);

        $active = $query->handle(new ProjectDocumentFilter($project, state: 'active'));
        $detached = $query->handle(new ProjectDocumentFilter($project, state: 'detached'));
        $all = $query->handle(new ProjectDocumentFilter($project, state: 'all'));

        $this->assertCount(1, $active->items);
        $this->assertCount(1, $detached->items);
        $this->assertCount(2, $all->items);
        $this->assertSame(['title' => 'Migrated', 'mime' => 'application/pdf'], $active->items[0]->snapshot);
    }

    public function test_list_honors_query_source_role_mime_availability_date_and_page_filters(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $pdf = $this->file($owner, 'Alpha.pdf');
        $image = $this->file($owner, 'Beta.png', 'image/png');
        $source = new LegacyFileDocumentSource;
        $repository = new EloquentProjectDocumentRepository;
        $attach = new AttachProjectDocument($source, $repository, app(ProjectOperationRepository::class));
        $attach->handle($project, new ProjectDocumentSourceRef('file', "file:{$pdf->id}"), 'file', (int) $owner->id, new DateTimeImmutable('2026-08-29 09:00:00'), 'filter-pdf');
        $attach->handle($project, new ProjectDocumentSourceRef('file', "file:{$image->id}"), 'other', (int) $owner->id, new DateTimeImmutable('2026-08-29 10:00:00'), 'filter-image');
        $query = new ListProjectDocuments($repository, $source);

        $filtered = $query->handle(new ProjectDocumentFilter(
            $project, q: 'beta', sourceTypes: ['file'], roles: ['other'], mimeGroups: ['image'],
            availabilities: ['available'], from: new DateTimeImmutable('2026-08-29 09:30:00'), to: new DateTimeImmutable('2026-08-29 10:30:00'),
        ));
        $secondPage = $query->handle(new ProjectDocumentFilter($project, page: 2, perPage: 1));

        $this->assertCount(1, $filtered->items);
        $this->assertSame('Beta.png', $filtered->items[0]->current?->title);
        $this->assertSame(2, $secondPage->total);
        $this->assertSame('Alpha.pdf', $secondPage->items[0]->current?->title);
    }

    private function project(User $owner): ProjectId
    {
        $uuid = (string) Str::uuid();
        DB::table('finance_project_records')->insert([
            'user_id' => $owner->id, 'uuid' => $uuid, 'parent_project_id' => null,
            'source_type' => null, 'source_id' => null, 'name' => 'Documents',
            'kind' => 'business', 'status' => 'planned', 'partner_reference' => null,
            'starts_on' => null, 'due_on' => null, 'budget_minor' => null, 'currency' => 'EUR',
            'version' => 0, 'archived_at' => null, 'created_by' => $owner->id,
            'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
        ]);

        return new ProjectId((int) $owner->id, $uuid);
    }

    private function file(User $owner, string $name, string $mime = 'application/pdf'): FileEntry
    {
        $id = (int) DB::table('files')->insertGetId([
            'user_id' => $owner->id,
            'name' => $name,
            'mime' => $mime,
            'size' => 123,
            'storage_path' => 'private/do-not-leak.pdf',
            'sha256' => str_repeat('a', 64),
            'favorite' => false,
            'version' => 0,
            'created_at' => '2026-08-29 08:00:00',
            'updated_at' => '2026-08-29 08:00:00',
        ]);

        return FileEntry::query()->withoutGlobalScopes()->findOrFail($id);
    }

    private function crashingOperations(ProjectOperationRepository $inner): ProjectOperationRepository
    {
        return new class($inner) implements ProjectOperationRepository
        {
            private bool $crash = true;

            public function __construct(private readonly ProjectOperationRepository $inner) {}

            public function reserve(int $ownerId, string $operation, string $key, string $requestSha256, ?ProjectId $projectId): OperationReservation
            {
                return $this->inner->reserve($ownerId, $operation, $key, $requestSha256, $projectId);
            }

            public function succeed(OperationReservation $reservation, array $result): void
            {
                if ($this->crash) {
                    throw new RuntimeException('simulated_completion_crash');
                }
                $this->inner->succeed($reservation, $result);
            }

            public function fail(OperationReservation $reservation, string $errorCode): void
            {
                if ($this->crash) {
                    $this->crash = false;
                    throw new RuntimeException('simulated_completion_crash');
                }
                $this->inner->fail($reservation, $errorCode);
            }

            public function retryFailed(OperationReservation $reservation): OperationReservation
            {
                return $this->inner->retryFailed($reservation);
            }
        };
    }

    private function crashAfterReservation(ProjectOperationRepository $inner): ProjectOperationRepository
    {
        return new class($inner) implements ProjectOperationRepository
        {
            private bool $crash = true;

            public function __construct(private readonly ProjectOperationRepository $inner) {}

            public function reserve(int $ownerId, string $operation, string $key, string $requestSha256, ?ProjectId $projectId): OperationReservation
            {
                $reservation = $this->inner->reserve($ownerId, $operation, $key, $requestSha256, $projectId);
                if ($this->crash) {
                    $this->crash = false;
                    throw new RuntimeException('simulated_pre_mutation_crash');
                }

                return $reservation;
            }

            public function succeed(OperationReservation $reservation, array $result): void
            {
                $this->inner->succeed($reservation, $result);
            }

            public function fail(OperationReservation $reservation, string $errorCode): void
            {
                $this->inner->fail($reservation, $errorCode);
            }

            public function retryFailed(OperationReservation $reservation): OperationReservation
            {
                return $this->inner->retryFailed($reservation);
            }
        };
    }

    private function failFirstResolveSource(ProjectDocumentSource $inner): ProjectDocumentSource
    {
        return new class($inner) implements ProjectDocumentSource
        {
            private bool $fail = true;

            public function __construct(private readonly ProjectDocumentSource $inner) {}

            public function supports(string $sourceType): bool
            {
                return $this->inner->supports($sourceType);
            }

            public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
            {
                if ($this->fail) {
                    $this->fail = false;
                    throw new DomainException('source_temporarily_unavailable');
                }

                return $this->inner->resolve($ownerId, $ref);
            }

            public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
            {
                return $this->inner->search($ownerId, $filter);
            }
        };
    }

    private function failFirstDetachRepository(ProjectDocumentRepository $inner): ProjectDocumentRepository
    {
        return new class($inner) implements ProjectDocumentRepository
        {
            private bool $fail = true;

            public function __construct(private readonly ProjectDocumentRepository $inner) {}

            public function attach(ProjectId $projectId, ProjectDocumentMetadata $metadata, string $role, int $actorId, DateTimeImmutable $at, ?int $operationId = null): ProjectDocumentView
            {
                return $this->inner->attach($projectId, $metadata, $role, $actorId, $at, $operationId);
            }

            public function detach(ProjectId $projectId, int $linkId, int $actorId, DateTimeImmutable $at, ?int $operationId = null): ProjectDocumentView
            {
                if ($this->fail) {
                    $this->fail = false;
                    throw new DomainException('detach_temporarily_unavailable');
                }

                return $this->inner->detach($projectId, $linkId, $actorId, $at, $operationId);
            }

            public function get(ProjectId $projectId, int $linkId, ?ProjectDocumentSource $catalog = null): ProjectDocumentView
            {
                return $this->inner->get($projectId, $linkId, $catalog);
            }

            public function findActive(ProjectId $projectId, ProjectDocumentSourceRef $source, string $role, ProjectDocumentSource $catalog): ?ProjectDocumentView
            {
                return $this->inner->findActive($projectId, $source, $role, $catalog);
            }

            public function findAttachedByOperation(ProjectId $projectId, int $operationId, ProjectDocumentSource $catalog): ?ProjectDocumentView
            {
                return $this->inner->findAttachedByOperation($projectId, $operationId, $catalog);
            }

            public function findDetachedByOperation(ProjectId $projectId, int $operationId, ProjectDocumentSource $catalog): ?ProjectDocumentView
            {
                return $this->inner->findDetachedByOperation($projectId, $operationId, $catalog);
            }

            public function page(ProjectDocumentFilter $filter, ProjectDocumentSource $catalog): ProjectDocumentPage
            {
                return $this->inner->page($filter, $catalog);
            }
        };
    }

    /** @return array{string, int} */
    private function financeSeries(User $owner, string $type, string $number): array
    {
        $uuid = (string) Str::uuid();
        $seriesId = (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => $owner->id, 'uuid' => $uuid, 'document_type' => $type, 'status' => 'published',
            'source_type' => null, 'source_id' => null, 'created_by' => $owner->id,
            'created_at' => '2026-08-29 08:00:00', 'updated_at' => '2026-08-29 08:00:00',
        ]);
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id, 'document_series_id' => $seriesId, 'revision_number' => 1,
            'previous_revision_id' => null, 'status' => 'published',
            'snapshot' => json_encode(['number' => $number], JSON_THROW_ON_ERROR),
            'net_minor' => 100, 'vat_minor' => 19, 'gross_minor' => 119, 'currency' => 'EUR',
            'change_reason' => null, 'pdf_path' => 'private/never-leak.pdf', 'pdf_sha256' => str_repeat('b', 64),
            'published_at' => '2026-08-29 08:00:00', 'created_by' => $owner->id, 'created_at' => '2026-08-29 08:00:00',
        ]);

        return [$uuid, $revisionId];
    }

    private function pagedSource(string $type, int $count): ProjectDocumentSource
    {
        return new class($type, $count) implements ProjectDocumentSource
        {
            public function __construct(private readonly string $type, private readonly int $count) {}

            public function supports(string $sourceType): bool
            {
                return $sourceType === $this->type;
            }

            public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
            {
                $id = (int) substr($ref->sourceReference, strrpos($ref->sourceReference, ':') + 1);

                return new ProjectDocumentMetadata($ref, $this->type.' '.$id, null, null, null, $this->type, null, (new DateTimeImmutable('2026-12-31 00:00:00'))->modify('-'.($id - 1).' seconds'));
            }

            public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
            {
                $offset = $filter->cursor === null ? 0 : (int) base64_decode($filter->cursor, true);
                $items = [];
                for ($index = $offset; $index < min($this->count, $offset + $filter->perPage); $index++) {
                    $id = $index + 1;
                    $reference = $this->type === 'file' ? 'file:'.$id : 'legacy-invoice:'.$id;
                    $items[] = $this->resolve($ownerId, new ProjectDocumentSourceRef($this->type, $reference));
                }
                $next = $offset + count($items);

                return new ProjectDocumentSourcePage($items, $next < $this->count ? base64_encode((string) $next) : null);
            }
        };
    }

    private function singleItemSource(string $type, string $reference, string $occurredAt): ProjectDocumentSource
    {
        return new class($type, $reference, $occurredAt) implements ProjectDocumentSource
        {
            public function __construct(
                private readonly string $type,
                private readonly string $reference,
                private readonly string $occurredAt,
            ) {}

            public function supports(string $sourceType): bool
            {
                return $sourceType === $this->type;
            }

            public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
            {
                return new ProjectDocumentMetadata(
                    $ref,
                    $this->reference,
                    null,
                    null,
                    null,
                    $this->type,
                    null,
                    new DateTimeImmutable($this->occurredAt),
                );
            }

            public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
            {
                if ($filter->cursor !== null) {
                    return new ProjectDocumentSourcePage([], null);
                }

                return new ProjectDocumentSourcePage([
                    $this->resolve($ownerId, new ProjectDocumentSourceRef($this->type, $this->reference)),
                ], base64_encode('1'));
            }
        };
    }

    /**
     * @param  list<list<array{string, string}>>  $pages
     */
    private function scriptedSource(string $type, array $pages): ProjectDocumentSource
    {
        return new class($type, $pages) implements ProjectDocumentSource
        {
            /** @param list<list<array{string, string}>> $pages */
            public function __construct(private readonly string $type, private readonly array $pages) {}

            public function supports(string $sourceType): bool
            {
                return $sourceType === $this->type;
            }

            public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
            {
                foreach ($this->pages as $page) {
                    foreach ($page as [$reference, $occurredAt]) {
                        if ($reference === $ref->sourceReference) {
                            return new ProjectDocumentMetadata($ref, $reference, null, null, null, $this->type, null, new DateTimeImmutable($occurredAt));
                        }
                    }
                }

                throw new ModelNotFoundException;
            }

            public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
            {
                $index = $filter->cursor === null ? 0 : (int) base64_decode($filter->cursor, true);
                $items = array_map(
                    fn (array $item): ProjectDocumentMetadata => $this->resolve($ownerId, new ProjectDocumentSourceRef($this->type, $item[0])),
                    $this->pages[$index] ?? [],
                );
                $next = $index + 1;

                return new ProjectDocumentSourcePage($items, $next < count($this->pages) ? base64_encode((string) $next) : null);
            }
        };
    }

    /** @return list<string> */
    private function sourceReferences(ProjectDocumentSource $source, int $ownerId, string $type): array
    {
        $cursor = null;
        $references = [];
        $hops = 0;
        do {
            $page = $source->search($ownerId, new ProjectDocumentSourceFilter($ownerId, sourceTypes: [$type], cursor: $cursor, perPage: 1));
            foreach ($page->items as $item) {
                $references[] = $item->source->sourceReference;
            }
            $cursor = $page->nextCursor;
            $hops++;
            if ($hops > 20) {
                $this->fail('Source cursor did not terminate for '.$type);
            }
        } while ($cursor !== null);

        return $references;
    }

    /** @param callable(string, string): void $test */
    private function withIsolatedPostgresDocumentSchema(callable $test): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');
        if (! extension_loaded('pdo_pgsql') || ! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped('Set FINANCE_TEST_PGSQL_URL and install pdo_pgsql to run Project document concurrency tests.');
        }
        $defaultConnection = DB::getDefaultConnection();
        $connectionName = 'pgsql_project_documents';
        $schema = 'finance_project_task8_'.bin2hex(random_bytes(8));
        $postgresConfig = config('database.connections.pgsql');
        if (! is_array($postgresConfig)) {
            throw new RuntimeException('PostgreSQL connection configuration is unavailable.');
        }
        config(["database.connections.{$connectionName}" => array_merge($postgresConfig, ['url' => $postgresUrl, 'search_path' => 'public'])]);
        DB::purge($connectionName);
        $connection = DB::connection($connectionName);
        $created = false;

        try {
            $connection->statement('CREATE SCHEMA "'.$schema.'"');
            $created = true;
            $connection->statement('SET search_path TO "'.$schema.'"');
            DB::setDefaultConnection($connectionName);
            Schema::clearResolvedInstance('db.schema');
            Schema::create('users', static fn (Blueprint $table) => $table->id());
            foreach ([
                require database_path('migrations/2026_08_28_100000_create_finance_document_core.php'),
                require database_path('migrations/2027_03_04_100000_create_finance_project_workflow.php'),
                require database_path('migrations/2027_03_04_130000_add_operation_identity_to_project_document_links.php'),
            ] as $migration) {
                $migration->up();
            }
            Schema::create('files', static function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->string('mime')->nullable();
                $table->unsignedBigInteger('size');
                $table->string('storage_path');
                $table->char('sha256', 64)->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
            DB::table('users')->insert(['id' => 1]);
            $test($postgresUrl, $schema);
        } finally {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            DB::setDefaultConnection($defaultConnection);
            Schema::clearResolvedInstance('db.schema');
            if ($created) {
                $connection->statement('SET search_path TO public');
                $connection->statement('DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE');
            }
            DB::purge($connectionName);
        }
    }

    private function startPostgresDocumentWorker(string $postgresUrl, string $schema, string $projectUuid, int $fileId, string $key): Process
    {
        $script = <<<'PHP'
            require getcwd().'/vendor/autoload.php';
            $app = require getcwd().'/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            $name = 'pgsql_project_documents_worker';
            $base = config('database.connections.pgsql');
            config(['database.connections.'.$name => array_merge($base, ['url' => getenv('FINANCE_TEST_PGSQL_URL'), 'search_path' => getenv('FINANCE_TEST_PGSQL_SCHEMA')])]);
            \Illuminate\Support\Facades\DB::purge($name);
            \Illuminate\Support\Facades\DB::setDefaultConnection($name);
            \Illuminate\Support\Facades\DB::statement('SET search_path TO "'.getenv('FINANCE_TEST_PGSQL_SCHEMA').'"');
            echo 'ready='.getmypid().PHP_EOL;
            flush();
            try {
                $view = app(\App\Modules\Finance\Application\Commands\Projects\AttachProjectDocument::class)->handle(
                    new \App\Modules\Finance\Application\DTOs\Projects\ProjectId(1, getenv('FINANCE_TEST_PROJECT_UUID')),
                    new \App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef('file', 'file:'.getenv('FINANCE_TEST_FILE_ID')),
                    'file', 1, new \DateTimeImmutable('2026-08-29 09:00:00'), getenv('FINANCE_TEST_PROJECT_KEY'),
                );
                echo json_encode(['link_id' => $view->linkId], JSON_THROW_ON_ERROR).PHP_EOL;
            } catch (\Throwable $exception) {
                echo json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR).PHP_EOL;
            }
            PHP;
        $process = new Process([PHP_BINARY, '-r', $script], base_path(), [
            'FINANCE_TEST_PGSQL_URL' => $postgresUrl, 'FINANCE_TEST_PGSQL_SCHEMA' => $schema,
            'FINANCE_TEST_PROJECT_UUID' => $projectUuid, 'FINANCE_TEST_FILE_ID' => (string) $fileId,
            'FINANCE_TEST_PROJECT_KEY' => $key,
        ], null, 20);
        $process->start();

        return $process;
    }

    private function waitForDocumentWorkerReady(Process $process): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            if (str_contains($process->getOutput(), 'ready=')) {
                return;
            }
            if (! $process->isRunning()) {
                break;
            }
            usleep(20_000);
        }
        $this->fail('PostgreSQL document worker did not become ready: '.$process->getErrorOutput().$process->getOutput());
    }
}
