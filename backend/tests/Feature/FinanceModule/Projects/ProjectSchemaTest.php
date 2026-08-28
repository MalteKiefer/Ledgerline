<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Projects;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProjectSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_workflow_tables_expose_the_required_columns(): void
    {
        $required = [
            'finance_project_records' => [
                'id', 'user_id', 'uuid', 'parent_project_id', 'source_type', 'source_id',
                'name', 'kind', 'status', 'partner_reference', 'starts_on', 'due_on',
                'budget_minor', 'currency', 'version', 'archived_at', 'created_by',
                'created_at', 'updated_at',
            ],
            'finance_project_work_items' => [
                'id', 'user_id', 'project_id', 'uuid', 'title', 'description', 'status',
                'starts_on', 'due_on', 'estimate_quantity_scaled', 'is_milestone', 'sort',
                'source_revision_id', 'source_line_index', 'product_reference', 'version',
                'created_by', 'deleted_at', 'created_at', 'updated_at',
            ],
            'finance_project_time_entries' => [
                'id', 'user_id', 'project_id', 'work_item_id', 'uuid', 'worked_on',
                'quantity_scaled', 'description', 'billable', 'hourly_rate_minor', 'currency',
                'invoice_target_reference', 'invoiced_at', 'version', 'created_by',
                'deleted_at', 'created_at', 'updated_at',
            ],
            'finance_project_ledger_entries' => [
                'id', 'user_id', 'project_id', 'uuid', 'direction', 'amount_minor', 'currency',
                'occurred_on', 'title', 'note', 'category_reference',
                'payment_method_reference', 'legacy_metadata', 'version', 'created_by',
                'deleted_at', 'created_at', 'updated_at',
            ],
            'finance_project_document_links' => [
                'id', 'user_id', 'project_id', 'source_type', 'source_reference',
                'document_series_id', 'pinned_revision_id', 'role', 'metadata_snapshot',
                'attached_by', 'attached_at', 'detached_by', 'detached_at',
            ],
            'finance_project_notes' => [
                'id', 'user_id', 'project_id', 'type', 'visibility', 'body',
                'supersedes_note_id', 'created_by', 'created_at',
            ],
            'finance_project_activities' => [
                'id', 'user_id', 'project_id', 'type', 'subject_type',
                'subject_reference', 'payload', 'created_by', 'occurred_at', 'created_at',
            ],
            'finance_project_operations' => [
                'id', 'user_id', 'project_id', 'operation', 'idempotency_key',
                'request_sha256', 'state', 'result', 'error_code', 'started_at',
                'completed_at',
            ],
        ];

        foreach ($required as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
            $this->assertTrue(Schema::hasColumns($table, $columns), "Missing columns on {$table}");
        }

        $this->assertTrue(Schema::hasColumn('finance_document_notes', 'supersedes_note_id'));
    }

    public function test_identity_and_query_indexes_match_the_owner_scoped_contract(): void
    {
        foreach (['finance_project_records', 'finance_project_work_items', 'finance_project_time_entries', 'finance_project_ledger_entries'] as $table) {
            $this->assertTrue(Schema::hasIndex($table, ['user_id', 'uuid'], 'unique'));
        }

        $this->assertTrue(Schema::hasIndex('finance_project_records', ['user_id', 'source_type', 'source_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_project_work_items', ['user_id', 'source_revision_id', 'source_line_index'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_project_records', ['user_id', 'status', 'updated_at']));
        $this->assertTrue(Schema::hasIndex('finance_project_work_items', ['project_id', 'status', 'sort']));
        $this->assertTrue(Schema::hasIndex('finance_project_time_entries', ['project_id', 'worked_on']));
        $this->assertTrue(Schema::hasIndex('finance_project_ledger_entries', ['project_id', 'occurred_on']));
        $this->assertTrue(Schema::hasIndex('finance_project_document_links', ['user_id', 'source_type', 'source_reference']));
        $this->assertTrue(Schema::hasIndex('finance_project_document_links', ['project_id', 'detached_at', 'attached_at']));
        $this->assertTrue(Schema::hasIndex('finance_project_notes', ['project_id', 'type', 'created_at']));
        $this->assertTrue(Schema::hasIndex('finance_project_notes', ['project_id', 'visibility', 'created_at']));
        $this->assertTrue(Schema::hasIndex('finance_project_activities', ['project_id', 'type', 'occurred_at']));
        $this->assertTrue(Schema::hasIndex('finance_project_operations', ['user_id', 'operation', 'idempotency_key'], 'unique'));
    }

    public function test_project_identity_defaults_enums_and_exact_budget_are_enforced(): void
    {
        $owner = User::factory()->create();
        $projectId = $this->insertProject((int) $owner->id, [
            'source_type' => 'legacy.finance_project',
            'source_id' => 41,
            'budget_minor' => 9_007_199_254_740_993,
        ]);

        $stored = DB::table('finance_project_records')->find($projectId);
        $this->assertSame(9_007_199_254_740_993, $stored->budget_minor);
        $this->assertSame(0, $stored->version);

        $this->expectConstraint(fn () => $this->insertProject((int) $owner->id, ['uuid' => $stored->uuid]));
        $this->expectConstraint(fn () => $this->insertProject((int) $owner->id, ['source_type' => 'legacy.finance_project', 'source_id' => 41]));
        $this->expectConstraint(fn () => $this->insertProject((int) $owner->id, ['source_type' => 'legacy.finance_project', 'source_id' => null]));
        $this->expectConstraint(fn () => $this->insertProject((int) $owner->id, ['kind' => 'company']));
        $this->expectConstraint(fn () => $this->insertProject((int) $owner->id, ['status' => 'deleted']));
        $this->expectConstraint(fn () => $this->insertProject((int) $owner->id, ['budget_minor' => -1]));
        $this->expectConstraint(fn () => $this->insertProject((int) $owner->id, ['currency' => 'eur']));
    }

    public function test_parent_and_all_project_children_reject_cross_owner_references(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $projectId = $this->insertProject((int) $owner->id);
        $foreignProjectId = $this->insertProject((int) $other->id);

        $this->expectConstraint(fn () => $this->insertProject((int) $owner->id, ['parent_project_id' => $foreignProjectId]));
        $childId = $this->insertProject((int) $owner->id, ['parent_project_id' => $projectId]);
        $this->expectConstraint(fn () => DB::table('finance_project_records')->where('id', $projectId)->delete());
        $this->expectConstraint(fn () => $this->insertWorkItem((int) $owner->id, $foreignProjectId));
        $this->expectConstraint(fn () => $this->insertTimeEntry((int) $owner->id, $foreignProjectId));
        $this->expectConstraint(fn () => $this->insertLedgerEntry((int) $owner->id, $foreignProjectId));
        $this->expectConstraint(fn () => $this->insertProjectNote((int) $owner->id, $foreignProjectId));
        $this->expectConstraint(fn () => $this->insertActivity((int) $owner->id, $foreignProjectId));
        $this->expectConstraint(fn () => $this->insertOperation((int) $owner->id, $foreignProjectId));
        $this->expectConstraint(fn () => $this->insertDocumentLink((int) $owner->id, $foreignProjectId));
        $this->expectConstraint(fn () => $this->insertActivity((int) $owner->id, $projectId, ['created_by' => $other->id]));

        $this->assertSame($projectId, (int) DB::table('finance_project_records')->where('id', $projectId)->value('id'));
        $this->assertSame($childId, (int) DB::table('finance_project_records')->where('id', $childId)->value('id'));
    }

    public function test_work_items_enforce_project_revision_pairing_and_scaled_values(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $projectId = $this->insertProject((int) $owner->id);
        $seriesId = $this->insertSeries((int) $owner->id);
        $revisionId = $this->insertRevision((int) $owner->id, $seriesId);
        $otherSeriesId = $this->insertSeries((int) $other->id);
        $otherRevisionId = $this->insertRevision((int) $other->id, $otherSeriesId);

        $workId = $this->insertWorkItem((int) $owner->id, $projectId, [
            'source_revision_id' => $revisionId,
            'source_line_index' => 0,
            'estimate_quantity_scaled' => 25_000,
        ]);
        $this->assertSame(0, DB::table('finance_project_work_items')->where('id', $workId)->value('version'));

        $this->expectConstraint(fn () => $this->insertWorkItem((int) $owner->id, $projectId, ['source_revision_id' => $revisionId]));
        $this->expectConstraint(fn () => $this->insertWorkItem((int) $owner->id, $projectId, ['source_revision_id' => $revisionId, 'source_line_index' => 0]));
        $this->expectConstraint(fn () => $this->insertWorkItem((int) $owner->id, $projectId, ['source_revision_id' => $revisionId, 'source_line_index' => -1]));
        $this->expectConstraint(fn () => $this->insertWorkItem((int) $owner->id, $projectId, ['source_revision_id' => $otherRevisionId, 'source_line_index' => 0]));
        $this->expectConstraint(fn () => $this->insertWorkItem((int) $owner->id, $projectId, ['status' => 'cancelled']));
        $this->expectConstraint(fn () => $this->insertWorkItem((int) $owner->id, $projectId, ['estimate_quantity_scaled' => 0]));
        $this->expectConstraint(fn () => $this->insertWorkItem((int) $owner->id, $projectId, ['is_milestone' => true, 'estimate_quantity_scaled' => 10_000]));
    }

    public function test_time_entries_bind_work_items_to_the_same_owner_and_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $projectId = $this->insertProject((int) $owner->id);
        $otherProjectId = $this->insertProject((int) $owner->id);
        $workId = $this->insertWorkItem((int) $owner->id, $projectId);
        $otherProjectWorkId = $this->insertWorkItem((int) $owner->id, $otherProjectId);
        $foreignProjectId = $this->insertProject((int) $other->id);
        $foreignWorkId = $this->insertWorkItem((int) $other->id, $foreignProjectId);

        $entryId = $this->insertTimeEntry((int) $owner->id, $projectId, ['work_item_id' => $workId, 'quantity_scaled' => -5_000]);
        $this->assertSame(-5_000, DB::table('finance_project_time_entries')->where('id', $entryId)->value('quantity_scaled'));

        $this->expectConstraint(fn () => $this->insertTimeEntry((int) $owner->id, $projectId, ['work_item_id' => $otherProjectWorkId]));
        $this->expectConstraint(fn () => $this->insertTimeEntry((int) $owner->id, $projectId, ['work_item_id' => $foreignWorkId]));
        $this->expectConstraint(fn () => $this->insertTimeEntry((int) $owner->id, $projectId, ['quantity_scaled' => 0]));
        $this->expectConstraint(fn () => $this->insertTimeEntry((int) $owner->id, $projectId, ['hourly_rate_minor' => -1]));
        $this->expectConstraint(fn () => $this->insertTimeEntry((int) $owner->id, $projectId, ['invoice_target_reference' => 'invoice:1']));
        $this->expectConstraint(fn () => $this->insertTimeEntry((int) $owner->id, $projectId, ['invoiced_at' => now()]));
    }

    public function test_ledger_values_and_operation_idempotency_are_exact_and_constrained(): void
    {
        $owner = User::factory()->create();
        $projectId = $this->insertProject((int) $owner->id);
        $ledgerId = $this->insertLedgerEntry((int) $owner->id, $projectId, ['amount_minor' => 9_007_199_254_740_993]);

        $this->assertSame(9_007_199_254_740_993, DB::table('finance_project_ledger_entries')->where('id', $ledgerId)->value('amount_minor'));
        $this->assertSame(0, DB::table('finance_project_ledger_entries')->where('id', $ledgerId)->value('version'));
        $this->expectConstraint(fn () => $this->insertLedgerEntry((int) $owner->id, $projectId, ['amount_minor' => 0]));
        $this->expectConstraint(fn () => $this->insertLedgerEntry((int) $owner->id, $projectId, ['direction' => 'transfer']));

        $this->insertOperation((int) $owner->id, $projectId, ['operation' => 'attach', 'idempotency_key' => 'same-key']);
        $this->expectConstraint(fn () => $this->insertOperation((int) $owner->id, null, ['operation' => 'attach', 'idempotency_key' => 'same-key']));
        $this->expectConstraint(fn () => $this->insertOperation((int) $owner->id, $projectId, ['state' => 'unknown']));
    }

    public function test_document_links_enforce_source_owner_revision_pairing_and_active_uniqueness(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $projectId = $this->insertProject((int) $owner->id);
        $seriesUuid = $this->uuid(501);
        $seriesId = $this->insertSeries((int) $owner->id, $seriesUuid);
        $revisionId = $this->insertRevision((int) $owner->id, $seriesId);
        $otherSeriesId = $this->insertSeries((int) $owner->id, $this->uuid(502));
        $otherRevisionId = $this->insertRevision((int) $owner->id, $otherSeriesId);
        $foreignSeriesId = $this->insertSeries((int) $other->id, $this->uuid(503));
        $foreignRevisionId = $this->insertRevision((int) $other->id, $foreignSeriesId);

        $linkId = $this->insertDocumentLink((int) $owner->id, $projectId, [
            'source_type' => 'finance_series',
            'source_reference' => $seriesUuid,
            'document_series_id' => $seriesId,
            'pinned_revision_id' => $revisionId,
            'role' => 'quote',
        ]);
        $this->expectConstraint(fn () => $this->insertDocumentLink((int) $owner->id, $projectId, [
            'source_type' => 'finance_series', 'source_reference' => $seriesUuid,
            'document_series_id' => $seriesId, 'role' => 'quote',
        ]));

        DB::table('finance_project_document_links')->where('id', $linkId)->update(['detached_at' => now()]);
        $reattached = $this->insertDocumentLink((int) $owner->id, $projectId, [
            'source_type' => 'finance_series', 'source_reference' => $seriesUuid,
            'document_series_id' => $seriesId, 'pinned_revision_id' => $revisionId, 'role' => 'quote',
        ]);
        $this->assertGreaterThan($linkId, $reattached);

        $this->expectConstraint(fn () => $this->insertDocumentLink((int) $owner->id, $projectId, ['source_type' => 'finance_series']));
        $this->expectConstraint(fn () => $this->insertDocumentLink((int) $owner->id, $projectId, [
            'source_type' => 'finance_series', 'source_reference' => $this->uuid(999), 'document_series_id' => $seriesId,
        ]));
        $this->expectConstraint(fn () => $this->insertDocumentLink((int) $owner->id, $projectId, [
            'source_type' => 'finance_series', 'source_reference' => $seriesUuid,
            'document_series_id' => $seriesId, 'pinned_revision_id' => $otherRevisionId,
        ]));
        $this->expectConstraint(fn () => $this->insertDocumentLink((int) $owner->id, $projectId, [
            'source_type' => 'finance_series', 'source_reference' => $this->uuid(503),
            'document_series_id' => $foreignSeriesId, 'pinned_revision_id' => $foreignRevisionId,
        ]));
        $this->expectConstraint(fn () => $this->insertDocumentLink((int) $owner->id, $projectId, [
            'source_type' => 'file', 'source_reference' => 'file:41', 'document_series_id' => $seriesId,
        ]));
        $this->expectConstraint(fn () => $this->insertDocumentLink((int) $owner->id, $projectId, ['source_type' => 'unknown']));
        $this->expectConstraint(fn () => $this->insertDocumentLink((int) $owner->id, $projectId, ['role' => 'attachment']));
        $this->expectConstraint(fn () => DB::table('finance_project_document_links')->where('id', $linkId)->update(['detached_at' => null]));
    }

    public function test_detach_actor_requires_a_timestamp_but_system_detach_is_allowed(): void
    {
        $owner = User::factory()->create();
        $projectId = $this->insertProject((int) $owner->id);

        $this->expectConstraint(fn () => $this->insertDocumentLink((int) $owner->id, $projectId, ['detached_by' => $owner->id]));
        $id = $this->insertDocumentLink((int) $owner->id, $projectId, ['detached_at' => now()]);
        $this->assertNull(DB::table('finance_project_document_links')->where('id', $id)->value('detached_by'));
    }

    public function test_project_notes_are_owner_bound_append_history_with_exact_correction_rules(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $projectId = $this->insertProject((int) $owner->id);
        $otherProjectId = $this->insertProject((int) $owner->id);
        $foreignProjectId = $this->insertProject((int) $other->id);
        $noteId = $this->insertProjectNote((int) $owner->id, $projectId);
        $otherNoteId = $this->insertProjectNote((int) $owner->id, $otherProjectId);
        $foreignNoteId = $this->insertProjectNote((int) $other->id, $foreignProjectId);

        $correctionId = $this->insertProjectNote((int) $owner->id, $projectId, ['type' => 'correction', 'supersedes_note_id' => $noteId]);
        $this->assertGreaterThan($noteId, $correctionId);
        $this->expectConstraint(fn () => $this->insertProjectNote((int) $owner->id, $projectId, ['type' => 'correction']));
        $this->expectConstraint(fn () => $this->insertProjectNote((int) $owner->id, $projectId, ['type' => 'note', 'supersedes_note_id' => $noteId]));
        $this->expectConstraint(fn () => $this->insertProjectNote((int) $owner->id, $projectId, ['type' => 'memo']));
        $this->expectConstraint(fn () => $this->insertProjectNote((int) $owner->id, $projectId, ['visibility' => 'public']));
        $this->expectConstraint(fn () => $this->insertProjectNote((int) $owner->id, $projectId, ['type' => 'correction', 'supersedes_note_id' => $otherNoteId]));
        $this->expectConstraint(fn () => $this->insertProjectNote((int) $owner->id, $projectId, ['type' => 'correction', 'supersedes_note_id' => $foreignNoteId]));
        $this->expectConstraint(fn () => DB::table('finance_project_notes')->where('id', $noteId)->update(['type' => 'correction', 'supersedes_note_id' => $noteId]));
    }

    public function test_document_note_corrections_are_bound_to_the_same_owner_and_series(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id);
        $otherSeriesId = $this->insertSeries((int) $owner->id);
        $foreignSeriesId = $this->insertSeries((int) $other->id);
        $noteId = $this->insertDocumentNote((int) $owner->id, $seriesId, ['type' => 'comment']);
        $otherNoteId = $this->insertDocumentNote((int) $owner->id, $otherSeriesId);
        $foreignNoteId = $this->insertDocumentNote((int) $other->id, $foreignSeriesId);

        $this->insertDocumentNote((int) $owner->id, $seriesId, ['type' => 'correction', 'supersedes_note_id' => $noteId]);
        $this->expectConstraint(fn () => $this->insertDocumentNote((int) $owner->id, $seriesId, ['type' => 'correction']));
        $this->expectConstraint(fn () => $this->insertDocumentNote((int) $owner->id, $seriesId, ['type' => 'note', 'supersedes_note_id' => $noteId]));
        $this->expectConstraint(fn () => $this->insertDocumentNote((int) $owner->id, $seriesId, ['type' => 'correction', 'supersedes_note_id' => $otherNoteId]));
        $this->expectConstraint(fn () => $this->insertDocumentNote((int) $owner->id, $seriesId, ['type' => 'correction', 'supersedes_note_id' => $foreignNoteId]));
        $this->expectConstraint(fn () => DB::table('finance_document_notes')->where('id', $noteId)->update(['type' => 'correction', 'supersedes_note_id' => $noteId]));
    }

    public function test_direct_aggregate_deletes_are_restricted_while_owner_delete_cascades_everything(): void
    {
        $owner = User::factory()->create();
        $projectId = $this->insertProject((int) $owner->id);
        $seriesId = $this->insertSeries((int) $owner->id);
        $revisionId = $this->insertRevision((int) $owner->id, $seriesId);
        $documentNoteId = $this->insertDocumentNote((int) $owner->id, $seriesId);
        $this->insertDocumentNote((int) $owner->id, $seriesId, [
            'type' => 'correction',
            'supersedes_note_id' => $documentNoteId,
        ]);
        $workId = $this->insertWorkItem((int) $owner->id, $projectId, [
            'source_revision_id' => $revisionId,
            'source_line_index' => 0,
        ]);
        $this->insertTimeEntry((int) $owner->id, $projectId, ['work_item_id' => $workId]);
        $noteId = $this->insertProjectNote((int) $owner->id, $projectId);
        $this->insertProjectNote((int) $owner->id, $projectId, ['type' => 'correction', 'supersedes_note_id' => $noteId]);
        $this->insertLedgerEntry((int) $owner->id, $projectId);
        $this->insertActivity((int) $owner->id, $projectId);
        $this->insertOperation((int) $owner->id, $projectId);
        $this->insertDocumentLink((int) $owner->id, $projectId, [
            'source_type' => 'finance_series',
            'source_reference' => DB::table('finance_document_series')->where('id', $seriesId)->value('uuid'),
            'document_series_id' => $seriesId,
            'pinned_revision_id' => $revisionId,
            'role' => 'quote',
        ]);

        $this->expectConstraint(fn () => DB::table('finance_project_work_items')->where('id', $workId)->delete());
        $this->expectConstraint(fn () => DB::table('finance_project_records')->where('id', $projectId)->delete());
        $this->expectConstraint(fn () => DB::table('finance_project_notes')->where('id', $noteId)->delete());
        $this->expectConstraint(fn () => DB::table('finance_document_revisions')->where('id', $revisionId)->delete());
        $this->expectConstraint(fn () => DB::table('finance_document_series')->where('id', $seriesId)->delete());

        $owner->delete();

        foreach ([
            'finance_project_records', 'finance_project_work_items', 'finance_project_time_entries',
            'finance_project_ledger_entries', 'finance_project_document_links', 'finance_project_notes',
            'finance_project_activities', 'finance_project_operations',
        ] as $table) {
            $this->assertSame(0, DB::table($table)->where('user_id', $owner->id)->count(), $table);
        }
        $this->assertSame(0, DB::table('finance_document_series')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_document_revisions')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('finance_document_notes')->where('user_id', $owner->id)->count());
    }

    public function test_migration_down_and_up_round_trip_only_its_additive_surface(): void
    {
        $migration = require database_path('migrations/2027_03_04_100000_create_finance_project_workflow.php');
        $migration->down();

        foreach ($this->projectTables() as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasTable('finance_document_notes'));
        $this->assertFalse(Schema::hasColumn('finance_document_notes', 'supersedes_note_id'));

        $migration->up();

        foreach ($this->projectTables() as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasColumn('finance_document_notes', 'supersedes_note_id'));
    }

    public function test_postgresql_ddl_preserves_owner_cascades_and_defers_integrity_guards(): void
    {
        $default = DB::getDefaultConnection();
        $connection = 'pgsql_project_schema_ddl';
        config([
            "database.connections.{$connection}" => array_merge(
                config('database.connections.pgsql'),
                ['database' => 'ledgerline_ddl_inspection'],
            ),
        ]);
        DB::setDefaultConnection($connection);
        Schema::clearResolvedInstance('db.schema');

        try {
            $queries = DB::connection()->pretend(function (): void {
                $migration = require database_path('migrations/2027_03_04_100000_create_finance_project_workflow.php');
                $migration->up();
            });
        } finally {
            DB::setDefaultConnection($default);
            DB::purge($connection);
            Schema::clearResolvedInstance('db.schema');
        }

        $ddl = preg_replace(
            '/\s+/',
            ' ',
            strtolower(implode("\n", array_column($queries, 'query'))),
        ) ?? '';

        foreach ($this->projectTables() as $table) {
            $constraint = "{$table}_user_id_foreign";
            $this->assertMatchesRegularExpression("/{$constraint}.*on delete cascade/", $ddl);
        }
        foreach ([
            'finance_project_records_owner_parent_foreign',
            'finance_project_work_items_owner_project_foreign',
            'finance_project_work_items_owner_revision_foreign',
            'finance_project_time_entries_owner_project_foreign',
            'finance_project_time_entries_owner_project_work_foreign',
            'finance_project_ledger_entries_owner_project_foreign',
            'finance_project_document_links_owner_project_foreign',
            'finance_project_notes_supersedes_foreign',
            'finance_project_notes_owner_project_foreign',
            'finance_project_activities_owner_project_foreign',
            'finance_project_operations_owner_project_foreign',
            'finance_project_document_links_owner_series_foreign',
            'finance_project_document_links_owner_revision_foreign',
            'finance_document_notes_supersedes_foreign',
        ] as $constraint) {
            $this->assertMatchesRegularExpression(
                "/{$constraint}.*on delete no action deferrable initially deferred/",
                $ddl,
            );
        }
        $this->assertStringContainsString(
            'create unique index finance_project_document_links_active_unique',
            $ddl,
        );
        $this->assertStringContainsString('where detached_at is null', $ddl);
        $this->assertStringContainsString('finance_project_document_links_validate_source', $ddl);
        $this->assertStringContainsString('finance_project_records_source_pair_check', $ddl);
        $this->assertStringContainsString('finance_project_time_entries_invoice_pair_check', $ddl);
        $this->assertStringContainsString('finance_project_notes_correction_pair_check', $ddl);
        $this->assertStringContainsString('finance_document_notes_correction_pair_check', $ddl);
    }

    /** @return list<string> */
    private function projectTables(): array
    {
        return [
            'finance_project_records', 'finance_project_work_items',
            'finance_project_time_entries', 'finance_project_ledger_entries',
            'finance_project_document_links', 'finance_project_notes',
            'finance_project_activities', 'finance_project_operations',
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function insertProject(int $userId, array $overrides = []): int
    {
        $now = now();

        return (int) DB::table('finance_project_records')->insertGetId(array_merge([
            'user_id' => $userId,
            'uuid' => $this->uuid(random_int(1_000, 999_999_999)),
            'parent_project_id' => null,
            'source_type' => null,
            'source_id' => null,
            'name' => 'Schema project',
            'kind' => 'business',
            'status' => 'planned',
            'partner_reference' => null,
            'starts_on' => null,
            'due_on' => null,
            'budget_minor' => null,
            'currency' => 'EUR',
            'archived_at' => null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertWorkItem(int $userId, int $projectId, array $overrides = []): int
    {
        $now = now();

        return (int) DB::table('finance_project_work_items')->insertGetId(array_merge([
            'user_id' => $userId, 'project_id' => $projectId,
            'uuid' => $this->uuid(random_int(1_000, 999_999_999)), 'title' => 'Work',
            'description' => null, 'status' => 'open', 'starts_on' => null, 'due_on' => null,
            'estimate_quantity_scaled' => null, 'is_milestone' => false, 'sort' => 0,
            'source_revision_id' => null, 'source_line_index' => null,
            'product_reference' => null, 'created_by' => $userId, 'deleted_at' => null,
            'created_at' => $now, 'updated_at' => $now,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertTimeEntry(int $userId, int $projectId, array $overrides = []): int
    {
        $now = now();

        return (int) DB::table('finance_project_time_entries')->insertGetId(array_merge([
            'user_id' => $userId, 'project_id' => $projectId, 'work_item_id' => null,
            'uuid' => $this->uuid(random_int(1_000, 999_999_999)), 'worked_on' => '2026-08-28',
            'quantity_scaled' => 10_000, 'description' => null, 'billable' => true,
            'hourly_rate_minor' => 10_000, 'currency' => 'EUR',
            'invoice_target_reference' => null, 'invoiced_at' => null,
            'created_by' => $userId, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertLedgerEntry(int $userId, int $projectId, array $overrides = []): int
    {
        $now = now();

        return (int) DB::table('finance_project_ledger_entries')->insertGetId(array_merge([
            'user_id' => $userId, 'project_id' => $projectId,
            'uuid' => $this->uuid(random_int(1_000, 999_999_999)), 'direction' => 'out',
            'amount_minor' => 1, 'currency' => 'EUR', 'occurred_on' => null,
            'title' => null, 'note' => null, 'category_reference' => null,
            'payment_method_reference' => null, 'legacy_metadata' => null,
            'created_by' => $userId, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertDocumentLink(int $userId, int $projectId, array $overrides = []): int
    {
        return (int) DB::table('finance_project_document_links')->insertGetId(array_merge([
            'user_id' => $userId, 'project_id' => $projectId, 'source_type' => 'file',
            'source_reference' => 'file:'.random_int(1_000, 999_999_999),
            'document_series_id' => null, 'pinned_revision_id' => null, 'role' => 'file',
            'metadata_snapshot' => '{}', 'attached_by' => $userId, 'attached_at' => now(),
            'detached_by' => null, 'detached_at' => null,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertProjectNote(int $userId, int $projectId, array $overrides = []): int
    {
        return (int) DB::table('finance_project_notes')->insertGetId(array_merge([
            'user_id' => $userId, 'project_id' => $projectId, 'type' => 'note',
            'visibility' => 'internal', 'body' => 'Project history',
            'supersedes_note_id' => null, 'created_by' => $userId, 'created_at' => now(),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertActivity(int $userId, int $projectId, array $overrides = []): int
    {
        return (int) DB::table('finance_project_activities')->insertGetId(array_merge([
            'user_id' => $userId, 'project_id' => $projectId, 'type' => 'project.created',
            'subject_type' => null, 'subject_reference' => null, 'payload' => null,
            'created_by' => $userId, 'occurred_at' => now(), 'created_at' => now(),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertOperation(int $userId, ?int $projectId, array $overrides = []): int
    {
        return (int) DB::table('finance_project_operations')->insertGetId(array_merge([
            'user_id' => $userId, 'project_id' => $projectId, 'operation' => 'create',
            'idempotency_key' => 'key-'.random_int(1_000, 999_999_999),
            'request_sha256' => str_repeat('a', 64), 'state' => 'reserved',
            'result' => null, 'error_code' => null, 'started_at' => now(), 'completed_at' => null,
        ], $overrides));
    }

    private function insertSeries(int $userId, ?string $uuid = null): int
    {
        $now = now();

        return (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => $userId, 'uuid' => $uuid ?? $this->uuid(random_int(1_000, 999_999_999)),
            'document_type' => 'quote', 'status' => 'draft', 'source_type' => null,
            'source_id' => null, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function insertRevision(int $userId, int $seriesId): int
    {
        return (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $userId, 'document_series_id' => $seriesId, 'revision_number' => 1,
            'previous_revision_id' => null, 'status' => 'draft', 'snapshot' => '{}',
            'net_minor' => 100, 'vat_minor' => 19, 'gross_minor' => 119, 'currency' => 'EUR',
            'change_reason' => null, 'pdf_path' => null, 'pdf_sha256' => null,
            'published_at' => null, 'created_by' => $userId, 'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function insertDocumentNote(int $userId, int $seriesId, array $overrides = []): int
    {
        $now = now();

        return (int) DB::table('finance_document_notes')->insertGetId(array_merge([
            'user_id' => $userId, 'document_series_id' => $seriesId,
            'document_revision_id' => null, 'type' => 'note', 'visibility' => 'internal',
            'body' => 'Document history', 'supersedes_note_id' => null,
            'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ], $overrides));
    }

    /** @param callable(): mixed $operation */
    private function expectConstraint(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a database constraint violation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    private function uuid(int $suffix): string
    {
        return sprintf('018f4ca3-224d-7d8d-9f00-%012d', $suffix);
    }
}
