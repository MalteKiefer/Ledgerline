<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Modules\Finance\Infrastructure\Persistence\Exception\PublishedRevisionMutation;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentNoteRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DocumentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_queries_only_expose_the_owners_document_aggregate(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        $this->actingAs($owner);
        [$ownedSeries, $ownedRevision, $ownedActivity, $ownedNote] = $this->createAggregate(
            '018f4ca3-224d-7d8d-9f00-111111111111',
        );

        $this->actingAs($otherOwner);
        [$otherSeries, $otherRevision, $otherActivity, $otherNote] = $this->createAggregate(
            '018f4ca3-224d-7d8d-9f00-222222222222',
        );

        $this->assertSame([$otherSeries->id], DocumentSeriesRecord::query()->pluck('id')->all());
        $this->assertSame([$otherRevision->id], DocumentRevisionRecord::query()->pluck('id')->all());
        $this->assertSame([$otherActivity->id], DocumentActivityRecord::query()->pluck('id')->all());
        $this->assertSame([$otherNote->id], DocumentNoteRecord::query()->pluck('id')->all());

        $this->assertNull(DocumentSeriesRecord::query()->find($ownedSeries->id));
        $this->assertNull(DocumentRevisionRecord::query()->find($ownedRevision->id));
        $this->assertNull(DocumentActivityRecord::query()->find($ownedActivity->id));
        $this->assertNull(DocumentNoteRecord::query()->find($ownedNote->id));

        $this->actingAs($owner);

        $this->assertSame([$ownedSeries->id], DocumentSeriesRecord::query()->pluck('id')->all());
        $this->assertSame([$ownedRevision->id], DocumentRevisionRecord::query()->pluck('id')->all());
        $this->assertSame([$ownedActivity->id], DocumentActivityRecord::query()->pluck('id')->all());
        $this->assertSame([$ownedNote->id], DocumentNoteRecord::query()->pluck('id')->all());
    }

    public function test_an_owned_series_never_resolves_child_ids_from_another_owner(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        $this->actingAs($owner);
        [$ownedSeries] = $this->createAggregate('018f4ca3-224d-7d8d-9f00-333333333333');

        $this->actingAs($otherOwner);
        [, $otherRevision, $otherActivity, $otherNote] = $this->createAggregate(
            '018f4ca3-224d-7d8d-9f00-444444444444',
        );

        $this->actingAs($owner);
        $resolvedSeries = DocumentSeriesRecord::query()->findOrFail($ownedSeries->id);

        $this->assertNull($resolvedSeries->revisions()->find($otherRevision->id));
        $this->assertNull($resolvedSeries->activities()->find($otherActivity->id));
        $this->assertNull($resolvedSeries->notes()->find($otherNote->id));
    }

    public function test_force_delete_cannot_cross_the_authenticated_owner_boundary(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        $this->actingAs($otherOwner);
        [$otherSeries, $otherRevision] = $this->createAggregate(
            '018f4ca3-224d-7d8d-9f00-777777777777',
        );
        DB::table('finance_document_activities')->where('document_series_id', $otherSeries->id)->delete();
        DB::table('finance_document_notes')->where('document_series_id', $otherSeries->id)->delete();

        $this->actingAs($owner);

        $this->assertSame(
            0,
            DocumentRevisionRecord::query()->whereKey($otherRevision->id)->forceDelete(),
        );
        $this->assertTrue(
            DB::table('finance_document_revisions')->where('id', $otherRevision->id)->exists(),
        );
    }

    public function test_server_controlled_identity_and_publication_fields_are_not_mass_assignable(): void
    {
        $guardedFields = [
            [new DocumentSeriesRecord, ['user_id', 'uuid', 'source_type', 'source_id']],
            [new DocumentRevisionRecord, [
                'user_id', 'document_series_id', 'revision_number', 'pdf_path', 'pdf_sha256',
                'published_at',
            ]],
            [new DocumentActivityRecord, ['user_id', 'document_series_id']],
            [new DocumentNoteRecord, ['user_id', 'document_series_id']],
        ];

        foreach ($guardedFields as [$record, $fields]) {
            foreach ($fields as $field) {
                $this->assertFalse($record->isFillable($field), sprintf(
                    '%s must not allow mass assignment of %s.',
                    $record::class,
                    $field,
                ));
            }
        }
    }

    public function test_a_draft_revision_can_be_updated(): void
    {
        $revision = $this->createOwnedAggregate()[1];

        $revision->update([
            'snapshot' => ['lines' => [['description' => 'Draft line']]],
            'net_minor' => 2500,
            'vat_minor' => 475,
            'gross_minor' => 2975,
            'change_reason' => 'Draft corrected',
        ]);

        $revision->refresh();

        $this->assertSame(['lines' => [['description' => 'Draft line']]], $revision->snapshot);
        $this->assertSame(2500, $revision->net_minor);
        $this->assertSame(475, $revision->vat_minor);
        $this->assertSame(2975, $revision->gross_minor);
        $this->assertSame('Draft corrected', $revision->change_reason);
    }

    public function test_normal_save_cannot_create_an_already_published_revision(): void
    {
        $series = $this->createOwnedAggregate()[0];
        $revision = $series->revisions()->make([
            'status' => 'published',
            'snapshot' => ['lines' => []],
            'net_minor' => 10000,
            'vat_minor' => 1900,
            'gross_minor' => 11900,
            'currency' => 'EUR',
            'change_reason' => 'Publication bypass',
            'created_by' => auth()->id(),
        ]);
        $revision->forceFill([
            'revision_number' => 2,
            'pdf_path' => 'finance/revisions/create-bypass.pdf',
            'pdf_sha256' => str_repeat('b', 64),
            'published_at' => now(),
        ]);

        $this->expectException(PublishedRevisionMutation::class);

        $revision->save();
    }

    public function test_normal_update_cannot_publish_a_draft_revision(): void
    {
        $revision = $this->createOwnedAggregate()[1];

        try {
            $revision->update(['status' => 'published']);
            $this->fail('A normal update published a draft revision.');
        } catch (PublishedRevisionMutation) {
            $this->addToAssertionCount(1);
        }

        $revision->refresh();
        $this->assertSame('draft', $revision->status);
        $this->assertNull($revision->published_at);
    }

    public function test_normal_save_cannot_set_publication_fields_on_a_draft_revision(): void
    {
        $revision = $this->createOwnedAggregate()[1];
        $revision->forceFill([
            'pdf_path' => 'finance/revisions/bypass.pdf',
            'pdf_sha256' => str_repeat('b', 64),
            'published_at' => now(),
        ]);

        try {
            $revision->save();
            $this->fail('A normal save set publication fields on a draft revision.');
        } catch (PublishedRevisionMutation) {
            $this->addToAssertionCount(1);
        }

        $revision->refresh();
        $this->assertSame('draft', $revision->status);
        $this->assertNull($revision->pdf_path);
        $this->assertNull($revision->pdf_sha256);
        $this->assertNull($revision->published_at);
    }

    public function test_quiet_operations_cannot_publish_a_draft_revision(): void
    {
        $operations = [
            'updateQuietly' => static fn (DocumentRevisionRecord $revision): bool => $revision->updateQuietly([
                'status' => 'published',
            ]),
            'saveQuietly' => static function (DocumentRevisionRecord $revision): bool {
                $revision->forceFill([
                    'status' => 'published',
                    'pdf_path' => 'finance/revisions/quiet-bypass.pdf',
                    'pdf_sha256' => str_repeat('c', 64),
                    'published_at' => now(),
                ]);

                return $revision->saveQuietly();
            },
        ];

        foreach ($operations as $name => $operation) {
            $revision = $this->createOwnedAggregate()[1];

            try {
                $operation($revision);
                $this->fail("{$name} published a draft revision.");
            } catch (PublishedRevisionMutation) {
                $this->addToAssertionCount(1);
            }

            $revision->refresh();
            $this->assertSame('draft', $revision->status);
            $this->assertNull($revision->pdf_path);
            $this->assertNull($revision->published_at);
        }
    }

    public function test_bulk_operations_cannot_publish_a_draft_revision(): void
    {
        $operations = [
            'update' => static fn (DocumentRevisionRecord $revision): int => DocumentRevisionRecord::query()
                ->whereKey($revision->id)
                ->update([
                    'status' => 'published',
                    'pdf_path' => 'finance/revisions/bulk-bypass.pdf',
                    'pdf_sha256' => str_repeat('d', 64),
                    'published_at' => now(),
                ]),
            'increment extra' => static fn (DocumentRevisionRecord $revision): int => DocumentRevisionRecord::query()
                ->whereKey($revision->id)
                ->increment('net_minor', 1, ['status' => 'published']),
            'incrementEach extra' => static fn (DocumentRevisionRecord $revision): int => DocumentRevisionRecord::query()
                ->whereKey($revision->id)
                ->incrementEach(['net_minor' => 1], ['published_at' => now()]),
            'updateFrom' => static fn (DocumentRevisionRecord $revision): int => DocumentRevisionRecord::query()
                ->whereKey($revision->id)
                ->updateFrom(['status' => 'published']),
        ];

        foreach ($operations as $name => $operation) {
            $revision = $this->createOwnedAggregate()[1];

            try {
                $operation($revision);
                $this->fail("{$name} published a draft revision.");
            } catch (PublishedRevisionMutation) {
                $this->addToAssertionCount(1);
            }

            $revision->refresh();
            $this->assertSame('draft', $revision->status);
            $this->assertNull($revision->pdf_path);
            $this->assertNull($revision->published_at);
        }
    }

    public function test_a_published_revision_cannot_be_updated(): void
    {
        $revision = $this->publishedRevision();

        $this->expectException(PublishedRevisionMutation::class);

        $revision->update(['change_reason' => 'Rewrite history']);
    }

    public function test_a_published_revision_cannot_be_deleted(): void
    {
        $revision = $this->publishedRevision();

        $this->expectException(PublishedRevisionMutation::class);

        $revision->delete();
    }

    public function test_a_published_revision_pdf_cannot_be_replaced(): void
    {
        $revision = $this->publishedRevision();

        $revision->forceFill([
            'pdf_path' => 'finance/revisions/replacement.pdf',
            'pdf_sha256' => str_repeat('b', 64),
        ]);

        $this->expectException(PublishedRevisionMutation::class);

        $revision->save();
    }

    public function test_a_published_revision_snapshot_cannot_be_altered(): void
    {
        $revision = $this->publishedRevision();

        $this->expectException(PublishedRevisionMutation::class);

        $revision->update(['snapshot' => ['lines' => [['description' => 'Rewritten']]]]);
    }

    public function test_quiet_model_operations_cannot_bypass_published_revision_immutability(): void
    {
        $revision = $this->publishedRevision();

        try {
            $revision->forceFill(['status' => 'void'])->saveQuietly();
            $this->fail('A quiet update mutated a published revision.');
        } catch (PublishedRevisionMutation) {
            $this->addToAssertionCount(1);
        }

        $revision->refresh();
        $this->assertSame('published', $revision->status);

        $this->expectException(PublishedRevisionMutation::class);
        $revision->deleteQuietly();
    }

    public function test_eloquent_bulk_operations_cannot_bypass_published_revision_immutability(): void
    {
        $revision = $this->publishedRevision();

        try {
            DocumentRevisionRecord::query()
                ->whereKey($revision->id)
                ->update(['status' => 'void']);
            $this->fail('A bulk update mutated a published revision.');
        } catch (PublishedRevisionMutation) {
            $this->addToAssertionCount(1);
        }

        $revision->refresh();
        $this->assertSame('published', $revision->status);

        $this->expectException(PublishedRevisionMutation::class);
        DocumentRevisionRecord::query()->whereKey($revision->id)->delete();
    }

    public function test_document_activities_cannot_be_updated_or_deleted(): void
    {
        $activity = $this->createOwnedAggregate()[2];

        try {
            $activity->update(['type' => 'rewritten']);
            $this->fail('An activity was updated.');
        } catch (PublishedRevisionMutation) {
            $this->addToAssertionCount(1);
        }

        $activity->refresh();
        $this->assertSame('created', $activity->type);

        $this->expectException(PublishedRevisionMutation::class);
        $activity->delete();
    }

    public function test_quiet_and_bulk_operations_cannot_mutate_document_activities(): void
    {
        $activity = $this->createOwnedAggregate()[2];

        foreach (
            [
                fn () => $activity->forceFill(['type' => 'quiet'])->saveQuietly(),
                fn () => DocumentActivityRecord::query()->whereKey($activity->id)->update(['type' => 'bulk']),
                fn () => DocumentActivityRecord::query()->whereKey($activity->id)->delete(),
            ] as $mutation
        ) {
            try {
                $mutation();
                $this->fail('An activity mutation bypassed the append-only guard.');
            } catch (PublishedRevisionMutation) {
                $this->addToAssertionCount(1);
            }
        }

        $activity->refresh();
        $this->assertSame('created', $activity->type);

        $this->expectException(PublishedRevisionMutation::class);
        $activity->deleteQuietly();
    }

    public function test_database_owner_cascade_can_remove_an_immutable_aggregate(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        [$series] = $this->createAggregate('018f4ca3-224d-7d8d-9f00-555555555555');
        $this->publishRevision($series->revisions()->firstOrFail());

        $owner->delete();

        $this->assertSame(0, DB::table('finance_document_series')->where('id', $series->id)->count());
        $this->assertSame(0, DB::table('finance_document_revisions')->where('document_series_id', $series->id)->count());
        $this->assertSame(0, DB::table('finance_document_activities')->where('document_series_id', $series->id)->count());
        $this->assertSame(0, DB::table('finance_document_notes')->where('document_series_id', $series->id)->count());
    }

    /**
     * @return array{
     *     DocumentSeriesRecord,
     *     DocumentRevisionRecord,
     *     DocumentActivityRecord,
     *     DocumentNoteRecord
     * }
     */
    private function createAggregate(string $uuid): array
    {
        $series = new DocumentSeriesRecord;
        $series->forceFill([
            'uuid' => $uuid,
            'document_type' => 'invoice',
            'status' => 'draft',
            'created_by' => auth()->id(),
        ])->save();

        $revision = $series->revisions()->make([
            'status' => 'draft',
            'snapshot' => ['lines' => []],
            'net_minor' => 10000,
            'vat_minor' => 1900,
            'gross_minor' => 11900,
            'currency' => 'EUR',
            'change_reason' => 'Initial revision',
            'created_by' => auth()->id(),
        ]);
        $revision->forceFill(['revision_number' => 1])->save();

        $activity = $series->activities()->create([
            'document_revision_id' => $revision->id,
            'type' => 'created',
            'payload' => ['origin' => 'test'],
            'created_by' => auth()->id(),
        ]);

        $note = $series->notes()->create([
            'document_revision_id' => $revision->id,
            'type' => 'comment',
            'visibility' => 'internal',
            'body' => 'Owner-visible note',
            'created_by' => auth()->id(),
        ]);

        return [$series, $revision, $activity, $note];
    }

    /**
     * @return array{
     *     DocumentSeriesRecord,
     *     DocumentRevisionRecord,
     *     DocumentActivityRecord,
     *     DocumentNoteRecord
     * }
     */
    private function createOwnedAggregate(): array
    {
        $this->actingAs(User::factory()->create());

        return $this->createAggregate('018f4ca3-224d-7d8d-9f00-666666666666');
    }

    private function publishedRevision(): DocumentRevisionRecord
    {
        return $this->publishRevision($this->createOwnedAggregate()[1]);
    }

    private function publishRevision(DocumentRevisionRecord $revision): DocumentRevisionRecord
    {
        DB::table('finance_document_revisions')
            ->where('id', $revision->id)
            ->update([
                'status' => 'published',
                'pdf_path' => 'finance/revisions/original.pdf',
                'pdf_sha256' => str_repeat('a', 64),
                'published_at' => now(),
            ]);

        return $revision->refresh();
    }
}
