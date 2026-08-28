<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Modules\Finance\Application\Commands\CreateDocumentRevision;
use App\Modules\Finance\Application\Commands\PublishDocumentRevision;
use App\Modules\Finance\Application\DTOs\CreateRevisionData;
use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentRevisionRepository;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Persistence\EloquentDocumentRevisionRepository;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Stringable;
use Tests\TestCase;

final class DocumentRevisionApplicationTest extends TestCase
{
    use RefreshDatabase;

    private FakeDocumentRenderer $renderer;

    private FakeDocumentStorage $storage;

    private FakeLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new FakeDocumentRenderer;
        $this->storage = new FakeDocumentStorage;
        $this->logger = new FakeLogger;
        $this->app->instance(DocumentRenderer::class, $this->renderer);
        $this->app->instance(DocumentStorage::class, $this->storage);
        $this->app->instance(LoggerInterface::class, $this->logger);
    }

    public function test_the_repository_port_is_bound_to_the_eloquent_adapter(): void
    {
        $this->assertInstanceOf(
            EloquentDocumentRevisionRepository::class,
            $this->app->make(DocumentRevisionRepository::class),
        );
    }

    public function test_revision_ids_must_be_positive_integers(): void
    {
        $this->assertSame(1, (new DocumentRevisionId(1))->value);

        $this->expectException(InvalidArgumentException::class);

        new DocumentRevisionId(0);
    }

    public function test_stored_documents_reject_unsafe_paths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StoredDocument('../outside.pdf', str_repeat('a', 64));
    }

    public function test_stored_documents_require_a_sha256_digest(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StoredDocument('finance/revisions/safe.pdf', 'not-a-sha256');
    }

    public function test_revisions_start_at_one_and_reference_the_immediately_previous_revision(): void
    {
        $series = $this->ownedSeries('018f4ca3-224d-7d8d-9f00-111111111111');
        $command = $this->app->make(CreateDocumentRevision::class);

        $firstId = $command->handle($this->revisionData($series->uuid, ['version' => 1]));
        $secondId = $command->handle($this->revisionData($series->uuid, ['version' => 2]));

        $first = DocumentRevisionRecord::query()->findOrFail($firstId->value);
        $second = DocumentRevisionRecord::query()->findOrFail($secondId->value);

        $this->assertSame(1, $first->revision_number);
        $this->assertNull($first->previous_revision_id);
        $this->assertSame(2, $second->revision_number);
        $this->assertSame($first->id, $second->previous_revision_id);
    }

    public function test_control_totals_are_calculated_server_side(): void
    {
        $series = $this->ownedSeries('018f4ca3-224d-7d8d-9f00-222222222222');
        $data = new CreateRevisionData(
            seriesUuid: $series->uuid,
            snapshot: [
                'client_totals' => ['net_minor' => 1, 'vat_minor' => 2, 'gross_minor' => 3],
            ],
            lines: [
                new DocumentLine(
                    'Consulting',
                    DecimalQuantity::fromString('2.5'),
                    Money::fromDecimal('100.00', 'EUR'),
                    1900,
                ),
            ],
            discount: Discount::percentBasisPoints(1000, 'EUR'),
            changeReason: 'Initial revision',
        );

        $id = $this->app->make(CreateDocumentRevision::class)->handle($data);
        $revision = DocumentRevisionRecord::query()->findOrFail($id->value);

        $this->assertSame(22_500, $revision->net_minor);
        $this->assertSame(4_275, $revision->vat_minor);
        $this->assertSame(26_775, $revision->gross_minor);
        $this->assertSame('EUR', $revision->currency);
    }

    public function test_snapshot_lines_and_totals_are_derived_from_domain_values(): void
    {
        $series = $this->ownedSeries('018f4ca3-224d-7d8d-9f00-232323232323');
        $data = new CreateRevisionData(
            seriesUuid: $series->uuid,
            snapshot: [
                'metadata' => ['reference' => 'AUTHORITATIVE'],
                'lines' => [['description' => 'Forged', 'unit_price' => 0.01]],
                'totals' => ['gross' => 1.25],
            ],
            lines: [
                new DocumentLine(
                    'Consulting',
                    DecimalQuantity::fromString('1.5'),
                    Money::fromMinor(10_000, 'EUR'),
                    1900,
                ),
                new DocumentLine(
                    'Hardware',
                    DecimalQuantity::fromString('2'),
                    Money::fromMinor(5_000, 'EUR'),
                    700,
                ),
            ],
            discount: Discount::fixed(Money::fromMinor(1_000, 'EUR')),
            changeReason: 'Authoritative snapshot',
        );

        $id = $this->app->make(CreateDocumentRevision::class)->handle($data);
        $revision = DocumentRevisionRecord::query()->findOrFail($id->value);
        $snapshot = $revision->getAttribute('snapshot');
        $this->assertIsArray($snapshot);

        $this->assertSame([
            [
                'currency' => 'EUR',
                'description' => 'Consulting',
                'quantity_scaled' => 15_000,
                'tax_rate_basis_points' => 1900,
                'unit_price_minor' => 10_000,
            ],
            [
                'currency' => 'EUR',
                'description' => 'Hardware',
                'quantity_scaled' => 20_000,
                'tax_rate_basis_points' => 700,
                'unit_price_minor' => 5_000,
            ],
        ], $snapshot['lines']);
        $this->assertSame([
            'currency' => 'EUR',
            'discount_minor' => 1_000,
            'gross_minor' => 27_408,
            'net_minor' => 24_000,
            'tax_breakdowns' => [
                [
                    'gross_minor' => 10_272,
                    'net_minor' => 9_600,
                    'tax_rate_basis_points' => 700,
                    'vat_minor' => 672,
                ],
                [
                    'gross_minor' => 17_136,
                    'net_minor' => 14_400,
                    'tax_rate_basis_points' => 1900,
                    'vat_minor' => 2_736,
                ],
            ],
            'vat_minor' => 3_408,
        ], $snapshot['totals']);
        $this->assertSame(['reference' => 'AUTHORITATIVE'], $snapshot['metadata']);
        $storedSnapshot = DB::table('finance_document_revisions')
            ->where('id', $id->value)
            ->value('snapshot');
        $this->assertIsString($storedSnapshot);
        $this->assertStringNotContainsString('1.25', $storedSnapshot);
    }

    public function test_snapshot_metadata_rejects_floating_point_values(): void
    {
        $series = $this->ownedSeries('018f4ca3-224d-7d8d-9f00-242424242424');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document snapshots cannot contain floating-point values.');

        $this->app->make(CreateDocumentRevision::class)->handle($this->revisionData(
            $series->uuid,
            ['metadata' => ['amount' => 12.34]],
        ));
    }

    public function test_snapshot_metadata_rejects_non_array_json_objects(): void
    {
        $series = $this->ownedSeries('018f4ca3-224d-7d8d-9f00-252525252525');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document snapshots may contain only arrays and scalar JSON values.');

        $this->app->make(CreateDocumentRevision::class)->handle($this->revisionData(
            $series->uuid,
            ['metadata' => ['unstable' => (object) ['b' => 2, 'a' => 1]]],
        ));
    }

    public function test_snapshot_keys_are_canonicalized_recursively_before_hashing_and_storage(): void
    {
        $series = $this->ownedSeries('018f4ca3-224d-7d8d-9f00-333333333333');
        $command = $this->app->make(CreateDocumentRevision::class);
        $firstSnapshot = [
            'lines' => [[
                'meta' => ['z' => 2, 'a' => 1],
                'description' => 'Consulting',
            ]],
            'customer' => ['name' => 'Ada', 'city' => 'Berlin'],
        ];
        $sameSnapshotInAnotherOrder = [
            'customer' => ['city' => 'Berlin', 'name' => 'Ada'],
            'lines' => [[
                'description' => 'Consulting',
                'meta' => ['a' => 1, 'z' => 2],
            ]],
        ];

        $firstId = $command->handle($this->revisionData($series->uuid, $firstSnapshot));
        $secondId = $command->handle($this->revisionData($series->uuid, $sameSnapshotInAnotherOrder));

        $storedSnapshots = DB::table('finance_document_revisions')
            ->whereIn('id', [$firstId->value, $secondId->value])
            ->orderBy('id')
            ->pluck('snapshot')
            ->all();
        $activityHashes = DB::table('finance_document_activities')
            ->where('type', 'revision.created')
            ->orderBy('id')
            ->pluck('payload')
            ->map(static function (mixed $payload): string {
                if (! is_string($payload)) {
                    throw new RuntimeException('Stored activity payload is not JSON.');
                }

                $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
                $sha256 = is_array($decoded) ? ($decoded['snapshot_sha256'] ?? null) : null;

                if (! is_string($sha256)) {
                    throw new RuntimeException('Stored activity payload has no snapshot hash.');
                }

                return $sha256;
            })
            ->all();

        $expectedJson = '{"customer":{"city":"Berlin","name":"Ada"},"lines":[{"currency":"EUR","description":"Consulting","quantity_scaled":10000,"tax_rate_basis_points":1900,"unit_price_minor":10000}],"totals":{"currency":"EUR","discount_minor":0,"gross_minor":11900,"net_minor":10000,"tax_breakdowns":[{"gross_minor":11900,"net_minor":10000,"tax_rate_basis_points":1900,"vat_minor":1900}],"vat_minor":1900}}';
        $expectedHash = 'c51a09b18defb4350f736aff2951961f9a92fa7def0839e3f7c670d60fce3fd7';

        $this->assertSame([$expectedJson, $expectedJson], $storedSnapshots);
        $this->assertSame($expectedHash, hash('sha256', $storedSnapshots[0]));
        $this->assertSame([$expectedHash, $expectedHash], $activityHashes);
    }

    public function test_another_owners_series_is_rejected(): void
    {
        $series = $this->ownedSeries('018f4ca3-224d-7d8d-9f00-444444444444');
        $this->actingAs(User::factory()->create());

        $this->expectException(ModelNotFoundException::class);

        $this->app->make(CreateDocumentRevision::class)
            ->handle($this->revisionData($series->uuid, ['version' => 1]));
    }

    public function test_a_forced_unique_sequence_collision_is_retried_without_leaving_a_duplicate_number(): void
    {
        $series = $this->ownedSeries('018f4ca3-224d-7d8d-9f00-555555555555');
        $ownerId = (int) auth()->id();
        $injectCollision = true;

        DocumentRevisionRecord::creating(function (DocumentRevisionRecord $revision) use (&$injectCollision, $ownerId, $series): void {
            if (! $injectCollision) {
                return;
            }

            $injectCollision = false;
            DB::table('finance_document_revisions')->insert([
                'user_id' => $ownerId,
                'document_series_id' => $series->id,
                'revision_number' => $revision->revision_number,
                'previous_revision_id' => null,
                'status' => 'draft',
                'snapshot' => '{}',
                'net_minor' => 0,
                'vat_minor' => 0,
                'gross_minor' => 0,
                'currency' => 'EUR',
                'change_reason' => 'Injected collision',
                'created_by' => $ownerId,
                'created_at' => now(),
            ]);
        });

        $id = $this->app->make(CreateDocumentRevision::class)
            ->handle($this->revisionData($series->uuid, ['version' => 1]));

        $this->assertSame(1, DocumentRevisionRecord::query()->count());
        $this->assertSame(1, DocumentRevisionRecord::query()->findOrFail($id->value)->revision_number);
        $this->assertSame(
            [1],
            DocumentRevisionRecord::query()->orderBy('revision_number')->pluck('revision_number')->all(),
        );
    }

    public function test_publication_resolves_the_series_before_locking_the_revision(): void
    {
        [, $revisionId] = $this->createdRevision('018f4ca3-224d-7d8d-9f00-565656565656');
        $aggregateReads = [];
        DB::listen(static function (QueryExecuted $query) use (&$aggregateReads): void {
            $sql = strtolower($query->sql);

            if (str_starts_with($sql, 'select')
                && (str_contains($sql, 'finance_document_series')
                    || str_contains($sql, 'finance_document_revisions'))) {
                $aggregateReads[] = $sql;
            }
        });

        $this->app->make(PublishDocumentRevision::class)->handle($revisionId);

        $this->assertGreaterThanOrEqual(3, count($aggregateReads));
        $this->assertStringContainsString('select "document_series_id"', $aggregateReads[0]);
        $this->assertStringContainsString('from "finance_document_series"', $aggregateReads[1]);
        $this->assertStringContainsString('from "finance_document_revisions"', $aggregateReads[2]);
        $this->assertStringContainsString('"document_series_id"', $aggregateReads[2]);
    }

    public function test_publication_stores_a_safe_server_path_byte_hash_timestamp_and_activity_atomically(): void
    {
        [$series, $revisionId] = $this->createdRevision('018f4ca3-224d-7d8d-9f00-666666666666');

        $published = $this->app->make(PublishDocumentRevision::class)->handle($revisionId);
        $revision = DocumentRevisionRecord::query()->findOrFail($revisionId->value);
        $activity = DocumentActivityRecord::query()
            ->where('document_revision_id', $revisionId->value)
            ->where('type', 'revision.published')
            ->firstOrFail();
        $expectedHash = hash('sha256', '%PDF-test');

        $this->assertSame($revisionId->value, $published->revisionId->value);
        $this->assertSame(1, $published->revisionNumber);
        $this->assertMatchesRegularExpression(
            '#\Afinance/revisions/'.preg_quote($series->uuid, '#').'/[a-f0-9-]+\.pdf\z#',
            $published->path,
        );
        $this->assertStringNotContainsString('..', $published->path);
        $this->assertStringNotContainsString('\\', $published->path);
        $this->assertSame($expectedHash, $published->sha256);
        $this->assertSame('published', $revision->status);
        $this->assertSame($published->path, $revision->pdf_path);
        $this->assertSame($expectedHash, $revision->pdf_sha256);
        $persistedPublishedAt = $revision->getAttribute('published_at');
        $this->assertInstanceOf(\DateTimeInterface::class, $persistedPublishedAt);
        $this->assertSame(
            $published->publishedAt->format('Y-m-d H:i:s'),
            $persistedPublishedAt->format('Y-m-d H:i:s'),
        );
        $this->assertSame([
            'path' => $published->path,
            'pdf_sha256' => $expectedHash,
        ], $activity->payload);
        $this->assertSame([$revision->snapshot], $this->renderer->snapshots);
        $this->assertSame('%PDF-test', $this->storage->documents[$published->path]);
    }

    public function test_publication_retry_returns_the_same_result_without_rendering_or_storing_twice(): void
    {
        [, $revisionId] = $this->createdRevision('018f4ca3-224d-7d8d-9f00-777777777777');
        $command = $this->app->make(PublishDocumentRevision::class);

        $first = $command->handle($revisionId);
        $retry = $command->handle($revisionId);

        $this->assertEquals($first, $retry);
        $this->assertSame(1, $this->renderer->calls);
        $this->assertSame(1, $this->storage->puts);
        $this->assertSame(1, DocumentActivityRecord::query()->where('type', 'revision.published')->count());
    }

    public function test_renderer_failure_leaves_the_revision_unpublished_without_false_success_activity(): void
    {
        [, $revisionId] = $this->createdRevision('018f4ca3-224d-7d8d-9f00-888888888888');
        $this->renderer->failure = new RuntimeException('Renderer failed.');

        try {
            $this->app->make(PublishDocumentRevision::class)->handle($revisionId);
            $this->fail('Publication unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Renderer failed.', $exception->getMessage());
        }

        $revision = DocumentRevisionRecord::query()->findOrFail($revisionId->value);
        $this->assertSame('draft', $revision->status);
        $this->assertNull($revision->pdf_path);
        $this->assertNull($revision->pdf_sha256);
        $this->assertNull($revision->published_at);
        $this->assertSame(0, $this->storage->puts);
        $this->assertSame(0, DocumentActivityRecord::query()->where('type', 'revision.published')->count());
    }

    public function test_publication_rejects_a_storage_hash_that_does_not_match_the_rendered_bytes(): void
    {
        [, $revisionId] = $this->createdRevision('018f4ca3-224d-7d8d-9f00-889999999999');
        $this->storage->reportedSha256 = str_repeat('b', 64);

        try {
            $this->app->make(PublishDocumentRevision::class)->handle($revisionId);
            $this->fail('Publication unexpectedly trusted an incorrect storage hash.');
        } catch (LogicException $exception) {
            $this->assertSame('Stored PDF hash does not match the rendered bytes.', $exception->getMessage());
        }

        $revision = DocumentRevisionRecord::query()->findOrFail($revisionId->value);
        $this->assertSame('draft', $revision->status);
        $this->assertNull($revision->published_at);
        $this->assertSame(1, $this->storage->puts);
        $this->assertCount(1, $this->storage->deleted);
        $this->assertSame([], $this->storage->documents);
        $this->assertSame(0, DocumentActivityRecord::query()->where('type', 'revision.published')->count());
    }

    public function test_a_non_object_json_snapshot_is_rejected_before_rendering(): void
    {
        [, $revisionId] = $this->createdRevision('018f4ca3-224d-7d8d-9f00-898989898989');
        DB::table('finance_document_revisions')
            ->where('id', $revisionId->value)
            ->update(['snapshot' => '"invalid"']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A document revision snapshot must be a JSON object or array.');

        try {
            $this->app->make(PublishDocumentRevision::class)->handle($revisionId);
        } finally {
            $this->assertSame(0, $this->renderer->calls);
        }
    }

    public function test_a_database_failure_after_storage_deletes_the_new_pdf_and_rolls_back_publication(): void
    {
        [, $revisionId] = $this->createdRevision('018f4ca3-224d-7d8d-9f00-999999999999');

        DocumentActivityRecord::creating(function (DocumentActivityRecord $activity): void {
            if ($activity->type === 'revision.published') {
                throw new RuntimeException('Activity insert failed.');
            }
        });

        try {
            $this->app->make(PublishDocumentRevision::class)->handle($revisionId);
            $this->fail('Publication unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Activity insert failed.', $exception->getMessage());
        }

        $revision = DocumentRevisionRecord::query()->findOrFail($revisionId->value);
        $this->assertSame('draft', $revision->status);
        $this->assertNull($revision->published_at);
        $this->assertSame(1, $this->storage->puts);
        $this->assertCount(1, $this->storage->deleted);
        $this->assertSame([], $this->storage->documents);
        $this->assertSame(0, DocumentActivityRecord::query()->where('type', 'revision.published')->count());
    }

    public function test_cleanup_cannot_delete_a_foreign_object_that_reuses_the_same_path(): void
    {
        [, $revisionId] = $this->createdRevision('018f4ca3-224d-7d8d-9f00-989898989898');

        DocumentActivityRecord::creating(function (DocumentActivityRecord $activity): void {
            if ($activity->type !== 'revision.published') {
                return;
            }

            $path = array_key_first($this->storage->documents);

            if (! is_string($path)) {
                throw new RuntimeException('The PDF was not written before the activity.');
            }

            $this->storage->replaceWithForeignObject($path, '%PDF-foreign');

            throw new RuntimeException('Activity insert failed after path reuse.');
        });

        try {
            $this->app->make(PublishDocumentRevision::class)->handle($revisionId);
            $this->fail('Publication unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Activity insert failed after path reuse.', $exception->getMessage());
        }

        $this->assertSame(['%PDF-foreign'], array_values($this->storage->documents));
    }

    public function test_a_storage_write_that_throws_after_persisting_is_cleaned_up_by_ownership_token(): void
    {
        [, $revisionId] = $this->createdRevision('018f4ca3-224d-7d8d-9f00-979797979797');
        $this->storage->putFailureAfterWrite = new RuntimeException('Storage acknowledgement failed.');

        try {
            $this->app->make(PublishDocumentRevision::class)->handle($revisionId);
            $this->fail('Publication unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Storage acknowledgement failed.', $exception->getMessage());
        }

        $revision = DocumentRevisionRecord::query()->findOrFail($revisionId->value);
        $this->assertSame('draft', $revision->status);
        $this->assertNull($revision->published_at);
        $this->assertSame(1, $this->storage->puts);
        $this->assertCount(1, $this->storage->deleted);
        $this->assertSame([], $this->storage->documents);
    }

    public function test_cleanup_failure_is_logged_without_hiding_the_primary_failure(): void
    {
        [, $revisionId] = $this->createdRevision('018f4ca3-224d-7d8d-9f00-969696969696');
        $primaryFailure = new RuntimeException('Storage acknowledgement failed.');
        $cleanupFailure = new RuntimeException('Storage cleanup failed.');
        $this->storage->putFailureAfterWrite = $primaryFailure;
        $this->storage->deleteFailure = $cleanupFailure;

        try {
            $this->app->make(PublishDocumentRevision::class)->handle($revisionId);
            $this->fail('Publication unexpectedly succeeded.');
        } catch (RuntimeException $caught) {
            $this->assertSame($primaryFailure, $caught);
        }

        $this->assertCount(1, $this->logger->records);
        $this->assertSame('error', $this->logger->records[0]['level']);
        $this->assertSame('Document PDF cleanup failed after publication error.', $this->logger->records[0]['message']);
        $this->assertSame($cleanupFailure, $this->logger->records[0]['context']['exception']);
        $this->assertSame($primaryFailure, $this->logger->records[0]['context']['primary_exception']);
    }

    private function ownedSeries(string $uuid): DocumentSeriesRecord
    {
        $this->actingAs(User::factory()->create());

        $series = new DocumentSeriesRecord;
        $series->forceFill([
            'uuid' => $uuid,
            'document_type' => 'invoice',
            'status' => 'draft',
            'created_by' => auth()->id(),
        ])->save();

        return $series;
    }

    /** @return array{DocumentSeriesRecord, DocumentRevisionId} */
    private function createdRevision(string $uuid): array
    {
        $series = $this->ownedSeries($uuid);
        $revisionId = $this->app->make(CreateDocumentRevision::class)
            ->handle($this->revisionData($series->uuid, ['version' => 1]));

        return [$series, $revisionId];
    }

    /** @param array<array-key, mixed> $snapshot */
    private function revisionData(string $seriesUuid, array $snapshot): CreateRevisionData
    {
        return new CreateRevisionData(
            seriesUuid: $seriesUuid,
            snapshot: $snapshot,
            lines: [
                new DocumentLine(
                    'Consulting',
                    DecimalQuantity::fromString('1'),
                    Money::fromDecimal('100.00', 'EUR'),
                    1900,
                ),
            ],
            discount: Discount::none('EUR'),
            changeReason: 'Test revision',
        );
    }
}

final class FakeDocumentRenderer implements DocumentRenderer
{
    public int $calls = 0;

    public ?RuntimeException $failure = null;

    /** @var list<array<array-key, mixed>> */
    public array $snapshots = [];

    /** @param array<array-key, mixed> $snapshot */
    public function render(array $snapshot): string
    {
        $this->calls++;
        $this->snapshots[] = $snapshot;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return '%PDF-test';
    }
}

final class FakeDocumentStorage implements DocumentStorage
{
    public int $puts = 0;

    public ?string $reportedSha256 = null;

    public ?RuntimeException $putFailureAfterWrite = null;

    public ?RuntimeException $deleteFailure = null;

    /** @var array<string, string> */
    public array $documents = [];

    /** @var array<string, string> */
    public array $ownershipTokens = [];

    /** @var list<string> */
    public array $deleted = [];

    public function putPdf(string $seriesUuid, string $bytes, string $ownershipToken): StoredDocument
    {
        $this->puts++;
        $sha256 = hash('sha256', $bytes);
        $path = sprintf('finance/revisions/%s/%d-%s.pdf', $seriesUuid, $this->puts, $sha256);
        $this->documents[$path] = $bytes;
        $this->ownershipTokens[$path] = $ownershipToken;

        if ($this->putFailureAfterWrite !== null) {
            throw $this->putFailureAfterWrite;
        }

        return new StoredDocument($path, $this->reportedSha256 ?? $sha256);
    }

    public function delete(string $ownershipToken): void
    {
        if ($this->deleteFailure !== null) {
            throw $this->deleteFailure;
        }

        $path = array_search($ownershipToken, $this->ownershipTokens, true);

        if (! is_string($path)) {
            return;
        }

        $this->deleted[] = $path;
        unset($this->documents[$path]);
        unset($this->ownershipTokens[$path]);
    }

    public function replaceWithForeignObject(string $path, string $bytes): void
    {
        $this->documents[$path] = $bytes;
        $this->ownershipTokens[$path] = 'foreign-object';
    }
}

final class FakeLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    /** @param array<mixed> $context */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
