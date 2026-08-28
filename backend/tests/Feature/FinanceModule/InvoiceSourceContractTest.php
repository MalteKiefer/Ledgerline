<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Invoices\CreateInvoiceDraftFromSource;
use App\Modules\Finance\Application\Commands\Invoices\DeleteInvoiceDraft;
use App\Modules\Finance\Application\Commands\Invoices\UpdateInvoiceDraft;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\Ports\IdempotencyStore;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Infrastructure\Persistence\EloquentIdempotencyStore;
use App\Modules\Finance\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Modules\Finance\Infrastructure\SystemClock;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class InvoiceSourceContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $clock = new SystemClock;
        $idempotency = new EloquentIdempotencyStore($clock);
        $this->app->instance(IdempotencyStore::class, $idempotency);
        $this->app->instance(
            InvoiceRepository::class,
            new EloquentInvoiceRepository($idempotency, $clock),
        );
    }

    public function test_identical_source_calls_replay_one_immutable_unnumbered_draft(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $source = $this->source();
        $key = new IdempotencyKey('source-create-77');
        $command = $this->app->make(CreateInvoiceDraftFromSource::class);

        $created = $command->handle($source, $key);
        $replayed = $command->handle($source, $key);

        $this->assertSame($created->id->value, $replayed->id->value);
        $this->assertSame('quote_revision', $created->sourceType);
        $this->assertSame('quote-77:revision-3', $created->sourceKey);
        $this->assertSame(77, $created->sourceRevisionId);
        $this->assertSame(hash('sha256', 'immutable quote revision 77'), $created->sourceSnapshotSha256);
        $this->assertSame('quote_revision', $created->snapshot['source']['type']);
        $this->assertSame('quote-77:revision-3', $created->snapshot['source']['key']);
        $this->assertSame(77, $created->snapshot['source']['revision_id']);
        $this->assertSame(
            hash('sha256', 'immutable quote revision 77'),
            $created->snapshot['source']['snapshot_sha256'],
        );
        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{64}\z/D',
            $created->snapshot['source']['request_sha256'],
        );
        $this->assertSame('draft', $created->status);
        $this->assertNull($created->number);
        $this->assertSame(1, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
        $this->assertSame(1, DB::table('finance_document_revisions')->where('user_id', $owner->id)->count());
        $this->assertSame(1, DB::table('finance_document_activities')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_invoice_sequences')->where('user_id', $owner->id)->count());
        $this->assertDatabaseHas('finance_idempotency_records', [
            'user_id' => $owner->id,
            'operation' => 'invoice.create_from_source',
            'key_hash' => $key->hash(),
            'status' => 'completed',
        ]);
    }

    public function test_source_metadata_rejects_unknown_labels_invalid_identity_and_noncanonical_hashes(): void
    {
        foreach ([
            ['other', 'key', 1, hash('sha256', 'snapshot')],
            ['quote_revision', '   ', 1, hash('sha256', 'snapshot')],
            ['quote_revision', str_repeat('k', 256), 1, hash('sha256', 'snapshot')],
            ['quote_revision', 'key', 0, hash('sha256', 'snapshot')],
            ['quote_revision', 'key', 1, strtoupper(hash('sha256', 'snapshot'))],
            ['quote_revision', 'key', 1, 'not-a-hash'],
        ] as [$type, $key, $revisionId, $sha256]) {
            try {
                new InvoiceDraftSource($type, $key, $revisionId, $sha256, $this->draft());
                $this->fail('Invalid invoice source metadata crossed the immutable boundary.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_same_source_identity_rejects_a_different_draft_even_with_a_new_operation_key(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $command = $this->app->make(CreateInvoiceDraftFromSource::class);
        $source = $this->source();
        $created = $command->handle($source, new IdempotencyKey('source-original-77'));
        $changed = new InvoiceDraftSource(
            $source->sourceType,
            $source->sourceKey,
            $source->sourceRevisionId,
            $source->sourceSnapshotSha256,
            new InvoiceDraftData(
                issueDate: new DateTimeImmutable('2026-08-28'),
                dueDate: new DateTimeImmutable('2026-09-11'),
                currency: 'EUR',
                customer: ['name' => 'Changed customer'],
                lines: [new InvoiceLineData('Work', '2.5000', 10_000, 1_900, 'h', null, 'service')],
                discount: Discount::none('EUR'),
            ),
        );

        try {
            $command->handle($changed, new IdempotencyKey('source-conflict-77'));
            $this->fail('A source identity was reused for a different invoice draft.');
        } catch (DomainException $exception) {
            $this->assertSame('source_snapshot_conflict', $exception->getMessage());
        }

        $this->assertSame(1, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
        $this->assertSame(1, DB::table('finance_idempotency_records')->where('user_id', $owner->id)->count());
        $this->assertSame('ACME', $created->snapshot['customer']['name']);
    }

    public function test_draft_updates_preserve_the_source_contract_and_later_source_replay(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $source = $this->source();
        $created = $this->app->make(CreateInvoiceDraftFromSource::class)->handle(
            $source,
            new IdempotencyKey('source-before-edit-77'),
        );
        $sourceSnapshot = $created->snapshot['source'];

        $updated = $this->app->make(UpdateInvoiceDraft::class)->handle(
            $created->id,
            0,
            new InvoiceDraftData(
                issueDate: new DateTimeImmutable('2026-08-29'),
                dueDate: new DateTimeImmutable('2026-09-12'),
                currency: 'EUR',
                customer: ['name' => 'Edited invoice customer'],
                lines: [new InvoiceLineData('Adjusted work', '1.0000', 10_000, 1_900, 'h', null, 'service')],
                discount: Discount::none('EUR'),
            ),
        );
        $replayed = $this->app->make(CreateInvoiceDraftFromSource::class)->handle(
            $source,
            new IdempotencyKey('source-after-edit-77'),
        );

        $this->assertSame($sourceSnapshot, $updated->snapshot['source']);
        $this->assertSame($updated->id->value, $replayed->id->value);
        $this->assertSame(1, $replayed->version);
        $this->assertSame('Edited invoice customer', $replayed->snapshot['customer']['name']);
        $this->assertSame($sourceSnapshot, $replayed->snapshot['source']);
        $this->assertSame(1, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
    }

    public function test_control_totals_do_not_change_the_source_idempotency_identity(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $source = $this->source();
        $withoutControls = new InvoiceDraftSource(
            $source->sourceType,
            $source->sourceKey,
            $source->sourceRevisionId,
            $source->sourceSnapshotSha256,
            new InvoiceDraftData(
                issueDate: new DateTimeImmutable('2026-08-28'),
                dueDate: new DateTimeImmutable('2026-09-11'),
                currency: 'EUR',
                customer: ['name' => 'ACME'],
                lines: [new InvoiceLineData('Work', '2.5000', 10_000, 1_900, 'h', null, 'service')],
                discount: Discount::none('EUR'),
            ),
        );
        $key = new IdempotencyKey('source-control-retry-77');
        $command = $this->app->make(CreateInvoiceDraftFromSource::class);

        $created = $command->handle($source, $key);
        $replayed = $command->handle($withoutControls, $key);

        $this->assertSame($created->id->value, $replayed->id->value);
        $this->assertSame(1, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
    }

    public function test_source_creation_and_idempotency_completion_are_one_atomic_transaction(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $source = $this->source();
        $key = new IdempotencyKey('source-atomic-77');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER invoice_source_completion_failure
            BEFORE UPDATE ON finance_idempotency_records
            WHEN NEW.operation = 'invoice.create_from_source' AND NEW.status = 'completed'
            BEGIN
                SELECT RAISE(ABORT, 'injected_invoice_source_completion_failure');
            END
            SQL);

        try {
            $this->app->make(CreateInvoiceDraftFromSource::class)->handle($source, $key);
            $this->fail('The injected idempotency completion failure was not observed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'injected_invoice_source_completion_failure',
                $exception->getMessage(),
            );
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS invoice_source_completion_failure');
        }

        $this->assertSame(0, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_document_series')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_document_revisions')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_document_activities')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_idempotency_records')->where('user_id', $owner->id)->count());

        $retried = $this->app->make(CreateInvoiceDraftFromSource::class)->handle($source, $key);
        $replayed = $this->app->make(CreateInvoiceDraftFromSource::class)->handle($source, $key);
        $this->assertSame($retried->id->value, $replayed->id->value);
        $this->assertSame(1, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
    }

    public function test_source_identity_and_result_lookup_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $command = $this->app->make(CreateInvoiceDraftFromSource::class);
        $source = $this->source();
        $key = new IdempotencyKey('same-key-for-each-owner');
        $this->actingAs($owner);
        $first = $command->handle($source, $key);
        $this->actingAs($otherOwner);
        $second = $command->handle($source, $key);

        $this->assertNotSame($first->id->value, $second->id->value);
        $this->assertSame(1, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
        $this->assertSame(1, DB::table('finance_invoices')->where('user_id', $otherOwner->id)->count());
        try {
            $this->app->make(InvoiceRepository::class)->get($first->id);
            $this->fail('A source-created invoice leaked across owners.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_source_creation_rolls_back_its_reservation_for_a_foreign_draft_reference(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $foreignPartnerId = (int) DB::table('finance_partners')->insertGetId([
            'user_id' => $otherOwner->id,
            'name' => 'Foreign partner',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignProjectId = (int) DB::table('finance_projects')->insertGetId([
            'user_id' => $otherOwner->id,
            'name' => 'Foreign project',
            'kind' => 'business',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($owner);
        $source = $this->source();
        foreach ([
            ['partnerId' => $foreignPartnerId, 'projectId' => null],
            ['partnerId' => null, 'projectId' => $foreignProjectId],
        ] as $index => $references) {
            $foreign = new InvoiceDraftSource(
                $source->sourceType,
                $source->sourceKey,
                $source->sourceRevisionId,
                $source->sourceSnapshotSha256,
                new InvoiceDraftData(
                    new DateTimeImmutable('2026-08-28'),
                    new DateTimeImmutable('2026-09-11'),
                    'EUR',
                    ['name' => 'ACME'],
                    [new InvoiceLineData('Work', '1.0000', 10_000, 1_900, 'h', null, 'service')],
                    Discount::none('EUR'),
                    partnerId: $references['partnerId'],
                    projectId: $references['projectId'],
                ),
            );

            try {
                $this->app->make(CreateInvoiceDraftFromSource::class)->handle(
                    $foreign,
                    new IdempotencyKey("foreign-source-reference-{$index}"),
                );
                $this->fail('A foreign source draft reference resolved for the authenticated owner.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_idempotency_records')->where('user_id', $owner->id)->count());
    }

    public function test_source_created_draft_cannot_be_deleted_and_remains_replayable(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $source = $this->source();
        $key = new IdempotencyKey('source-delete-guard-77');
        $created = $this->app->make(CreateInvoiceDraftFromSource::class)->handle($source, $key);

        try {
            $this->app->make(DeleteInvoiceDraft::class)->handle($created->id, 0);
            $this->fail('A source-created draft was deleted and broke replay identity.');
        } catch (DomainException $exception) {
            $this->assertSame('source_invoice_not_deletable', $exception->getMessage());
        }

        $replayed = $this->app->make(CreateInvoiceDraftFromSource::class)->handle($source, $key);
        $this->assertSame($created->id->value, $replayed->id->value);
        $this->assertSame(1, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
        $this->assertSame(1, DB::table('finance_document_activities')->where('user_id', $owner->id)->count());
    }

    private function source(): InvoiceDraftSource
    {
        return new InvoiceDraftSource(
            sourceType: 'quote_revision',
            sourceKey: 'quote-77:revision-3',
            sourceRevisionId: 77,
            sourceSnapshotSha256: hash('sha256', 'immutable quote revision 77'),
            draft: $this->draft(),
        );
    }

    private function draft(): InvoiceDraftData
    {
        return new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME'],
            lines: [new InvoiceLineData('Work', '2.5000', 10_000, 1_900, 'h', null, 'service')],
            discount: Discount::none('EUR'),
            controlNetMinor: 25_000,
            controlVatMinor: 4_750,
            controlGrossMinor: 29_750,
        );
    }
}
