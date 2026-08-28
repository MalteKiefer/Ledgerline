<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule\Quotes;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class QuoteSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_workflow_tables_expose_the_required_owner_scoped_columns(): void
    {
        $requiredColumns = [
            'finance_quote_series' => [
                'document_series_id', 'user_id', 'partner_id', 'current_revision_id',
                'number', 'sequence_year', 'sequence_number', 'version', 'published_at',
                'accepted_at', 'declined_at', 'converted_at', 'deleted_at', 'created_at',
                'updated_at',
            ],
            'finance_quote_drafts' => [
                'document_series_id', 'user_id', 'based_on_revision_id', 'payload',
                'net_minor', 'vat_minor', 'gross_minor', 'currency', 'updated_by',
                'created_at', 'updated_at',
            ],
            'finance_quote_number_sequences' => [
                'user_id', 'year', 'next_sequence',
            ],
            'finance_quote_operations' => [
                'id', 'user_id', 'document_series_id', 'operation', 'idempotency_key',
                'request_sha256', 'state', 'result', 'error_code', 'started_at',
                'completed_at',
            ],
            'finance_quote_deliveries' => [
                'id', 'user_id', 'document_series_id', 'document_revision_id',
                'recipient', 'recipient_domain', 'message_id', 'state', 'attempts',
                'last_error_code', 'queued_at', 'sent_at', 'failed_at',
            ],
            'finance_quote_conversions' => [
                'id', 'user_id', 'document_series_id', 'source_revision_id',
                'target_type', 'target_reference', 'target_id', 'created_at',
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

    public function test_quote_workflow_indexes_preserve_numbers_and_idempotency(): void
    {
        $this->assertTrue(Schema::hasIndex(
            'finance_quote_series',
            ['user_id', 'document_series_id'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_quote_series',
            ['user_id', 'sequence_year', 'sequence_number'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_quote_number_sequences',
            ['user_id', 'year'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_quote_operations',
            ['user_id', 'operation', 'idempotency_key'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_quote_deliveries',
            ['user_id', 'message_id'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_quote_conversions',
            ['user_id', 'source_revision_id', 'target_type'],
            'unique',
        ));
    }

    public function test_quote_series_current_revision_and_partner_cannot_cross_aggregate_or_owner_boundaries(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $seriesId = $this->insertDocumentSeries((int) $owner->id);
        $otherSeriesId = $this->insertDocumentSeries((int) $owner->id);
        $foreignSeriesId = $this->insertDocumentSeries((int) $otherOwner->id);
        $this->insertQuoteSeries((int) $owner->id, $seriesId);
        $this->insertQuoteSeries((int) $owner->id, $otherSeriesId);
        $this->insertQuoteSeries((int) $otherOwner->id, $foreignSeriesId);
        $revisionId = $this->insertRevision((int) $owner->id, $seriesId, 1);
        $otherSeriesRevisionId = $this->insertRevision((int) $owner->id, $otherSeriesId, 1);
        $foreignRevisionId = $this->insertRevision((int) $otherOwner->id, $foreignSeriesId, 1);

        $this->expectConstraintViolation(function () use ($seriesId, $otherSeriesRevisionId): void {
            DB::table('finance_quote_series')->where('document_series_id', $seriesId)
                ->update(['current_revision_id' => $otherSeriesRevisionId]);
        });
        $this->expectConstraintViolation(function () use ($seriesId, $foreignRevisionId): void {
            DB::table('finance_quote_series')->where('document_series_id', $seriesId)
                ->update(['current_revision_id' => $foreignRevisionId]);
        });

        $foreignPartnerId = $this->insertPartner((int) $otherOwner->id);
        $this->expectConstraintViolation(function () use ($seriesId, $foreignPartnerId): void {
            DB::table('finance_quote_series')->where('document_series_id', $seriesId)
                ->update(['partner_id' => $foreignPartnerId]);
        });

        $partnerId = $this->insertPartner((int) $owner->id);
        DB::table('finance_quote_series')->where('document_series_id', $seriesId)
            ->update(['partner_id' => $partnerId]);
        $this->expectConstraintViolation(function () use ($partnerId, $otherOwner): void {
            DB::table('finance_partners')->where('id', $partnerId)
                ->update(['user_id' => $otherOwner->id]);
        });

        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update([
            'current_revision_id' => $revisionId,
            'published_at' => now(),
        ]);
        $this->assertSame(
            $revisionId,
            DB::table('finance_quote_series')->where('document_series_id', $seriesId)->value('current_revision_id'),
        );
    }

    public function test_quote_extension_can_only_attach_to_a_quote_document_series(): void
    {
        $owner = User::factory()->create();
        $invoiceSeriesId = $this->insertDocumentSeries((int) $owner->id, 'invoice');

        $this->expectConstraintViolation(function () use ($owner, $invoiceSeriesId): void {
            $this->insertQuoteSeries((int) $owner->id, $invoiceSeriesId);
        });

        $quoteSeriesId = $this->insertDocumentSeries((int) $owner->id);
        $this->insertQuoteSeries((int) $owner->id, $quoteSeriesId);
        $this->expectConstraintViolation(function () use ($quoteSeriesId): void {
            DB::table('finance_document_series')->where('id', $quoteSeriesId)
                ->update(['document_type' => 'invoice']);
        });
    }

    public function test_quote_series_rejects_invalid_versions_number_tuples_and_reused_deleted_numbers(): void
    {
        $owner = User::factory()->create();
        $seriesId = $this->insertDocumentSeries((int) $owner->id);
        $this->insertQuoteSeries((int) $owner->id, $seriesId, [
            'number' => 'AN-2026-0007',
            'sequence_year' => 2026,
            'sequence_number' => 7,
            'deleted_at' => now(),
        ]);

        $this->assertSame(0, DB::table('finance_quote_series')->where('document_series_id', $seriesId)->value('version'));

        $this->expectConstraintViolation(function () use ($owner): void {
            $duplicateSeriesId = $this->insertDocumentSeries((int) $owner->id);
            $this->insertQuoteSeries((int) $owner->id, $duplicateSeriesId, [
                'number' => 'AN-2026-9999',
                'sequence_year' => 2026,
                'sequence_number' => 7,
            ]);
        });
        $this->expectConstraintViolation(function () use ($owner): void {
            $partialSeriesId = $this->insertDocumentSeries((int) $owner->id);
            $this->insertQuoteSeries((int) $owner->id, $partialSeriesId, ['number' => 'AN-2026-0008']);
        });
        $this->expectConstraintViolation(function () use ($seriesId): void {
            DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update(['version' => -1]);
        });
        $this->expectConstraintViolation(function () use ($owner): void {
            $invalidSequenceSeriesId = $this->insertDocumentSeries((int) $owner->id);
            $this->insertQuoteSeries((int) $owner->id, $invalidSequenceSeriesId, [
                'number' => 'AN-2026-0000',
                'sequence_year' => 2026,
                'sequence_number' => 0,
            ]);
        });
        $this->expectConstraintViolation(function () use ($owner): void {
            $invalidDecisionSeriesId = $this->insertDocumentSeries((int) $owner->id);
            $this->insertQuoteSeries((int) $owner->id, $invalidDecisionSeriesId, [
                'accepted_at' => now(),
                'declined_at' => now(),
            ]);
        });
    }

    public function test_draft_base_revision_must_belong_to_the_same_owner_and_quote_series(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $seriesId = $this->insertDocumentSeries((int) $owner->id);
        $otherSeriesId = $this->insertDocumentSeries((int) $owner->id);
        $foreignSeriesId = $this->insertDocumentSeries((int) $otherOwner->id);
        $this->insertQuoteSeries((int) $owner->id, $seriesId);
        $this->insertQuoteSeries((int) $owner->id, $otherSeriesId);
        $this->insertQuoteSeries((int) $otherOwner->id, $foreignSeriesId);
        $revisionId = $this->insertRevision((int) $owner->id, $seriesId, 1);
        $otherSeriesRevisionId = $this->insertRevision((int) $owner->id, $otherSeriesId, 1);
        $foreignRevisionId = $this->insertRevision((int) $otherOwner->id, $foreignSeriesId, 1);

        $this->insertDraft((int) $owner->id, $seriesId, $revisionId);

        $this->expectConstraintViolation(function () use ($owner, $otherSeriesId, $revisionId): void {
            $this->insertDraft((int) $owner->id, $otherSeriesId, $revisionId);
        });
        $this->expectConstraintViolation(function () use ($otherOwner, $foreignSeriesId, $otherSeriesRevisionId): void {
            $this->insertDraft((int) $otherOwner->id, $foreignSeriesId, $otherSeriesRevisionId);
        });
        $this->expectConstraintViolation(function () use ($owner, $otherSeriesId, $foreignRevisionId): void {
            $this->insertDraft((int) $owner->id, $otherSeriesId, $foreignRevisionId);
        });
    }

    public function test_number_sequences_are_positive_and_unique_per_owner_and_year(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        $this->insertNumberSequence((int) $owner->id, 2026, 8);
        $this->insertNumberSequence((int) $otherOwner->id, 2026, 8);

        $this->expectConstraintViolation(fn () => $this->insertNumberSequence((int) $owner->id, 2026, 9));
        $this->expectConstraintViolation(fn () => $this->insertNumberSequence((int) $owner->id, 0, 1));
        $this->expectConstraintViolation(fn () => $this->insertNumberSequence((int) $owner->id, 2027, 0));
    }

    public function test_operations_enforce_owner_scope_state_hash_and_idempotency_uniqueness(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $seriesId = $this->insertDocumentSeries((int) $owner->id);
        $this->insertQuoteSeries((int) $owner->id, $seriesId);

        $this->insertOperation((int) $owner->id, $seriesId, 'publish', 'publish-1');
        $this->insertOperation((int) $owner->id, null, 'send', 'publish-1');

        $this->expectConstraintViolation(
            fn () => $this->insertOperation((int) $owner->id, $seriesId, 'publish', 'publish-1'),
        );
        $this->expectConstraintViolation(
            fn () => $this->insertOperation((int) $otherOwner->id, $seriesId, 'publish', 'foreign-owner'),
        );
        $this->expectConstraintViolation(
            fn () => $this->insertOperation((int) $owner->id, null, 'publish', 'invalid-state', ['state' => 'done']),
        );
        $this->expectConstraintViolation(
            fn () => $this->insertOperation((int) $owner->id, null, 'publish', 'invalid-hash', ['request_sha256' => 'short']),
        );
    }

    public function test_deliveries_enforce_owner_series_revision_state_attempt_and_message_integrity(): void
    {
        [$ownerId, $seriesId, $revisionId, $otherSeriesRevisionId, $foreignOwnerId, $foreignSeriesId, $foreignRevisionId] =
            $this->revisionBoundaryFixture();

        $this->insertDelivery($ownerId, $seriesId, $revisionId, 'message-1');

        $this->expectConstraintViolation(
            fn () => $this->insertDelivery($ownerId, $seriesId, $otherSeriesRevisionId, 'message-2'),
        );
        $this->expectConstraintViolation(
            fn () => $this->insertDelivery($ownerId, $seriesId, $foreignRevisionId, 'message-3'),
        );
        $this->expectConstraintViolation(
            fn () => $this->insertDelivery($foreignOwnerId, $seriesId, $revisionId, 'message-4'),
        );
        $this->expectConstraintViolation(
            fn () => $this->insertDelivery($ownerId, $seriesId, $revisionId, 'message-1'),
        );
        $this->expectConstraintViolation(
            fn () => $this->insertDelivery($ownerId, $seriesId, $revisionId, 'message-state', ['state' => 'delivered']),
        );
        $this->expectConstraintViolation(
            fn () => $this->insertDelivery($ownerId, $seriesId, $revisionId, 'message-attempts', ['attempts' => -1]),
        );

        $this->insertDelivery($foreignOwnerId, $foreignSeriesId, $foreignRevisionId, 'message-1');
        $this->assertSame(2, DB::table('finance_quote_deliveries')->count());
    }

    public function test_conversions_enforce_source_and_target_ownership_type_and_idempotency(): void
    {
        [$ownerId, $seriesId, $revisionId, $otherSeriesRevisionId, $foreignOwnerId, , $foreignRevisionId] =
            $this->revisionBoundaryFixture();
        $invoiceId = $this->insertInvoice($ownerId);
        $foreignInvoiceId = $this->insertInvoice($foreignOwnerId);

        $this->insertConversion($ownerId, $seriesId, $revisionId, 'legacy-invoice:'.$invoiceId, $invoiceId);

        $this->expectConstraintViolation(function () use ($invoiceId, $foreignOwnerId): void {
            DB::table('invoices')->where('id', $invoiceId)->update(['user_id' => $foreignOwnerId]);
        });

        $this->expectConstraintViolation(
            fn () => $this->insertConversion($ownerId, $seriesId, $revisionId, 'legacy-invoice:again', $invoiceId),
        );
        $this->expectConstraintViolation(
            fn () => $this->insertConversion($ownerId, $seriesId, $otherSeriesRevisionId, 'legacy-invoice:other-series'),
        );
        $this->expectConstraintViolation(
            fn () => $this->insertConversion($ownerId, $seriesId, $foreignRevisionId, 'legacy-invoice:foreign-source'),
        );
        $newSeriesId = $this->insertDocumentSeries($ownerId);
        $this->insertQuoteSeries($ownerId, $newSeriesId);
        $newRevisionId = $this->insertRevision($ownerId, $newSeriesId, 1);
        $this->expectConstraintViolation(
            fn () => $this->insertConversion($ownerId, $newSeriesId, $newRevisionId, 'legacy-invoice:foreign-target', $foreignInvoiceId),
        );

        $invalidTypeSeriesId = $this->insertDocumentSeries($ownerId);
        $this->insertQuoteSeries($ownerId, $invalidTypeSeriesId);
        $invalidTypeRevisionId = $this->insertRevision($ownerId, $invalidTypeSeriesId, 1);
        $this->expectConstraintViolation(
            fn () => $this->insertConversion($ownerId, $invalidTypeSeriesId, $invalidTypeRevisionId, 'project:1', null, 'project'),
        );
    }

    public function test_direct_history_deletes_are_restricted_but_owner_delete_cascades_every_quote_row(): void
    {
        $owner = User::factory()->create();
        $ownerId = (int) $owner->id;
        $seriesId = $this->insertDocumentSeries($ownerId);
        $partnerId = $this->insertPartner($ownerId);
        $this->insertQuoteSeries($ownerId, $seriesId, ['partner_id' => $partnerId]);
        $revisionId = $this->insertRevision($ownerId, $seriesId, 1);
        DB::table('finance_quote_series')->where('document_series_id', $seriesId)->update([
            'current_revision_id' => $revisionId,
            'published_at' => now(),
        ]);
        $this->insertDraft($ownerId, $seriesId, $revisionId);
        $this->insertNumberSequence($ownerId, 2026, 8);
        $this->insertOperation($ownerId, $seriesId, 'publish', 'cascade-operation');
        $this->insertDelivery($ownerId, $seriesId, $revisionId, 'cascade-message');
        $invoiceId = $this->insertInvoice($ownerId);
        $this->insertConversion($ownerId, $seriesId, $revisionId, 'legacy-invoice:'.$invoiceId, $invoiceId);

        $this->expectConstraintViolation(function () use ($seriesId): void {
            DB::table('finance_document_series')->where('id', $seriesId)->delete();
        });
        $this->expectConstraintViolation(function () use ($revisionId): void {
            DB::table('finance_document_revisions')->where('id', $revisionId)->delete();
        });

        DB::table('finance_partners')->where('id', $partnerId)->delete();
        $this->assertNull(DB::table('finance_quote_series')->where('document_series_id', $seriesId)->value('partner_id'));
        DB::table('invoices')->where('id', $invoiceId)->delete();
        $this->assertNull(DB::table('finance_quote_conversions')->where('source_revision_id', $revisionId)->value('target_id'));

        $owner->delete();

        foreach ([
            'finance_quote_series',
            'finance_quote_drafts',
            'finance_quote_number_sequences',
            'finance_quote_operations',
            'finance_quote_deliveries',
            'finance_quote_conversions',
        ] as $table) {
            $this->assertSame(0, DB::table($table)->where('user_id', $ownerId)->count(), "Owner rows remain in {$table}");
        }
        $this->assertSame(0, DB::table('finance_document_series')->where('id', $seriesId)->count());
        $this->assertSame(0, DB::table('finance_document_revisions')->where('id', $revisionId)->count());
    }

    public function test_postgresql_ddl_uses_cascades_nulling_and_deferred_history_guards(): void
    {
        $defaultConnection = DB::getDefaultConnection();
        $postgresConnection = 'pgsql_quote_workflow_ddl';
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
                $migration = require database_path('migrations/2027_03_03_100000_create_finance_quote_workflow.php');
                $migration->up();
            });
        } finally {
            DB::setDefaultConnection($defaultConnection);
            DB::purge($postgresConnection);
            Schema::clearResolvedInstance('db.schema');
        }

        $ddl = strtolower(implode("\n", array_column($queries, 'query')));

        foreach ([
            'finance_quote_series_user_id_foreign',
            'finance_quote_drafts_user_id_foreign',
            'finance_quote_number_sequences_user_id_foreign',
            'finance_quote_operations_user_id_foreign',
            'finance_quote_deliveries_user_id_foreign',
            'finance_quote_conversions_user_id_foreign',
        ] as $constraint) {
            $this->assertMatchesRegularExpression("/{$constraint}.*on delete cascade/", $ddl);
        }

        foreach ([
            'finance_quote_series_owner_document_foreign',
            'finance_quote_series_current_revision_foreign',
            'finance_quote_drafts_based_revision_foreign',
            'finance_quote_operations_owner_series_foreign',
            'finance_quote_deliveries_owner_series_foreign',
            'finance_quote_deliveries_owner_series_revision_foreign',
            'finance_quote_conversions_owner_series_foreign',
            'finance_quote_conversions_owner_series_revision_foreign',
        ] as $constraint) {
            $this->assertMatchesRegularExpression(
                "/{$constraint}.*on delete no action deferrable initially deferred/",
                $ddl,
            );
        }

        $this->assertMatchesRegularExpression('/finance_quote_drafts_owner_series_foreign.*on delete cascade/', $ddl);
        $this->assertMatchesRegularExpression('/finance_quote_series_partner_id_foreign.*on delete set null/', $ddl);
        $this->assertMatchesRegularExpression('/finance_quote_conversions_target_id_foreign.*on delete set null/', $ddl);
        $this->assertStringContainsString('finance_quote_series_number_tuple_check', $ddl);
        $this->assertStringContainsString('finance_quote_number_sequences_positive_check', $ddl);
        $this->assertStringContainsString('finance_quote_operations_request_hash_check', $ddl);
        $this->assertStringContainsString('finance_quote_document_series_type_guard', $ddl);
        $this->assertStringContainsString('finance_quote_partner_owner_update_guard', $ddl);
        $this->assertStringContainsString('finance_quote_invoice_owner_update_guard', $ddl);
        $this->assertStringContainsString('finance_quote_series_document_type_guard', $ddl);
        $this->assertStringContainsString('finance_quote_series_partner_owner_guard', $ddl);
        $this->assertStringContainsString('finance_quote_conversions_target_owner_guard', $ddl);
    }

    public function test_migration_can_be_rolled_back_and_reapplied(): void
    {
        $migration = require database_path('migrations/2027_03_03_100000_create_finance_quote_workflow.php');

        try {
            $migration->down();

            foreach ([
                'finance_quote_conversions',
                'finance_quote_deliveries',
                'finance_quote_operations',
                'finance_quote_number_sequences',
                'finance_quote_drafts',
                'finance_quote_series',
            ] as $table) {
                $this->assertFalse(Schema::hasTable($table), "Rollback left {$table} behind");
            }
        } finally {
            $migration->up();
        }

        $this->assertTrue(Schema::hasTable('finance_quote_series'));
        $this->assertTrue(Schema::hasTable('finance_quote_conversions'));
    }

    /** @param array<string, mixed> $overrides */
    private function insertQuoteSeries(int $userId, int $seriesId, array $overrides = []): void
    {
        $now = now();
        DB::table('finance_quote_series')->insert(array_merge([
            'document_series_id' => $seriesId,
            'user_id' => $userId,
            'partner_id' => null,
            'current_revision_id' => null,
            'number' => null,
            'sequence_year' => null,
            'sequence_number' => null,
            'version' => 0,
            'published_at' => null,
            'accepted_at' => null,
            'declined_at' => null,
            'converted_at' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    private function insertDocumentSeries(int $userId, string $documentType = 'quote'): int
    {
        $now = now();

        return (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => $userId,
            'uuid' => (string) Str::uuid(),
            'document_type' => $documentType,
            'status' => 'draft',
            'source_type' => null,
            'source_id' => null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertRevision(int $userId, int $seriesId, int $revisionNumber): int
    {
        return (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $userId,
            'document_series_id' => $seriesId,
            'revision_number' => $revisionNumber,
            'previous_revision_id' => null,
            'status' => 'published',
            'snapshot' => '{}',
            'net_minor' => 10000,
            'vat_minor' => 1900,
            'gross_minor' => 11900,
            'currency' => 'EUR',
            'change_reason' => null,
            'pdf_path' => 'finance/revisions/test.pdf',
            'pdf_sha256' => str_repeat('a', 64),
            'published_at' => now(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    private function insertDraft(int $userId, int $seriesId, ?int $basedOnRevisionId): void
    {
        $now = now();
        DB::table('finance_quote_drafts')->insert([
            'document_series_id' => $seriesId,
            'user_id' => $userId,
            'based_on_revision_id' => $basedOnRevisionId,
            'payload' => '{}',
            'net_minor' => 10000,
            'vat_minor' => 1900,
            'gross_minor' => 11900,
            'currency' => 'EUR',
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertNumberSequence(int $userId, int $year, int $nextSequence): void
    {
        DB::table('finance_quote_number_sequences')->insert([
            'user_id' => $userId,
            'year' => $year,
            'next_sequence' => $nextSequence,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function insertOperation(
        int $userId,
        ?int $seriesId,
        string $operation,
        string $key,
        array $overrides = [],
    ): void {
        DB::table('finance_quote_operations')->insert(array_merge([
            'user_id' => $userId,
            'document_series_id' => $seriesId,
            'operation' => $operation,
            'idempotency_key' => $key,
            'request_sha256' => str_repeat('b', 64),
            'state' => 'reserved',
            'result' => null,
            'error_code' => null,
            'started_at' => now(),
            'completed_at' => null,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertDelivery(
        int $userId,
        int $seriesId,
        int $revisionId,
        string $messageId,
        array $overrides = [],
    ): void {
        DB::table('finance_quote_deliveries')->insert(array_merge([
            'user_id' => $userId,
            'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId,
            'recipient' => 'billing@example.com',
            'recipient_domain' => 'example.com',
            'message_id' => $messageId,
            'state' => 'queued',
            'attempts' => 0,
            'last_error_code' => null,
            'queued_at' => now(),
            'sent_at' => null,
            'failed_at' => null,
        ], $overrides));
    }

    private function insertConversion(
        int $userId,
        int $seriesId,
        int $revisionId,
        string $targetReference,
        ?int $targetId = null,
        string $targetType = 'invoice',
    ): void {
        DB::table('finance_quote_conversions')->insert([
            'user_id' => $userId,
            'document_series_id' => $seriesId,
            'source_revision_id' => $revisionId,
            'target_type' => $targetType,
            'target_reference' => $targetReference,
            'target_id' => $targetId,
            'created_at' => now(),
        ]);
    }

    private function insertPartner(int $userId): int
    {
        $now = now();

        return (int) DB::table('finance_partners')->insertGetId([
            'user_id' => $userId,
            'name' => 'Partner '.Str::random(8),
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertInvoice(int $userId): int
    {
        $now = now();

        return (int) DB::table('invoices')->insertGetId([
            'user_id' => $userId,
            'status' => 'draft',
            'currency' => 'EUR',
            'imported' => false,
            'version_seq' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array{int, int, int, int, int, int, int} */
    private function revisionBoundaryFixture(): array
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $ownerId = (int) $owner->id;
        $foreignOwnerId = (int) $otherOwner->id;
        $seriesId = $this->insertDocumentSeries($ownerId);
        $otherSeriesId = $this->insertDocumentSeries($ownerId);
        $foreignSeriesId = $this->insertDocumentSeries($foreignOwnerId);
        $this->insertQuoteSeries($ownerId, $seriesId);
        $this->insertQuoteSeries($ownerId, $otherSeriesId);
        $this->insertQuoteSeries($foreignOwnerId, $foreignSeriesId);

        return [
            $ownerId,
            $seriesId,
            $this->insertRevision($ownerId, $seriesId, 1),
            $this->insertRevision($ownerId, $otherSeriesId, 1),
            $foreignOwnerId,
            $foreignSeriesId,
            $this->insertRevision($foreignOwnerId, $foreignSeriesId, 1),
        ];
    }

    /** @param callable(): void $operation */
    private function expectConstraintViolation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a database constraint violation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
