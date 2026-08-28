<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\User;
use App\Models\UserSetting;
use App\Modules\Finance\Application\Commands\CreateDocumentRevision;
use App\Modules\Finance\Application\Commands\Quotes\CreateQuote;
use App\Modules\Finance\Application\Commands\Quotes\PublishQuote;
use App\Modules\Finance\Application\Commands\Quotes\StartQuoteVersion;
use App\Modules\Finance\Application\Commands\Quotes\UpdateQuoteDraft;
use App\Modules\Finance\Application\DTOs\CreateRevisionData;
use App\Modules\Finance\Application\DTOs\Quotes\PublishQuoteData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteLineData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\Quotes\QuoteNumberAllocator;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Services\CanonicalDocumentSnapshot;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Persistence\DatabaseQuoteNumberAllocator;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteOperationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class QuotePublicationTest extends TestCase
{
    use RefreshDatabase;

    private QuotePublicationRenderer $renderer;

    private QuotePublicationStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new QuotePublicationRenderer;
        $this->storage = new QuotePublicationStorage;
        $this->app->instance(DocumentRenderer::class, $this->renderer);
        $this->app->instance(DocumentStorage::class, $this->storage);
    }

    public function test_quote_number_allocator_port_is_bound_to_the_database_adapter(): void
    {
        $this->assertInstanceOf(
            DatabaseQuoteNumberAllocator::class,
            $this->app->make(QuoteNumberAllocator::class),
        );
    }

    public function test_canonical_snapshot_builder_replaces_caller_amounts_and_sorts_keys(): void
    {
        $line = new DocumentLine(
            'Consulting',
            DecimalQuantity::fromString('1.5000'),
            Money::fromMinor(10_000, 'EUR'),
            1900,
        );
        $data = new CreateRevisionData(
            seriesUuid: '018f4ca3-224d-7d8d-9f00-101010101010',
            snapshot: [
                'z' => 2,
                'lines' => [['description' => 'Forged', 'unit_price_minor' => 1]],
                'a' => ['z' => 2, 'a' => 1],
                'totals' => ['gross_minor' => 1],
            ],
            lines: [$line],
            discount: Discount::none('EUR'),
        );
        $totals = (new DocumentCalculator)->calculate($data->lines, $data->discount);

        $snapshot = (new CanonicalDocumentSnapshot)->build($data, $totals);

        $this->assertSame(['a', 'lines', 'totals', 'z'], array_keys($snapshot));
        $this->assertSame(['a' => 1, 'z' => 2], $snapshot['a']);
        $this->assertSame([[
            'currency' => 'EUR',
            'description' => 'Consulting',
            'quantity_scaled' => 15_000,
            'tax_rate_basis_points' => 1900,
            'unit_price_minor' => 10_000,
        ]], $snapshot['lines']);
        $this->assertSame(15_000, $snapshot['totals']['net_minor']);
        $this->assertSame(2_850, $snapshot['totals']['vat_minor']);
        $this->assertSame(17_850, $snapshot['totals']['gross_minor']);
    }

    public function test_revision_creation_key_replays_the_same_revision_and_rejects_changed_content(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $series = new DocumentSeriesRecord;
        $series->forceFill([
            'user_id' => $owner->id,
            'uuid' => '018f4ca3-224d-7d8d-9f00-111111111119',
            'document_type' => 'quote',
            'status' => 'draft',
            'created_by' => $owner->id,
        ])->save();
        $command = $this->app->make(CreateDocumentRevision::class);
        $data = new CreateRevisionData(
            seriesUuid: (string) $series->uuid,
            snapshot: ['title' => 'Original'],
            lines: [new DocumentLine(
                'Consulting',
                DecimalQuantity::fromString('1'),
                Money::fromMinor(10_000, 'EUR'),
                1900,
            )],
            discount: Discount::none('EUR'),
        );

        $first = $command->handleIdempotently($data, 'quote-publish-operation-17');
        $replay = $command->handleIdempotently($data, 'quote-publish-operation-17');

        $this->assertSame($first->value, $replay->value);
        $this->assertSame(1, $series->revisions()->count());
        $this->assertSame(1, $series->activities()->where('type', 'revision.created')->count());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('revision_creation_key_reused');
        $command->handleIdempotently(
            new CreateRevisionData(
                seriesUuid: (string) $series->uuid,
                snapshot: ['title' => 'Changed'],
                lines: $data->lines,
                discount: $data->discount,
            ),
            'quote-publish-operation-17',
        );
    }

    public function test_start_version_copies_the_current_snapshot_without_replacing_the_sent_revision(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$published, $revisionId, $snapshot] = $this->publishedQuote($owner);

        $started = $this->app->make(StartQuoteVersion::class)->handle($published->id, 0);

        $this->assertSame('sent', $started->status);
        $this->assertSame(1, $started->version);
        $this->assertSame($revisionId, $started->currentRevision?->id);
        $this->assertSame($snapshot, $started->draft);
        $this->assertSame($revisionId, DB::table('finance_quote_drafts')
            ->where('document_series_id', $this->seriesId($published))
            ->value('based_on_revision_id'));
        $this->assertSame('published', DB::table('finance_document_revisions')
            ->where('id', $revisionId)
            ->value('status'));
        $this->assertSame('finance/quotes/original.pdf', DB::table('finance_document_revisions')
            ->where('id', $revisionId)
            ->value('pdf_path'));
        $this->assertSame(1, DB::table('finance_document_activities')
            ->where('document_series_id', $this->seriesId($published))
            ->where('type', 'quote.version.started')
            ->count());
    }

    public function test_number_allocator_honors_owner_floor_template_and_never_reuses_a_deleted_number(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->forceFill([
            'quote_number_format' => 'AN-YYYY-NNNN',
            'quote_next_number' => 73,
        ])->save();
        $deleted = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'numbered-then-deleted',
            $this->draft(),
        );
        DB::table('finance_quote_series')->where('document_series_id', $this->seriesId($deleted))->update([
            'number' => 'AN-2026-0073',
            'sequence_year' => 2026,
            'sequence_number' => 73,
            'deleted_at' => now(),
        ]);

        $allocator = $this->app->make(DatabaseQuoteNumberAllocator::class);
        $first = $allocator->allocate((int) $owner->id, '2026-08-28');
        $second = $allocator->allocate((int) $owner->id, '2026-08-28');

        $this->assertSame([
            'number' => 'AN-2026-0074',
            'year' => 2026,
            'sequence' => 74,
        ], $first);
        $this->assertSame([
            'number' => 'AN-2026-0075',
            'year' => 2026,
            'sequence' => 75,
        ], $second);
        $this->assertSame(76, DB::table('finance_quote_number_sequences')
            ->where('user_id', $owner->id)
            ->where('year', 2026)
            ->value('next_sequence'));
    }

    public function test_number_allocator_skips_a_display_number_used_by_another_year_and_rolls_back_its_counter(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->forceFill([
            'quote_number_format' => 'AN-NNNN',
            'quote_next_number' => 7,
        ])->save();
        $old = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'old-display-number',
            $this->draft(),
        );
        DB::table('finance_quote_series')->where('document_series_id', $this->seriesId($old))->update([
            'number' => 'AN-0007',
            'sequence_year' => 2025,
            'sequence_number' => 7,
        ]);
        $allocator = $this->app->make(DatabaseQuoteNumberAllocator::class);

        $allocation = $allocator->allocate((int) $owner->id, '2026-08-28');
        $this->assertSame('AN-0008', $allocation['number']);
        $this->assertSame(8, $allocation['sequence']);

        try {
            DB::transaction(function () use ($allocator, $owner): void {
                $allocated = $allocator->allocate((int) $owner->id, '2027-08-28');
                $this->assertSame('AN-0008', $allocated['number']);

                throw new RuntimeException('rollback allocation');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback allocation', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('finance_quote_number_sequences')->where('year', 2027)->count());
        $retry = $allocator->allocate((int) $owner->id, '2027-08-28');
        $this->assertSame('AN-0008', $retry['number']);
    }

    public function test_prepare_publication_retries_a_known_display_number_collision(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $create = $this->app->make(CreateQuote::class);
        $occupied = $create->handle((int) $owner->id, 'occupied-number', $this->draft(title: 'Occupied'));
        DB::table('finance_quote_series')->where('document_series_id', $this->seriesId($occupied))->update([
            'number' => 'COLLISION',
            'sequence_year' => 2026,
            'sequence_number' => 900,
        ]);
        $target = $create->handle((int) $owner->id, 'collision-target', $this->draft(title: 'Target'));
        $hash = hash('sha256', '{"publish":true}');
        $operation = $this->app->make(QuoteOperationRepository::class)->reserve(
            (int) $owner->id,
            'publish',
            'collision-retry',
            $hash,
            $target->id,
        );
        $attempts = 0;

        $prepared = $this->app->make(QuoteRepository::class)->preparePublication(
            $target->id,
            0,
            $operation->recordId,
            static function (string $_issueDate) use (&$attempts): array {
                $attempts++;

                return $attempts === 1
                    ? ['number' => 'COLLISION', 'year' => 2026, 'sequence' => 1]
                    : ['number' => 'SAFE-0001', 'year' => 2026, 'sequence' => 1];
            },
        );

        $this->assertSame(2, $attempts);
        $this->assertSame('SAFE-0001', $prepared->number);
    }

    public function test_first_publish_allocates_once_stores_a_canonical_immutable_revision_and_replays(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        UserSetting::for((int) $owner->id)->forceFill([
            'quote_number_format' => 'AN-YYYY-NNNN',
            'quote_next_number' => 7,
        ])->save();
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-before-publish',
            $this->draft(),
        );
        $command = $this->app->make(PublishQuote::class);
        $data = new PublishQuoteData(
            quoteId: $created->id,
            expectedVersion: 0,
            idempotencyKey: 'publish-initial',
            changeReason: 'Initial publication',
        );

        $published = $command->handle($data);
        $replayed = $command->handle($data);

        $this->assertSame('sent', $published->status);
        $this->assertSame('AN-2026-0007', $published->number);
        $this->assertSame(1, $published->version);
        $this->assertNull($published->draft);
        $this->assertSame($published->currentRevision?->id, $replayed->currentRevision?->id);
        $this->assertSame(1, $this->renderer->calls);
        $this->assertSame(1, $this->storage->puts);
        $this->assertSame(1, DB::table('finance_document_revisions')
            ->where('document_series_id', $this->seriesId($published))
            ->count());
        $revision = DB::table('finance_document_revisions')
            ->where('id', $published->currentRevision?->id)
            ->sole();
        $snapshot = json_decode((string) $revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([
            'currency',
            'customer',
            'customer_note',
            'discount',
            'document_number',
            'document_type',
            'intro_text',
            'issue_date',
            'lines',
            'outro_text',
            'partner_id',
            'revision_label',
            'revision_number',
            'schema_version',
            'series_uuid',
            'title',
            'totals',
            'valid_until',
        ], array_keys($snapshot));
        $this->assertSame('quote', $snapshot['document_type']);
        $this->assertSame('AN-2026-0007', $snapshot['document_number']);
        $this->assertSame('AN-2026-0007', $snapshot['revision_label']);
        $this->assertSame(1, $snapshot['revision_number']);
        $this->assertSame($created->id->uuid, $snapshot['series_uuid']);
        $this->assertSame('Network refresh', $snapshot['title']);
        $this->assertSame('Ada GmbH', $snapshot['customer']['name']);
        $this->assertNull($snapshot['customer_note']);
        $this->assertArrayNotHasKey('internal_note', $snapshot);
        $this->assertSame([[
            'currency' => 'EUR',
            'description' => 'Consulting',
            'kind' => 'service',
            'product_id' => null,
            'quantity' => '2.5000',
            'quantity_scaled' => 25_000,
            'tax_rate' => '19.00',
            'tax_rate_basis_points' => 1900,
            'unit' => 'hour',
            'unit_price' => '100.00',
            'unit_price_minor' => 10_000,
        ]], $snapshot['lines']);
        $this->assertSame(22_500, $snapshot['totals']['net_minor']);
        $this->assertSame(4_275, $snapshot['totals']['vat_minor']);
        $this->assertSame(26_775, $snapshot['totals']['gross_minor']);
        $this->assertSame(
            ['quote.created', 'revision.created', 'revision.published', 'quote.published'],
            DB::table('finance_document_activities')
                ->where('document_series_id', $this->seriesId($published))
                ->orderBy('id')
                ->pluck('type')
                ->all(),
        );
    }

    public function test_two_series_receive_distinct_numbers_and_publish_key_reuse_rejects_changed_input(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $create = $this->app->make(CreateQuote::class);
        $first = $create->handle((int) $owner->id, 'create-number-one', $this->draft(title: 'First'));
        $second = $create->handle((int) $owner->id, 'create-number-two', $this->draft(title: 'Second'));
        $publish = $this->app->make(PublishQuote::class);

        $publishedFirst = $publish->handle(new PublishQuoteData($first->id, 0, 'publish-number-one', 'First'));
        $publishedSecond = $publish->handle(new PublishQuoteData($second->id, 0, 'publish-number-two', 'Second'));

        $this->assertSame('AN-2026-0001', $publishedFirst->number);
        $this->assertSame('AN-2026-0002', $publishedSecond->number);
        $this->assertNotSame($publishedFirst->currentRevision?->id, $publishedSecond->currentRevision?->id);

        try {
            $publish->handle(new PublishQuoteData($first->id, 0, 'publish-number-one', 'Changed'));
            $this->fail('A publish idempotency key accepted changed input.');
        } catch (\DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }
    }

    public function test_publish_validation_failure_allocates_nothing_and_same_key_can_resume_after_repair(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-invalid-publication',
            $this->draft(),
        );
        $seriesId = $this->seriesId($created);
        $validPayload = $created->draft;
        $this->assertIsArray($validPayload);
        $invalidPayload = $validPayload;
        $invalidPayload['lines'] = [];
        DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->update([
            'payload' => json_encode($invalidPayload, JSON_THROW_ON_ERROR),
        ]);
        $command = $this->app->make(PublishQuote::class);
        $data = new PublishQuoteData($created->id, 0, 'repairable-validation');

        try {
            $command->handle($data);
            $this->fail('Invalid persisted publication data unexpectedly allocated a number.');
        } catch (\LogicException $exception) {
            $this->assertSame('Quote draft lines are missing.', $exception->getMessage());
        }

        $this->assertNull(DB::table('finance_quote_series')->where('document_series_id', $seriesId)->value('number'));
        $this->assertSame(0, DB::table('finance_quote_number_sequences')->count());
        $this->assertSame(0, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());
        DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->update([
            'payload' => json_encode($validPayload, JSON_THROW_ON_ERROR),
        ]);

        $published = $command->handle($data);

        $this->assertSame('sent', $published->status);
        $this->assertSame(1, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());
    }

    public function test_discount_validation_runs_before_number_allocation(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-invalid-discount-publication',
            $this->draft(),
        );
        $seriesId = $this->seriesId($created);
        $payload = $created->draft;
        $this->assertIsArray($payload);
        $payload['discount'] = ['type' => 'invented'];
        DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->update([
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        try {
            $this->app->make(PublishQuote::class)->handle(
                new PublishQuoteData($created->id, 0, 'invalid-discount-publication'),
            );
            $this->fail('An invalid discount unexpectedly allocated a quote number.');
        } catch (\LogicException $exception) {
            $this->assertSame('Quote draft discount type is invalid.', $exception->getMessage());
        }

        $this->assertNull(DB::table('finance_quote_series')->where('document_series_id', $seriesId)->value('number'));
        $this->assertSame(0, DB::table('finance_quote_number_sequences')->count());
        $this->assertSame(0, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());
    }

    public function test_revision_creation_failure_keeps_the_number_and_retry_creates_one_revision(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-before-revision-failure',
            $this->draft(),
        );
        $seriesId = $this->seriesId($created);
        $failure = new class
        {
            public bool $enabled = true;
        };
        DocumentRevisionRecord::creating(static function () use ($failure): void {
            if ($failure->enabled) {
                throw new RuntimeException('injected revision creation failure');
            }
        });
        $command = $this->app->make(PublishQuote::class);
        $data = new PublishQuoteData($created->id, 0, 'retry-revision-create');

        try {
            $command->handle($data);
            $this->fail('Injected revision creation failure was not observed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('injected revision creation failure', $exception->getMessage());
        }
        $number = DB::table('finance_quote_series')->where('document_series_id', $seriesId)->value('number');
        $this->assertIsString($number);
        $this->assertSame(0, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());

        $failure->enabled = false;
        $published = $command->handle($data);

        $this->assertSame($number, $published->number);
        $this->assertSame(1, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());
        $this->assertSame(1, $this->renderer->calls);
        $this->assertSame(1, $this->storage->puts);
    }

    public function test_revision_checkpoint_failure_rediscovers_the_same_revision_on_retry(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-before-checkpoint-failure',
            $this->draft(),
        );
        $seriesId = $this->seriesId($created);
        $failure = new class
        {
            public bool $enabled = true;
        };
        QuoteOperationRecord::updating(static function (QuoteOperationRecord $operation) use ($failure): void {
            $result = $operation->getAttribute('result');
            if ($failure->enabled
                && $operation->state === 'running'
                && is_array($result)
                && isset($result['revision_id'])) {
                throw new RuntimeException('injected revision checkpoint failure');
            }
        });
        $command = $this->app->make(PublishQuote::class);
        $data = new PublishQuoteData($created->id, 0, 'retry-revision-checkpoint');

        try {
            $command->handle($data);
            $this->fail('Injected revision checkpoint failure was not observed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('injected revision checkpoint failure', $exception->getMessage());
        }

        $revisionId = (int) DB::table('finance_document_revisions')
            ->where('document_series_id', $seriesId)
            ->sole()
            ->id;
        $operation = DB::table('finance_quote_operations')
            ->where('document_series_id', $seriesId)
            ->where('operation', 'publish')
            ->sole();
        $this->assertSame('reserved', $operation->state);
        $this->assertNull($operation->result);
        $this->assertSame(0, $this->renderer->calls);

        $failure->enabled = false;
        $published = $command->handle($data);

        $this->assertSame($revisionId, $published->currentRevision?->id);
        $this->assertSame(1, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());
        $this->assertSame(1, $this->renderer->calls);
        $this->assertSame(1, $this->storage->puts);
    }

    public function test_renderer_failure_reuses_the_checkpointed_number_and_revision_on_retry(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-before-renderer-failure',
            $this->draft(),
        );
        $seriesId = $this->seriesId($created);
        $this->renderer->failure = new RuntimeException('injected renderer failure');
        $command = $this->app->make(PublishQuote::class);
        $data = new PublishQuoteData($created->id, 0, 'retry-renderer');

        try {
            $command->handle($data);
            $this->fail('Injected renderer failure was not observed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('injected renderer failure', $exception->getMessage());
        }

        $draftRevisionId = (int) DB::table('finance_document_revisions')
            ->where('document_series_id', $seriesId)
            ->sole()
            ->id;
        $this->assertSame('draft', DB::table('finance_document_revisions')->where('id', $draftRevisionId)->value('status'));
        $this->assertNull(DB::table('finance_quote_series')->where('document_series_id', $seriesId)->value('current_revision_id'));
        $this->assertSame(1, DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->count());

        $this->renderer->failure = null;
        $published = $command->handle($data);

        $this->assertSame($draftRevisionId, $published->currentRevision?->id);
        $this->assertSame(1, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());
        $this->assertSame(2, $this->renderer->calls);
        $this->assertSame(1, $this->storage->puts);
    }

    public function test_storage_failure_cleans_partial_bytes_and_reuses_the_number_and_revision_on_retry(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-before-storage-failure',
            $this->draft(),
        );
        $seriesId = $this->seriesId($created);
        $this->storage->failureAfterWrite = new RuntimeException('injected storage failure');
        $command = $this->app->make(PublishQuote::class);
        $data = new PublishQuoteData($created->id, 0, 'retry-storage');

        try {
            $command->handle($data);
            $this->fail('Injected storage failure was not observed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('injected storage failure', $exception->getMessage());
        }

        $revisionId = (int) DB::table('finance_document_revisions')
            ->where('document_series_id', $seriesId)
            ->sole()
            ->id;
        $number = DB::table('finance_quote_series')->where('document_series_id', $seriesId)->value('number');
        $this->assertIsString($number);
        $this->assertSame([], $this->storage->documents);
        $this->assertSame(1, $this->storage->deletes);

        $this->storage->failureAfterWrite = null;
        $published = $command->handle($data);

        $this->assertSame($number, $published->number);
        $this->assertSame($revisionId, $published->currentRevision?->id);
        $this->assertSame(1, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());
        $this->assertSame(2, $this->renderer->calls);
        $this->assertSame(2, $this->storage->puts);
    }

    public function test_final_aggregate_failure_retries_without_another_revision_render_or_pdf(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-before-finalize-failure',
            $this->draft(),
        );
        $seriesId = $this->seriesId($created);
        $failure = new class
        {
            public bool $enabled = true;
        };
        DocumentActivityRecord::creating(static function (DocumentActivityRecord $activity) use ($failure): void {
            if ($failure->enabled && $activity->type === 'quote.published') {
                throw new RuntimeException('injected quote finalization failure');
            }
        });
        $command = $this->app->make(PublishQuote::class);
        $data = new PublishQuoteData($created->id, 0, 'retry-finalization');

        try {
            $command->handle($data);
            $this->fail('Injected quote finalization failure was not observed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('injected quote finalization failure', $exception->getMessage());
        }
        $publishedRevisionId = (int) DB::table('finance_document_revisions')
            ->where('document_series_id', $seriesId)
            ->sole()
            ->id;
        $this->assertSame('published', DB::table('finance_document_revisions')->where('id', $publishedRevisionId)->value('status'));
        $this->assertNull(DB::table('finance_quote_series')->where('document_series_id', $seriesId)->value('current_revision_id'));
        $this->assertSame(1, DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->count());
        $this->assertSame(1, $this->renderer->calls);
        $this->assertSame(1, $this->storage->puts);
        $checkpoint = DB::table('finance_quote_operations')
            ->where('document_series_id', $seriesId)
            ->where('operation', 'publish')
            ->sole();
        $checkpointResult = json_decode((string) $checkpoint->result, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('running', $checkpoint->state);
        $this->assertSame($publishedRevisionId, $checkpointResult['revision_id']);

        $failure->enabled = false;
        $published = $command->handle($data);

        $this->assertSame($publishedRevisionId, $published->currentRevision?->id);
        $this->assertSame(1, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());
        $this->assertSame(1, $this->renderer->calls);
        $this->assertSame(1, $this->storage->puts);
        $this->assertSame(1, DB::table('finance_document_activities')
            ->where('document_series_id', $seriesId)
            ->where('type', 'quote.published')
            ->count());
        $this->assertSame('succeeded', DB::table('finance_quote_operations')
            ->where('document_series_id', $seriesId)
            ->where('operation', 'publish')
            ->value('state'));
    }

    public function test_later_version_keeps_the_base_number_and_supersedes_without_mutating_old_revision(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-versioned-quote',
            $this->draft(),
        );
        $publish = $this->app->make(PublishQuote::class);
        $initialData = new PublishQuoteData(
            $created->id,
            0,
            'publish-version-one',
            'Initial publication',
        );
        $initial = $publish->handle($initialData);
        $firstRevisionId = $initial->currentRevision?->id;
        $this->assertIsInt($firstRevisionId);
        $firstBefore = (array) DB::table('finance_document_revisions')->where('id', $firstRevisionId)->sole();

        $started = $this->app->make(StartQuoteVersion::class)->handle($initial->id, 1);
        $updated = $this->app->make(UpdateQuoteDraft::class)->handle(
            $started->id,
            2,
            $this->draft(title: 'Network refresh revised'),
        );
        $second = $publish->handle(new PublishQuoteData(
            $updated->id,
            3,
            'publish-version-two',
            'Scope adjusted',
        ));

        $this->assertSame($initial->number, $second->number);
        $this->assertSame(4, $second->version);
        $this->assertNull($second->draft);
        $this->assertSame(2, $second->currentRevision?->revisionNumber);
        $this->assertSame($firstRevisionId, $second->currentRevision?->previousRevisionId);
        $secondSnapshot = $second->currentRevision?->snapshot;
        $this->assertIsArray($secondSnapshot);
        $this->assertSame($initial->number.'-R2', $secondSnapshot['revision_label']);
        $this->assertSame('Network refresh revised', $secondSnapshot['title']);
        $this->assertSame($firstBefore, (array) DB::table('finance_document_revisions')->where('id', $firstRevisionId)->sole());
        $superseded = DB::table('finance_document_activities')
            ->where('document_series_id', $this->seriesId($second))
            ->where('type', 'quote.revision.superseded')
            ->sole();
        $this->assertSame([
            'version' => 4,
            'previous_revision_id' => $firstRevisionId,
            'current_revision_id' => $second->currentRevision?->id,
        ], json_decode((string) $superseded->payload, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(2, $this->renderer->calls);
        $this->assertSame(2, $this->storage->puts);
        $this->assertSame(2, DB::table('finance_document_revisions')
            ->where('document_series_id', $this->seriesId($second))
            ->count());

        $oldOperationReplay = $publish->handle($initialData);
        $this->assertSame($second->currentRevision?->id, $oldOperationReplay->currentRevision?->id);
        $this->assertSame(2, $this->renderer->calls);
        $this->assertSame(2, $this->storage->puts);
    }

    public function test_start_version_refuses_a_second_pending_draft_and_terminal_series(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$published] = $this->publishedQuote($owner);
        $command = $this->app->make(StartQuoteVersion::class);
        $started = $command->handle($published->id, 0);

        try {
            $command->handle($started->id, 1);
            $this->fail('A second pending quote draft was opened.');
        } catch (InvalidQuoteAction $exception) {
            $this->assertSame('quote_draft_pending', $exception->errorCode);
        }

        DB::table('finance_quote_drafts')->where('document_series_id', $this->seriesId($started))->delete();
        DB::table('finance_document_series')->where('id', $this->seriesId($started))->update(['status' => 'accepted']);

        try {
            $command->handle($started->id, 1);
            $this->fail('A terminal quote series opened a later version.');
        } catch (InvalidQuoteAction $exception) {
            $this->assertSame('quote_version_not_allowed', $exception->errorCode);
        }
    }

    public function test_start_version_requires_an_immutable_published_current_revision(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$quote, $revisionId] = $this->publishedQuote($owner);
        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'status' => 'draft',
            'pdf_path' => null,
            'pdf_sha256' => null,
            'published_at' => null,
        ]);

        try {
            $this->app->make(StartQuoteVersion::class)->handle($quote->id, 0);
            $this->fail('A mutable current revision was copied into a later version.');
        } catch (InvalidQuoteAction $exception) {
            $this->assertSame('quote_version_not_allowed', $exception->errorCode);
        }

        $this->assertSame(0, DB::table('finance_quote_drafts')
            ->where('document_series_id', $this->seriesId($quote))
            ->count());
    }

    public function test_publish_later_version_rejects_a_mutable_current_revision(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$quote, $revisionId, $snapshot] = $this->publishedQuote($owner);
        $seriesId = $this->seriesId($quote);
        DB::table('finance_document_revisions')->where('id', $revisionId)->update([
            'status' => 'draft',
            'pdf_path' => null,
            'pdf_sha256' => null,
            'published_at' => null,
        ]);
        DB::table('finance_quote_drafts')->insert([
            'document_series_id' => $seriesId,
            'user_id' => $owner->id,
            'based_on_revision_id' => $revisionId,
            'payload' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'net_minor' => $quote->netMinor,
            'vat_minor' => $quote->vatMinor,
            'gross_minor' => $quote->grossMinor,
            'currency' => $quote->currency,
            'updated_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->app->make(PublishQuote::class)->handle(
                new PublishQuoteData($quote->id, 0, 'publish-from-mutable-current'),
            );
            $this->fail('A later version superseded a mutable current revision.');
        } catch (InvalidQuoteAction $exception) {
            $this->assertSame('quote_publish_not_allowed', $exception->errorCode);
        }

        $this->assertSame(1, DB::table('finance_document_revisions')
            ->where('document_series_id', $seriesId)
            ->count());
    }

    /** @return array{QuoteView, int, array<string, mixed>} */
    private function publishedQuote(User $owner): array
    {
        $created = $this->app->make(CreateQuote::class)->handle(
            (int) $owner->id,
            'create-published-quote-'.(string) $owner->id,
            $this->draft(),
        );
        $seriesId = $this->seriesId($created);
        $snapshot = $created->draft;
        $this->assertIsArray($snapshot);
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'previous_revision_id' => null,
            'status' => 'published',
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'net_minor' => $created->netMinor,
            'vat_minor' => $created->vatMinor,
            'gross_minor' => $created->grossMinor,
            'currency' => $created->currency,
            'change_reason' => 'Initial',
            'pdf_path' => 'finance/quotes/original.pdf',
            'pdf_sha256' => hash('sha256', 'original-pdf'),
            'published_at' => now(),
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);
        DB::table('finance_quote_drafts')->where('document_series_id', $seriesId)->delete();
        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update([
            'current_revision_id' => $revisionId,
            'number' => 'AN-2026-0001',
            'sequence_year' => 2026,
            'sequence_number' => 1,
            'published_at' => now(),
        ]);
        DB::table('finance_document_series')->where('id', $seriesId)->update(['status' => 'sent']);

        return [$this->app->make(QuoteRepository::class)->get($created->id), $revisionId, $snapshot];
    }

    private function draft(string $title = 'Network refresh'): QuoteDraftData
    {
        return new QuoteDraftData(
            title: $title,
            partnerId: null,
            customer: ['name' => 'Ada GmbH', 'email' => 'billing@example.com'],
            issueDate: '2026-08-28',
            validUntil: '2026-09-27',
            currency: 'EUR',
            lines: [new QuoteLineData(
                'Consulting',
                '2.5000',
                'hour',
                '100.00',
                '19.00',
                'service',
                null,
            )],
            discountType: 'percent',
            discountValue: '10.00',
            introText: 'Intro',
            outroText: 'Outro',
            internalNote: 'Internal only',
        );
    }

    private function seriesId(QuoteView $quote): int
    {
        return (int) DB::table('finance_document_series')
            ->where('user_id', $quote->id->ownerId)
            ->where('uuid', $quote->id->uuid)
            ->value('id');
    }
}

final class QuotePublicationRenderer implements DocumentRenderer
{
    public int $calls = 0;

    public ?RuntimeException $failure = null;

    /** @var list<array<array-key, mixed>> */
    public array $snapshots = [];

    public function render(array $snapshot): string
    {
        $this->calls++;
        $this->snapshots[] = $snapshot;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return '%PDF-quote-'.$this->calls;
    }
}

final class QuotePublicationStorage implements DocumentStorage
{
    public int $puts = 0;

    public int $deletes = 0;

    public ?RuntimeException $failureAfterWrite = null;

    /** @var array<string, string> */
    public array $documents = [];

    /** @var array<string, string> */
    private array $tokens = [];

    public function putPdf(string $seriesUuid, string $bytes, string $ownershipToken): StoredDocument
    {
        $this->puts++;
        $path = "finance/revisions/{$seriesUuid}/{$this->puts}.pdf";
        $this->documents[$path] = $bytes;
        $this->tokens[$ownershipToken] = $path;

        if ($this->failureAfterWrite !== null) {
            throw $this->failureAfterWrite;
        }

        return new StoredDocument($path, hash('sha256', $bytes));
    }

    public function delete(string $ownershipToken): void
    {
        $path = $this->tokens[$ownershipToken] ?? null;
        if ($path === null) {
            return;
        }

        $this->deletes++;
        unset($this->documents[$path], $this->tokens[$ownershipToken]);
    }
}
