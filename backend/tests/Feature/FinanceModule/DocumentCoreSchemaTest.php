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

    public function test_series_source_type_and_id_must_be_both_null_or_both_set(): void
    {
        $owner = User::factory()->create();

        $this->expectQueryException(function () use ($owner): void {
            $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-111111111113', 'invoice', null);
        });
        $this->expectQueryException(function () use ($owner): void {
            $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-111111111114', null, 44);
        });

        $withoutSourceId = $this->insertSeries(
            (int) $owner->id,
            '018f4ca3-224d-7d8d-9f00-111111111115',
            null,
            null,
        );
        $this->assertGreaterThan(0, $withoutSourceId);
    }

    public function test_series_source_pair_cannot_be_broken_by_update(): void
    {
        $owner = User::factory()->create();
        $seriesId = $this->insertSeries(
            (int) $owner->id,
            '018f4ca3-224d-7d8d-9f00-111111111116',
            'invoice',
            46,
        );

        $this->expectQueryException(function () use ($seriesId): void {
            DB::table('finance_document_series')->where('id', $seriesId)->update(['source_id' => null]);
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

    public function test_revision_number_must_be_greater_than_zero(): void
    {
        $owner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-333333333334', 'quote', 334);

        $this->expectQueryException(function () use ($owner, $seriesId): void {
            $this->insertRevision((int) $owner->id, $seriesId, 0);
        });
        $this->expectQueryException(function () use ($owner, $seriesId): void {
            $this->insertRevision((int) $owner->id, $seriesId, -1);
        });

        $revisionId = $this->insertRevision((int) $owner->id, $seriesId, 1);
        $this->expectQueryException(function () use ($revisionId): void {
            DB::table('finance_document_revisions')->where('id', $revisionId)->update(['revision_number' => 0]);
        });
    }

    public function test_revision_cannot_reference_itself_as_previous(): void
    {
        $owner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-333333333335', 'quote', 335);
        $revisionId = $this->insertRevision((int) $owner->id, $seriesId, 1);

        $this->expectQueryException(function () use ($revisionId): void {
            DB::table('finance_document_revisions')
                ->where('id', $revisionId)
                ->update(['previous_revision_id' => $revisionId]);
        });

        $this->expectQueryException(function () use ($owner, $seriesId): void {
            $this->insertRevision((int) $owner->id, $seriesId, 2, 9001, 9001);
        });
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

    public function test_activity_revision_must_belong_to_the_same_owner_and_series(): void
    {
        [$ownerId, $seriesId, $otherSeriesRevisionId, $otherOwnerRevisionId] = $this->mismatchedRevisionFixture();

        $this->expectQueryException(function () use ($ownerId, $seriesId, $otherSeriesRevisionId): void {
            $this->insertActivity($ownerId, $seriesId, $otherSeriesRevisionId);
        });
        $this->expectQueryException(function () use ($ownerId, $seriesId, $otherOwnerRevisionId): void {
            $this->insertActivity($ownerId, $seriesId, $otherOwnerRevisionId);
        });

        $matchingRevisionId = $this->insertRevision($ownerId, $seriesId, 1);
        $this->insertActivity($ownerId, $seriesId, $matchingRevisionId);
        $this->assertSame(1, DB::table('finance_document_activities')->count());
    }

    public function test_note_revision_must_belong_to_the_same_owner_and_series(): void
    {
        [$ownerId, $seriesId, $otherSeriesRevisionId, $otherOwnerRevisionId] = $this->mismatchedRevisionFixture();

        $this->expectQueryException(function () use ($ownerId, $seriesId, $otherSeriesRevisionId): void {
            $this->insertNote($ownerId, $seriesId, $otherSeriesRevisionId);
        });
        $this->expectQueryException(function () use ($ownerId, $seriesId, $otherOwnerRevisionId): void {
            $this->insertNote($ownerId, $seriesId, $otherOwnerRevisionId);
        });

        $matchingRevisionId = $this->insertRevision($ownerId, $seriesId, 1);
        $this->insertNote($ownerId, $seriesId, $matchingRevisionId);
        $this->assertSame(1, DB::table('finance_document_notes')->count());
    }

    public function test_series_deletion_cascades_the_complete_document_aggregate(): void
    {
        $owner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-444444444444');
        $firstRevisionId = $this->insertRevision((int) $owner->id, $seriesId, 1);
        $secondRevisionId = $this->insertRevision((int) $owner->id, $seriesId, 2, $firstRevisionId);
        $this->insertActivity((int) $owner->id, $seriesId, $secondRevisionId);
        $this->insertNote((int) $owner->id, $seriesId, $secondRevisionId);

        DB::table('finance_document_series')->where('id', $seriesId)->delete();

        $this->assertSame(0, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());
        $this->assertSame(0, DB::table('finance_document_activities')->where('document_series_id', $seriesId)->count());
        $this->assertSame(0, DB::table('finance_document_notes')->where('document_series_id', $seriesId)->count());
    }

    public function test_owner_deletion_cascades_the_complete_document_aggregate(): void
    {
        $owner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-444444444445');
        $firstRevisionId = $this->insertRevision((int) $owner->id, $seriesId, 1);
        $secondRevisionId = $this->insertRevision((int) $owner->id, $seriesId, 2, $firstRevisionId);
        $this->insertActivity((int) $owner->id, $seriesId, $secondRevisionId);
        $this->insertNote((int) $owner->id, $seriesId, $secondRevisionId);

        $owner->delete();

        $this->assertSame(0, DB::table('finance_document_series')->where('id', $seriesId)->count());
        $this->assertSame(0, DB::table('finance_document_revisions')->where('document_series_id', $seriesId)->count());
        $this->assertSame(0, DB::table('finance_document_activities')->where('document_series_id', $seriesId)->count());
        $this->assertSame(0, DB::table('finance_document_notes')->where('document_series_id', $seriesId)->count());
    }

    public function test_postgresql_ddl_uses_one_owner_cascade_root_and_deferred_revision_guards(): void
    {
        $defaultConnection = DB::getDefaultConnection();
        $postgresConnection = 'pgsql_document_core_ddl';
        config([
            "database.connections.{$postgresConnection}" => array_merge(
                config('database.connections.pgsql'),
                ['database' => 'ledgerline_ddl_inspection'],
            ),
        ]);
        DB::setDefaultConnection($postgresConnection);
        Schema::clearResolvedInstance('db.schema');

        try {
            $queries = DB::connection()->pretend(function (): void {
                $migration = require database_path('migrations/2026_08_28_100000_create_finance_document_core.php');
                $migration->up();
            });
        } finally {
            DB::setDefaultConnection($defaultConnection);
            DB::purge($postgresConnection);
            Schema::clearResolvedInstance('db.schema');
        }

        $ddl = strtolower(implode("\n", array_column($queries, 'query')));

        $this->assertMatchesRegularExpression(
            '/finance_document_series_user_id_foreign.*on delete cascade/',
            $ddl,
        );
        $this->assertStringNotContainsString('finance_document_revisions_user_id_foreign', $ddl);
        $this->assertStringNotContainsString('finance_document_activities_user_id_foreign', $ddl);
        $this->assertStringNotContainsString('finance_document_notes_user_id_foreign', $ddl);

        foreach ([
            'finance_document_revisions_owner_series_foreign',
            'finance_document_activities_owner_series_foreign',
            'finance_document_notes_owner_series_foreign',
        ] as $constraint) {
            $this->assertMatchesRegularExpression(
                "/{$constraint}.*on delete cascade/",
                $ddl,
            );
        }

        foreach ([
            'finance_document_revisions_previous_foreign',
            'finance_document_activities_owner_series_revision_foreign',
            'finance_document_notes_owner_series_revision_foreign',
        ] as $constraint) {
            $this->assertMatchesRegularExpression(
                "/{$constraint}.*on delete no action deferrable initially deferred/",
                $ddl,
            );
        }
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
        ?string $sourceType = 'quote',
        ?int $sourceId = 1,
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

    private function insertRevision(
        int $userId,
        int $seriesId,
        int $revisionNumber,
        ?int $previousRevisionId = null,
        ?int $id = null,
    ): int {
        $attributes = [
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
        ];
        if ($id !== null) {
            $attributes['id'] = $id;
        }

        return (int) DB::table('finance_document_revisions')->insertGetId($attributes);
    }

    private function insertActivity(int $userId, int $seriesId, ?int $revisionId): void
    {
        DB::table('finance_document_activities')->insert([
            'user_id' => $userId,
            'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId,
            'type' => 'created',
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    private function insertNote(int $userId, int $seriesId, ?int $revisionId): void
    {
        $now = now();
        DB::table('finance_document_notes')->insert([
            'user_id' => $userId,
            'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId,
            'type' => 'comment',
            'visibility' => 'internal',
            'body' => 'Revision-bound note',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array{int, int, int, int} */
    private function mismatchedRevisionFixture(): array
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-999999999991', 'quote', 991);
        $otherSeriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f00-999999999992', 'quote', 992);
        $otherOwnerSeriesId = $this->insertSeries((int) $otherOwner->id, '018f4ca3-224d-7d8d-9f00-999999999993', 'quote', 991);

        return [
            (int) $owner->id,
            $seriesId,
            $this->insertRevision((int) $owner->id, $otherSeriesId, 1),
            $this->insertRevision((int) $otherOwner->id, $otherOwnerSeriesId, 1),
        ];
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
