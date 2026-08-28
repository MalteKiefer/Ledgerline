<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DocumentCoreSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_core_tables_expose_the_required_owner_scoped_columns(): void
    {
        $requiredColumns = [
            'finance_document_series' => [
                'id', 'user_id', 'uuid', 'document_type', 'status', 'source_type',
                'source_id', 'created_by', 'created_at', 'updated_at',
            ],
            'finance_document_revisions' => [
                'id', 'user_id', 'document_series_id', 'revision_number',
                'previous_revision_id', 'status', 'snapshot', 'net_minor',
                'vat_minor', 'gross_minor', 'currency', 'change_reason',
                'pdf_path', 'pdf_sha256', 'published_at', 'created_by', 'created_at',
            ],
            'finance_document_activities' => [
                'id', 'user_id', 'document_series_id', 'document_revision_id',
                'type', 'payload', 'created_by', 'created_at',
            ],
            'finance_document_notes' => [
                'id', 'user_id', 'document_series_id', 'document_revision_id',
                'type', 'visibility', 'body', 'created_by', 'created_at', 'updated_at',
            ],
        ];

        foreach ($requiredColumns as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
            $this->assertTrue(
                Schema::hasColumns($table, $columns),
                "Table {$table} is missing one or more required columns",
            );
        }
    }

    public function test_document_core_indexes_enforce_identity_and_support_owner_queries(): void
    {
        $this->assertTrue(Schema::hasIndex('finance_document_series', ['user_id', 'uuid'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_document_series', ['user_id', 'source_type', 'source_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_document_series', ['user_id', 'document_type', 'status']));
        $this->assertTrue(Schema::hasIndex('finance_document_revisions', ['document_series_id', 'revision_number'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_document_activities', ['user_id', 'created_at']));
        $this->assertTrue(Schema::hasIndex('finance_document_notes', ['user_id', 'created_at']));

        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-111111111111', 'invoice', 41);

        $this->expectQueryException(function () use ($owner): void {
            $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-111111111111', 'invoice', 42);
        });

        $otherSeriesId = $this->insertSeries((int) $otherOwner->id, '018f4ca3-224d-7d8d-9f00-111111111111', 'invoice', 41);
        $this->assertNotSame($seriesId, $otherSeriesId);

        $this->expectQueryException(function () use ($owner): void {
            $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-222222222222', 'invoice', 41);
        });
    }

    public function test_revision_number_and_previous_revision_are_protected_by_constraints(): void
    {
        $owner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-333333333333');
        $firstRevisionId = $this->insertRevision((int) $owner->id, $seriesId, 1);
        $secondRevisionId = $this->insertRevision((int) $owner->id, $seriesId, 2, $firstRevisionId);

        $this->expectQueryException(function () use ($owner, $seriesId): void {
            $this->insertRevision((int) $owner->id, $seriesId, 1);
        });

        $this->expectQueryException(function () use ($firstRevisionId): void {
            DB::table('finance_document_revisions')->where('id', $firstRevisionId)->delete();
        });

        $this->assertSame(2, DB::table('finance_document_revisions')->whereIn('id', [$firstRevisionId, $secondRevisionId])->count());
    }

    public function test_previous_revision_must_belong_to_the_same_series(): void
    {
        $owner = User::factory()->create();
        $firstSeriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-777777777777', 'quote', 71);
        $secondSeriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-888888888888', 'quote', 72);
        $firstRevisionId = $this->insertRevision((int) $owner->id, $firstSeriesId, 1);

        $this->expectQueryException(function () use ($owner, $secondSeriesId, $firstRevisionId): void {
            $this->insertRevision((int) $owner->id, $secondSeriesId, 1, $firstRevisionId);
        });
    }

    public function test_child_records_cannot_cross_the_series_owner_boundary(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-666666666666');
        $now = now();

        $this->expectQueryException(function () use ($otherOwner, $seriesId): void {
            $this->insertRevision((int) $otherOwner->id, $seriesId, 1);
        });

        $this->expectQueryException(function () use ($otherOwner, $seriesId, $now): void {
            DB::table('finance_document_activities')->insert([
                'user_id' => $otherOwner->id,
                'document_series_id' => $seriesId,
                'type' => 'created',
                'created_by' => $otherOwner->id,
                'created_at' => $now,
            ]);
        });

        $this->expectQueryException(function () use ($otherOwner, $seriesId, $now): void {
            DB::table('finance_document_notes')->insert([
                'user_id' => $otherOwner->id,
                'document_series_id' => $seriesId,
                'type' => 'comment',
                'visibility' => 'internal',
                'body' => 'Cross-owner note',
                'created_by' => $otherOwner->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function test_series_deletion_is_restricted_by_revisions_while_activities_and_notes_cascade(): void
    {
        $owner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-444444444444');
        $revisionId = $this->insertRevision((int) $owner->id, $seriesId, 1);
        $now = now();

        DB::table('finance_document_activities')->insert([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId,
            'type' => 'created',
            'payload' => '{}',
            'created_by' => $owner->id,
            'created_at' => $now,
        ]);
        DB::table('finance_document_notes')->insert([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId,
            'type' => 'comment',
            'visibility' => 'internal',
            'body' => 'Initial migration note',
            'created_by' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectQueryException(function () use ($seriesId): void {
            DB::table('finance_document_series')->where('id', $seriesId)->delete();
        });

        DB::table('finance_document_revisions')->where('id', $revisionId)->delete();
        DB::table('finance_document_series')->where('id', $seriesId)->delete();

        $this->assertSame(0, DB::table('finance_document_activities')->where('document_series_id', $seriesId)->count());
        $this->assertSame(0, DB::table('finance_document_notes')->where('document_series_id', $seriesId)->count());
    }

    public function test_note_visibility_rejects_values_outside_the_public_contract(): void
    {
        $owner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-555555555555');

        $this->expectQueryException(function () use ($owner, $seriesId): void {
            $now = now();
            DB::table('finance_document_notes')->insert([
                'user_id' => $owner->id,
                'document_series_id' => $seriesId,
                'type' => 'comment',
                'visibility' => 'private',
                'body' => 'Invalid visibility',
                'created_by' => $owner->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    private function insertSeries(
        int $userId,
        string $uuid,
        string $sourceType = 'quote',
        int $sourceId = 1,
    ): int {
        $now = now();

        return (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => $userId,
            'uuid' => $uuid,
            'document_type' => 'quote',
            'status' => 'draft',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertRevision(int $userId, int $seriesId, int $revisionNumber, ?int $previousRevisionId = null): int
    {
        return (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $userId,
            'document_series_id' => $seriesId,
            'revision_number' => $revisionNumber,
            'previous_revision_id' => $previousRevisionId,
            'status' => 'draft',
            'snapshot' => '{}',
            'net_minor' => 10000,
            'vat_minor' => 1900,
            'gross_minor' => 11900,
            'currency' => 'EUR',
            'change_reason' => null,
            'pdf_path' => null,
            'pdf_sha256' => null,
            'published_at' => null,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /** @param callable(): void $operation */
    private function expectQueryException(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a database constraint violation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
