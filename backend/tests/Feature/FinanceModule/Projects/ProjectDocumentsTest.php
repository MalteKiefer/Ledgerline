<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\FileEntry;
use App\Models\User;
use App\Modules\Finance\Application\Commands\Projects\AttachProjectDocument;
use App\Modules\Finance\Application\Commands\Projects\DetachProjectDocument;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function test_detach_is_idempotent_append_only_and_reattach_creates_a_new_history_row(): void
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $file = $this->file($owner, 'Receipt.pdf');
        $repository = new EloquentProjectDocumentRepository;
        $operations = app(ProjectOperationRepository::class);
        $attach = new AttachProjectDocument(new LegacyFileDocumentSource, $repository, $operations);
        $detach = new DetachProjectDocument($repository, $operations);
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

    private function file(User $owner, string $name): FileEntry
    {
        $id = (int) DB::table('files')->insertGetId([
            'user_id' => $owner->id,
            'name' => $name,
            'mime' => 'application/pdf',
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
}
