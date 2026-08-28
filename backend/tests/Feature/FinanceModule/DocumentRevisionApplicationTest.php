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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class DocumentRevisionApplicationTest extends TestCase
{
    use RefreshDatabase;

    private FakeDocumentRenderer $renderer;

    private FakeDocumentStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new FakeDocumentRenderer;
        $this->storage = new FakeDocumentStorage;
        $this->app->instance(DocumentRenderer::class, $this->renderer);
        $this->app->instance(DocumentStorage::class, $this->storage);
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

        $expectedJson = '{"customer":{"city":"Berlin","name":"Ada"},"lines":[{"description":"Consulting","meta":{"a":1,"z":2}}]}';
        $expectedHash = 'ec4993a37dccbc7205f64ab68ae8be5b7be1effdb8c2a2d2c1f5c5b335022fdc';

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

    public function test_a_unique_sequence_race_is_retried_without_creating_a_duplicate_number(): void
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
                'change_reason' => 'Concurrent attempt',
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
        $this->assertSame([['version' => 1]], $this->renderer->snapshots);
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

    /** @var array<string, string> */
    public array $documents = [];

    /** @var list<string> */
    public array $deleted = [];

    public function putPdf(string $seriesUuid, string $bytes): StoredDocument
    {
        $this->puts++;
        $sha256 = hash('sha256', $bytes);
        $path = sprintf('finance/revisions/%s/%d-%s.pdf', $seriesUuid, $this->puts, $sha256);
        $this->documents[$path] = $bytes;

        return new StoredDocument($path, $this->reportedSha256 ?? $sha256);
    }

    public function delete(string $path): void
    {
        $this->deleted[] = $path;
        unset($this->documents[$path]);
    }
}
