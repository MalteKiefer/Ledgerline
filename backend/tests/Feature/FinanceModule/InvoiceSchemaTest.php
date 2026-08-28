<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class InvoiceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_schema_exposes_only_workflow_projection_and_history_links(): void
    {
        $requiredColumns = [
            'finance_invoices' => [
                'id', 'user_id', 'uuid', 'document_series_id', 'current_revision_id',
                'kind', 'number', 'year', 'sequence', 'issue_date', 'due_date',
                'partner_id', 'project_id', 'source_type', 'source_key',
                'source_revision_id', 'source_snapshot_sha256', 'workflow_status',
                'finalized_at', 'sent_at', 'allocated_minor', 'open_minor', 'version',
                'cancels_invoice_id', 'created_at', 'updated_at',
            ],
            'finance_invoice_sequences' => [
                'id', 'user_id', 'series_key', 'year', 'next_sequence',
                'created_at', 'updated_at',
            ],
            'finance_invoice_deliveries' => [
                'id', 'user_id', 'uuid', 'invoice_id', 'document_series_id',
                'document_revision_id', 'kind', 'recipient', 'message_id', 'status',
                'attempts', 'last_error_code', 'idempotency_key_hash', 'request_hash',
                'queued_at', 'last_attempt_at', 'sent_at', 'next_retry_at',
                'created_at', 'updated_at',
            ],
            'finance_idempotency_records' => [
                'id', 'user_id', 'operation', 'key_hash', 'request_hash', 'status',
                'response_status', 'response_payload', 'completed_at', 'expires_at',
                'created_at', 'updated_at',
            ],
        ];

        foreach ($requiredColumns as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
            $this->assertTrue(
                Schema::hasColumns($table, $columns),
                "Table {$table} is missing one or more required columns",
            );
        }

        foreach (['customer', 'lines', 'net_minor', 'vat_minor', 'gross_minor', 'pdf_path', 'pdf_sha256'] as $revisionOwnedColumn) {
            $this->assertFalse(
                Schema::hasColumn('finance_invoices', $revisionOwnedColumn),
                "Revision-owned column {$revisionOwnedColumn} leaked into finance_invoices",
            );
        }
    }

    public function test_invoice_indexes_enforce_owner_scoped_identity_and_support_queries(): void
    {
        $this->assertTrue(Schema::hasIndex('finance_invoices', ['user_id', 'document_series_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_invoices', ['user_id', 'uuid'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_invoices', ['user_id', 'source_type', 'source_key'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_invoices', ['user_id', 'year', 'number'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_invoices', ['user_id', 'cancels_invoice_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_invoices', ['user_id', 'workflow_status', 'issue_date']));
        $this->assertTrue(Schema::hasIndex('finance_invoices', ['user_id', 'due_date', 'open_minor']));
        $this->assertTrue(Schema::hasIndex('finance_invoice_sequences', ['user_id', 'series_key', 'year'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_invoice_deliveries', ['user_id', 'kind', 'idempotency_key_hash'], 'unique'));
        $this->assertTrue(Schema::hasIndex('finance_invoice_deliveries', ['status', 'next_retry_at']));
        $this->assertTrue(Schema::hasIndex('finance_idempotency_records', ['user_id', 'operation', 'key_hash'], 'unique'));
    }

    public function test_invoice_series_and_current_revision_must_match_the_owner_and_each_other(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $seriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f10-100000000001');
        $revisionId = $this->insertRevision((int) $owner->id, $seriesId);
        $otherSeriesId = $this->insertSeries((int) $owner->id, '018f4ca3-224d-7d8d-9f10-100000000002');
        $otherSeriesRevisionId = $this->insertRevision((int) $owner->id, $otherSeriesId);
        $foreignSeriesId = $this->insertSeries((int) $otherOwner->id, '018f4ca3-224d-7d8d-9f10-100000000003');
        $foreignRevisionId = $this->insertRevision((int) $otherOwner->id, $foreignSeriesId);

        $invoiceId = $this->insertInvoice((int) $owner->id, $seriesId, $revisionId);
        $this->assertGreaterThan(0, $invoiceId);

        $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
            (int) $owner->id,
            $seriesId,
            $foreignRevisionId,
            ['uuid' => '018f4ca3-224d-7d8d-9f20-100000000002'],
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
            (int) $owner->id,
            $foreignSeriesId,
            $foreignRevisionId,
            ['uuid' => '018f4ca3-224d-7d8d-9f20-100000000003'],
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
            (int) $owner->id,
            $seriesId,
            $otherSeriesRevisionId,
            ['uuid' => '018f4ca3-224d-7d8d-9f20-100000000004'],
        ));
    }

    public function test_invoice_source_number_and_cancellation_uniqueness_keep_null_drafts_independent(): void
    {
        $owner = User::factory()->create();
        [$firstSeriesId, $firstRevisionId] = $this->documentFixture((int) $owner->id, 11);
        [$secondSeriesId, $secondRevisionId] = $this->documentFixture((int) $owner->id, 12);
        [$thirdSeriesId, $thirdRevisionId] = $this->documentFixture((int) $owner->id, 13);
        [$fourthSeriesId, $fourthRevisionId] = $this->documentFixture((int) $owner->id, 14);

        $originalId = $this->insertInvoice((int) $owner->id, $firstSeriesId, $firstRevisionId);
        $this->insertInvoice((int) $owner->id, $secondSeriesId, $secondRevisionId, [
            'uuid' => '018f4ca3-224d-7d8d-9f20-200000000002',
        ]);
        $this->insertInvoice((int) $owner->id, $thirdSeriesId, $thirdRevisionId, [
            'uuid' => '018f4ca3-224d-7d8d-9f20-200000000003',
            'source_type' => 'quote_revision',
            'source_key' => 'quote-1:revision-2',
            'source_revision_id' => 2,
            'source_snapshot_sha256' => str_repeat('a', 64),
            'number' => 'RE-2026-0001',
            'year' => 2026,
            'sequence' => 1,
        ]);

        $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
            (int) $owner->id,
            $fourthSeriesId,
            $fourthRevisionId,
            [
                'uuid' => '018f4ca3-224d-7d8d-9f20-200000000004',
                'source_type' => 'quote_revision',
                'source_key' => 'quote-1:revision-2',
                'source_revision_id' => 3,
                'source_snapshot_sha256' => str_repeat('b', 64),
            ],
        ));

        [$fifthSeriesId, $fifthRevisionId] = $this->documentFixture((int) $owner->id, 15);
        $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
            (int) $owner->id,
            $fifthSeriesId,
            $fifthRevisionId,
            [
                'uuid' => '018f4ca3-224d-7d8d-9f20-200000000005',
                'number' => 'RE-2026-0001',
                'year' => 2026,
                'sequence' => 99,
            ],
        ));

        [$creditSeriesId, $creditRevisionId] = $this->documentFixture((int) $owner->id, 16);
        $this->insertInvoice((int) $owner->id, $creditSeriesId, $creditRevisionId, [
            'uuid' => '018f4ca3-224d-7d8d-9f20-200000000006',
            'kind' => 'credit_note',
            'cancels_invoice_id' => $originalId,
        ]);

        [$duplicateCreditSeriesId, $duplicateCreditRevisionId] = $this->documentFixture((int) $owner->id, 17);
        $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
            (int) $owner->id,
            $duplicateCreditSeriesId,
            $duplicateCreditRevisionId,
            [
                'uuid' => '018f4ca3-224d-7d8d-9f20-200000000007',
                'kind' => 'credit_note',
                'cancels_invoice_id' => $originalId,
            ],
        ));
    }

    public function test_invoice_checks_reject_partial_numbering_source_metadata_and_invalid_states(): void
    {
        $owner = User::factory()->create();

        foreach ([
            ['number' => 'RE-1'],
            ['year' => 2026],
            ['sequence' => 1],
            ['number' => 'RE-1', 'year' => 2026],
            ['source_type' => 'quote_revision'],
            ['source_type' => 'quote_revision', 'source_key' => 'quote-2'],
            ['source_type' => 'quote_revision', 'source_key' => 'quote-3', 'source_revision_id' => 1],
            ['kind' => 'deposit'],
            ['workflow_status' => 'paid'],
            ['sequence' => -1, 'number' => 'RE-NEG', 'year' => 2026],
        ] as $index => $invalid) {
            [$seriesId, $revisionId] = $this->documentFixture((int) $owner->id, 100 + $index);
            $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
                (int) $owner->id,
                $seriesId,
                $revisionId,
                array_merge([
                    'uuid' => sprintf('018f4ca3-224d-7d8d-9f20-%012d', 100 + $index),
                ], $invalid),
            ));
        }

        [$seriesId, $revisionId] = $this->documentFixture((int) $owner->id, 199);
        $invoiceId = $this->insertInvoice((int) $owner->id, $seriesId, $revisionId);
        $this->expectConstraintViolation(function () use ($invoiceId): int {
            return DB::table('finance_invoices')->where('id', $invoiceId)->update([
                'number' => 'RE-UPDATE',
            ]);
        });
    }

    public function test_invoice_minor_unit_projections_remain_exact_signed_bigints(): void
    {
        $owner = User::factory()->create();
        [$seriesId, $revisionId] = $this->documentFixture((int) $owner->id, 201);
        $invoiceId = $this->insertInvoice((int) $owner->id, $seriesId, $revisionId, [
            'allocated_minor' => -9_007_199_254_740_991,
            'open_minor' => 9_007_199_254_740_991,
        ]);

        $invoice = DB::table('finance_invoices')->find($invoiceId);

        $this->assertSame(-9_007_199_254_740_991, (int) $invoice->allocated_minor);
        $this->assertSame(9_007_199_254_740_991, (int) $invoice->open_minor);
    }

    public function test_invoice_revision_currency_is_exactly_three_uppercase_letters(): void
    {
        $owner = User::factory()->create();
        $shortSeriesId = $this->insertSeries(
            (int) $owner->id,
            '018f4ca3-224d-7d8d-9f10-200000000021',
        );
        $lowercaseSeriesId = $this->insertSeries(
            (int) $owner->id,
            '018f4ca3-224d-7d8d-9f10-200000000022',
        );

        $this->expectConstraintViolation(fn (): int => $this->insertRevision(
            (int) $owner->id,
            $shortSeriesId,
            1,
            null,
            ['currency' => 'EU'],
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertRevision(
            (int) $owner->id,
            $lowercaseSeriesId,
            1,
            null,
            ['currency' => 'eur'],
        ));
    }

    public function test_sha256_fields_reject_non_hex_values_even_when_the_length_is_correct(): void
    {
        $owner = User::factory()->create();
        [$sourceSeriesId, $sourceRevisionId] = $this->documentFixture((int) $owner->id, 221);

        $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
            (int) $owner->id,
            $sourceSeriesId,
            $sourceRevisionId,
            [
                'source_type' => 'quote_revision',
                'source_key' => 'quote-invalid-hash',
                'source_revision_id' => 1,
                'source_snapshot_sha256' => str_repeat('z', 64),
            ],
        ));

        [$deliverySeriesId, $deliveryRevisionId] = $this->documentFixture((int) $owner->id, 222);
        $invoiceId = $this->insertInvoice((int) $owner->id, $deliverySeriesId, $deliveryRevisionId);
        $this->expectConstraintViolation(fn (): int => $this->insertDelivery(
            (int) $owner->id,
            $invoiceId,
            $deliverySeriesId,
            $deliveryRevisionId,
            ['idempotency_key_hash' => str_repeat('z', 64)],
        ));

        $this->expectConstraintViolation(fn (): int => $this->insertIdempotencyRecord(
            (int) $owner->id,
            'invoice.finalize',
            str_repeat('z', 64),
        ));
    }

    public function test_cancellation_target_must_be_owned_cannot_be_self_and_restricts_target_deletion(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        [$seriesId, $revisionId] = $this->documentFixture((int) $owner->id, 211);
        [$creditSeriesId, $creditRevisionId] = $this->documentFixture((int) $owner->id, 212);
        [$foreignCreditSeriesId, $foreignCreditRevisionId] = $this->documentFixture((int) $otherOwner->id, 213);
        $invoiceId = $this->insertInvoice((int) $owner->id, $seriesId, $revisionId);

        $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
            (int) $otherOwner->id,
            $foreignCreditSeriesId,
            $foreignCreditRevisionId,
            [
                'uuid' => '018f4ca3-224d-7d8d-9f20-200000000013',
                'kind' => 'credit_note',
                'cancels_invoice_id' => $invoiceId,
            ],
        ));

        $this->expectConstraintViolation(function () use ($invoiceId): int {
            return DB::table('finance_invoices')->where('id', $invoiceId)->update([
                'cancels_invoice_id' => $invoiceId,
            ]);
        });

        $this->insertInvoice((int) $owner->id, $creditSeriesId, $creditRevisionId, [
            'uuid' => '018f4ca3-224d-7d8d-9f20-200000000012',
            'kind' => 'credit_note',
            'cancels_invoice_id' => $invoiceId,
        ]);
        $this->expectConstraintViolation(function () use ($invoiceId): int {
            return DB::table('finance_invoices')->where('id', $invoiceId)->delete();
        });
    }

    public function test_invoice_sequences_are_owner_scoped_and_positive(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        $this->insertSequence((int) $owner->id, 'invoice', 2026, 1);
        $this->insertSequence((int) $otherOwner->id, 'invoice', 2026, 1);

        $this->expectConstraintViolation(fn (): int => $this->insertSequence((int) $owner->id, 'invoice', 2026, 2));
        $this->expectConstraintViolation(fn (): int => $this->insertSequence((int) $owner->id, 'invoice', 2027, 0));
    }

    public function test_deliveries_pin_an_owner_matched_invoice_revision_and_idempotency_identity(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        [$seriesId, $revisionId] = $this->documentFixture((int) $owner->id, 301);
        [$otherSeriesId, $otherRevisionId] = $this->documentFixture((int) $otherOwner->id, 302);
        $invoiceId = $this->insertInvoice((int) $owner->id, $seriesId, $revisionId);
        $otherInvoiceId = $this->insertInvoice((int) $otherOwner->id, $otherSeriesId, $otherRevisionId, [
            'uuid' => '018f4ca3-224d-7d8d-9f20-300000000002',
        ]);

        $deliveryId = $this->insertDelivery((int) $owner->id, $invoiceId, $seriesId, $revisionId);
        $this->assertGreaterThan(0, $deliveryId);

        $historicalRevisionId = $revisionId;
        $currentRevisionId = $this->insertRevision((int) $owner->id, $seriesId, 2, $historicalRevisionId);
        DB::table('finance_invoices')->where('id', $invoiceId)->update([
            'current_revision_id' => $currentRevisionId,
        ]);
        $this->assertSame(
            $historicalRevisionId,
            (int) DB::table('finance_invoice_deliveries')->where('id', $deliveryId)->value('document_revision_id'),
        );

        $this->expectConstraintViolation(fn (): int => $this->insertDelivery(
            (int) $owner->id,
            $otherInvoiceId,
            $otherSeriesId,
            $otherRevisionId,
            ['uuid' => '018f4ca3-224d-7d8d-9f30-300000000002'],
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertDelivery(
            (int) $owner->id,
            $invoiceId,
            $seriesId,
            $otherRevisionId,
            ['uuid' => '018f4ca3-224d-7d8d-9f30-300000000003'],
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertDelivery(
            (int) $owner->id,
            $invoiceId,
            $seriesId,
            $revisionId,
            [
                'uuid' => '018f4ca3-224d-7d8d-9f30-300000000004',
                'message_id' => '<invoice-delivery-duplicate-key@example.test>',
            ],
        ));
    }

    public function test_delivery_checks_reject_invalid_kind_status_attempts_and_hashes(): void
    {
        $owner = User::factory()->create();
        [$seriesId, $revisionId] = $this->documentFixture((int) $owner->id, 401);
        $invoiceId = $this->insertInvoice((int) $owner->id, $seriesId, $revisionId);

        foreach ([
            ['kind' => 'receipt'],
            ['status' => 'delivered'],
            ['attempts' => -1],
            ['idempotency_key_hash' => 'short'],
            ['request_hash' => 'short'],
        ] as $index => $invalid) {
            $this->expectConstraintViolation(fn (): int => $this->insertDelivery(
                (int) $owner->id,
                $invoiceId,
                $seriesId,
                $revisionId,
                array_merge([
                    'uuid' => sprintf('018f4ca3-224d-7d8d-9f30-%012d', 400 + $index),
                    'message_id' => "<invoice-delivery-{$index}@example.test>",
                    'idempotency_key_hash' => hash('sha256', "delivery-{$index}"),
                ], $invalid),
            ));
        }
    }

    public function test_idempotency_records_bind_key_to_operation_request_and_replay_payload(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $keyHash = hash('sha256', 'opaque-key');

        $this->insertIdempotencyRecord((int) $owner->id, 'invoice.finalize', $keyHash);
        $this->insertIdempotencyRecord((int) $owner->id, 'invoice.deliver', $keyHash);
        $this->insertIdempotencyRecord((int) $otherOwner->id, 'invoice.finalize', $keyHash);

        $this->expectConstraintViolation(fn (): int => $this->insertIdempotencyRecord(
            (int) $owner->id,
            'invoice.finalize',
            $keyHash,
            ['request_hash' => hash('sha256', 'different-payload')],
        ));

        $this->expectConstraintViolation(fn (): int => $this->insertIdempotencyRecord(
            (int) $owner->id,
            'invoice.cancel',
            'short',
        ));

        $this->expectConstraintViolation(fn (): int => $this->insertIdempotencyRecord(
            (int) $owner->id,
            'invoice.cancel',
            hash('sha256', 'other-key'),
            ['status' => 'unknown'],
        ));
    }

    public function test_invoice_dependencies_restrict_individual_deletion_but_owner_deletion_cascades(): void
    {
        $owner = User::factory()->create();
        [$seriesId, $revisionId] = $this->documentFixture((int) $owner->id, 501);
        [$creditSeriesId, $creditRevisionId] = $this->documentFixture((int) $owner->id, 502);
        $invoiceId = $this->insertInvoice((int) $owner->id, $seriesId, $revisionId);
        $creditInvoiceId = $this->insertInvoice((int) $owner->id, $creditSeriesId, $creditRevisionId, [
            'uuid' => '018f4ca3-224d-7d8d-9f20-500000000002',
            'kind' => 'credit_note',
            'cancels_invoice_id' => $invoiceId,
        ]);
        $this->insertSequence((int) $owner->id, 'invoice', 2026, 2);
        $this->insertDelivery((int) $owner->id, $invoiceId, $seriesId, $revisionId);
        $this->insertDelivery((int) $owner->id, $creditInvoiceId, $creditSeriesId, $creditRevisionId, [
            'uuid' => '018f4ca3-224d-7d8d-9f30-500000000002',
            'message_id' => '<invoice-credit-delivery@example.test>',
            'idempotency_key_hash' => hash('sha256', 'credit-delivery-key'),
            'request_hash' => hash('sha256', 'credit-delivery-payload'),
        ]);
        $this->insertIdempotencyRecord((int) $owner->id, 'invoice.finalize', hash('sha256', 'owner-delete'));

        $this->assertSame(2, DB::table('finance_invoices')->where('user_id', $owner->id)->count());
        $this->assertSame(2, DB::table('finance_document_revisions')->where('user_id', $owner->id)->count());
        $this->assertSame(2, DB::table('finance_invoice_deliveries')->where('user_id', $owner->id)->count());
        $this->assertSame(
            $invoiceId,
            (int) DB::table('finance_invoices')->where('id', $creditInvoiceId)->value('cancels_invoice_id'),
        );

        $this->expectConstraintViolation(function () use ($invoiceId): int {
            return DB::table('finance_invoices')->where('id', $invoiceId)->delete();
        });

        $owner->delete();

        foreach ([
            'finance_invoices',
            'finance_invoice_sequences',
            'finance_invoice_deliveries',
            'finance_idempotency_records',
            'finance_document_revisions',
            'finance_document_series',
        ] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "Owner cascade left rows in {$table}");
        }
    }

    public function test_postgresql_executes_owner_integrity_cascade_and_reapply_when_configured(): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');

        if (! extension_loaded('pdo_pgsql') || ! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped(
                'Set FINANCE_TEST_PGSQL_URL and install pdo_pgsql to run the PostgreSQL execution contract.',
            );
        }

        $defaultConnection = DB::getDefaultConnection();
        $postgresConnection = 'pgsql_invoice_execution';
        $schema = 'finance_invoice_task2_'.bin2hex(random_bytes(8));
        config([
            "database.connections.{$postgresConnection}" => array_merge(
                config('database.connections.pgsql'),
                [
                    'url' => $postgresUrl,
                    'search_path' => 'public',
                ],
            ),
        ]);
        DB::purge($postgresConnection);
        $connection = DB::connection($postgresConnection);
        $schemaCreated = false;

        try {
            $connection->statement("CREATE SCHEMA \"{$schema}\"");
            $schemaCreated = true;
            $connection->statement("SET search_path TO \"{$schema}\"");
            DB::setDefaultConnection($postgresConnection);
            Schema::clearResolvedInstance('db.schema');

            Schema::create('users', function (Blueprint $table): void {
                $table->id();
            });
            $foundationMigration = require database_path('migrations/2026_08_28_100000_create_finance_document_core.php');
            $invoiceMigration = require database_path('migrations/2026_08_28_110000_create_finance_invoices.php');
            $foundationMigration->up();
            $invoiceMigration->up();
            DB::table('users')->insert([['id' => 1], ['id' => 2]]);

            [$seriesId, $revisionId] = $this->documentFixture(1, 601);
            [$foreignSeriesId, $foreignRevisionId] = $this->documentFixture(2, 602);
            $invoiceId = $this->insertInvoice(1, $seriesId, $revisionId);

            $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
                1,
                $foreignSeriesId,
                $foreignRevisionId,
                ['uuid' => '018f4ca3-224d-7d8d-9f20-600000000002'],
            ));
            $this->expectConstraintViolation(fn (): int => $this->insertInvoice(
                2,
                $foreignSeriesId,
                $foreignRevisionId,
                [
                    'uuid' => '018f4ca3-224d-7d8d-9f20-600000000003',
                    'kind' => 'credit_note',
                    'cancels_invoice_id' => $invoiceId,
                ],
            ));
            $this->expectConstraintViolation(fn (): int => $this->insertDelivery(
                2,
                $invoiceId,
                $seriesId,
                $revisionId,
                [
                    'uuid' => '018f4ca3-224d-7d8d-9f30-600000000001',
                    'message_id' => '<cross-owner-delivery@example.test>',
                    'idempotency_key_hash' => hash('sha256', 'cross-owner-delivery'),
                ],
            ));

            [$creditSeriesId, $creditRevisionId] = $this->documentFixture(1, 603);
            $creditInvoiceId = $this->insertInvoice(1, $creditSeriesId, $creditRevisionId, [
                'uuid' => '018f4ca3-224d-7d8d-9f20-600000000004',
                'kind' => 'credit_note',
                'cancels_invoice_id' => $invoiceId,
            ]);
            $this->insertDelivery(1, $invoiceId, $seriesId, $revisionId);
            $this->insertDelivery(1, $creditInvoiceId, $creditSeriesId, $creditRevisionId, [
                'uuid' => '018f4ca3-224d-7d8d-9f30-600000000002',
                'message_id' => '<postgres-credit-delivery@example.test>',
                'idempotency_key_hash' => hash('sha256', 'postgres-credit-delivery'),
                'request_hash' => hash('sha256', 'postgres-credit-payload'),
            ]);
            $this->insertSequence(1, 'invoice', 2026, 2);
            $this->insertIdempotencyRecord(1, 'invoice.finalize', hash('sha256', 'postgres-owner-delete'));

            $this->assertSame(2, DB::table('finance_invoices')->where('user_id', 1)->count());
            $this->assertSame(2, DB::table('finance_document_revisions')->where('user_id', 1)->count());
            $this->assertSame(2, DB::table('finance_invoice_deliveries')->where('user_id', 1)->count());
            $this->assertSame(
                $invoiceId,
                (int) DB::table('finance_invoices')->where('id', $creditInvoiceId)->value('cancels_invoice_id'),
            );

            $this->expectConstraintViolation(function () use ($invoiceId): int {
                return DB::table('finance_invoices')->where('id', $invoiceId)->delete();
            });

            DB::transaction(function (): void {
                DB::table('users')->where('id', 1)->delete();
            });

            foreach ([
                'finance_invoices',
                'finance_invoice_sequences',
                'finance_invoice_deliveries',
                'finance_idempotency_records',
                'finance_document_revisions',
                'finance_document_series',
            ] as $table) {
                $this->assertSame(
                    0,
                    DB::table($table)->where('user_id', 1)->count(),
                    "PostgreSQL owner cascade left rows in {$table}",
                );
            }

            $invoiceMigration->down();
            $this->assertFalse(Schema::hasTable('finance_invoices'));
            $this->assertFalse(Schema::hasTable('finance_invoice_sequences'));
            $this->assertFalse(Schema::hasTable('finance_invoice_deliveries'));
            $this->assertFalse(Schema::hasTable('finance_idempotency_records'));
            $this->assertSame(
                0,
                DB::table('pg_catalog.pg_constraint as pc')
                    ->join('pg_catalog.pg_namespace as pn', 'pn.oid', '=', 'pc.connamespace')
                    ->where('pc.conname', 'finance_document_revisions_currency_check')
                    ->where('pn.nspname', $schema)
                    ->count(),
            );

            $invoiceMigration->up();
            $this->assertTrue(Schema::hasTable('finance_invoices'));
            $this->assertTrue(Schema::hasTable('finance_invoice_sequences'));
            $this->assertTrue(Schema::hasTable('finance_invoice_deliveries'));
            $this->assertTrue(Schema::hasTable('finance_idempotency_records'));
            $this->assertSame(
                1,
                DB::table('pg_catalog.pg_constraint as pc')
                    ->join('pg_catalog.pg_namespace as pn', 'pn.oid', '=', 'pc.connamespace')
                    ->where('pc.conname', 'finance_document_revisions_currency_check')
                    ->where('pn.nspname', $schema)
                    ->count(),
            );

            $reappliedInvoiceId = $this->insertInvoice(2, $foreignSeriesId, $foreignRevisionId, [
                'uuid' => '018f4ca3-224d-7d8d-9f20-600000000005',
            ]);
            $this->assertGreaterThan(0, $reappliedInvoiceId);
        } finally {
            DB::setDefaultConnection($defaultConnection);
            Schema::clearResolvedInstance('db.schema');

            try {
                if ($schemaCreated) {
                    $connection->statement('SET search_path TO public');
                    $connection->statement("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE");
                }
            } finally {
                DB::purge($postgresConnection);
            }
        }
    }

    public function test_postgresql_ddl_uses_partial_uniqueness_checks_and_deferred_history_foreign_keys(): void
    {
        $defaultConnection = DB::getDefaultConnection();
        $postgresConnection = 'pgsql_invoice_ddl';
        config([
            "database.connections.{$postgresConnection}" => array_merge(
                config('database.connections.pgsql'),
                ['database' => 'ledgerline_ddl_inspection'],
            ),
        ]);
        DB::setDefaultConnection($postgresConnection);
        Schema::clearResolvedInstance('db.schema');

        try {
            $migration = require database_path('migrations/2026_08_28_110000_create_finance_invoices.php');
            $upQueries = DB::connection()->pretend(function () use ($migration): void {
                $migration->up();
            });
            $downQueries = DB::connection()->pretend(function () use ($migration): void {
                $migration->down();
            });
        } finally {
            DB::setDefaultConnection($defaultConnection);
            DB::purge($postgresConnection);
            Schema::clearResolvedInstance('db.schema');
        }

        $ddl = strtolower(implode("\n", array_column($upQueries, 'query')));
        $downDdl = strtolower(implode("\n", array_column($downQueries, 'query')));

        foreach ([
            'finance_invoices_owner_source_unique.*where source_type is not null and source_key is not null',
            'finance_invoices_owner_number_unique.*where number is not null',
            'finance_invoices_owner_cancellation_unique.*where cancels_invoice_id is not null',
        ] as $pattern) {
            $this->assertMatchesRegularExpression("/{$pattern}/s", $ddl);
        }

        foreach ([
            'finance_invoices_owner_series_foreign',
            'finance_invoices_owner_series_revision_foreign',
            'finance_invoices_owner_cancellation_foreign',
            'finance_invoice_deliveries_owner_invoice_series_foreign',
            'finance_invoice_deliveries_owner_series_revision_foreign',
        ] as $constraint) {
            $this->assertMatchesRegularExpression(
                "/{$constraint}.*on delete no action deferrable initially deferred/",
                $ddl,
            );
        }

        foreach ([
            'finance_invoices_user_id_foreign',
            'finance_invoice_sequences_user_id_foreign',
            'finance_invoice_deliveries_user_id_foreign',
            'finance_idempotency_records_user_id_foreign',
        ] as $constraint) {
            $this->assertMatchesRegularExpression("/{$constraint}.*on delete cascade/", $ddl);
        }

        $this->assertStringContainsString('finance_invoices_number_group_check', $ddl);
        $this->assertStringContainsString('finance_invoices_source_group_check', $ddl);
        $this->assertStringContainsString('finance_document_revisions_currency_check', $ddl);
        $this->assertStringContainsString('finance_invoice_deliveries_state_check', $ddl);
        $this->assertStringContainsString('finance_idempotency_records_state_check', $ddl);
        $this->assertMatchesRegularExpression(
            '/create table "finance_invoices".*"allocated_minor" bigint.*"open_minor" bigint/s',
            $ddl,
        );
        $this->assertMatchesRegularExpression(
            '/"source_snapshot_sha256" varchar\(64\)/',
            $ddl,
        );
        $this->assertMatchesRegularExpression(
            '/"idempotency_key_hash" varchar\(64\).*"request_hash" varchar\(64\)/s',
            $ddl,
        );
        $this->assertStringNotContainsString(' create trigger ', $ddl);

        foreach ([
            'finance_idempotency_records',
            'finance_invoice_deliveries',
            'finance_invoice_sequences',
            'finance_invoices',
        ] as $table) {
            $this->assertStringContainsString("drop table if exists \"{$table}\"", $downDdl);
        }
        $this->assertStringContainsString(
            'drop constraint if exists finance_document_revisions_currency_check',
            $downDdl,
        );
    }

    public function test_migration_can_be_rolled_back_and_applied_again(): void
    {
        $migration = require database_path('migrations/2026_08_28_110000_create_finance_invoices.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('finance_idempotency_records'));
        $this->assertFalse(Schema::hasTable('finance_invoice_deliveries'));
        $this->assertFalse(Schema::hasTable('finance_invoice_sequences'));
        $this->assertFalse(Schema::hasTable('finance_invoices'));

        $migration->up();

        $this->assertTrue(Schema::hasTable('finance_invoices'));
        $this->assertTrue(Schema::hasTable('finance_invoice_sequences'));
        $this->assertTrue(Schema::hasTable('finance_invoice_deliveries'));
        $this->assertTrue(Schema::hasTable('finance_idempotency_records'));
    }

    /** @return array{int, int} */
    private function documentFixture(int $userId, int $suffix): array
    {
        $seriesId = $this->insertSeries(
            $userId,
            sprintf('018f4ca3-224d-7d8d-9f10-%012d', $suffix),
        );

        return [$seriesId, $this->insertRevision($userId, $seriesId)];
    }

    private function insertSeries(int $userId, string $uuid): int
    {
        $now = now();

        return (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => $userId,
            'uuid' => $uuid,
            'document_type' => 'invoice',
            'status' => 'draft',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertRevision(
        int $userId,
        int $seriesId,
        int $revisionNumber = 1,
        ?int $previousRevisionId = null,
        array $overrides = [],
    ): int {
        return (int) DB::table('finance_document_revisions')->insertGetId(array_merge([
            'user_id' => $userId,
            'document_series_id' => $seriesId,
            'revision_number' => $revisionNumber,
            'previous_revision_id' => $previousRevisionId,
            'status' => 'draft',
            'snapshot' => '{}',
            'net_minor' => 10_000,
            'vat_minor' => 1_900,
            'gross_minor' => 11_900,
            'currency' => 'EUR',
            'created_by' => $userId,
            'created_at' => now(),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertInvoice(int $userId, int $seriesId, int $revisionId, array $overrides = []): int
    {
        $now = now();

        return (int) DB::table('finance_invoices')->insertGetId(array_merge([
            'user_id' => $userId,
            'uuid' => '018f4ca3-224d-7d8d-9f20-100000000001',
            'document_series_id' => $seriesId,
            'current_revision_id' => $revisionId,
            'kind' => 'invoice',
            'number' => null,
            'year' => null,
            'sequence' => null,
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'partner_id' => null,
            'project_id' => null,
            'source_type' => null,
            'source_key' => null,
            'source_revision_id' => null,
            'source_snapshot_sha256' => null,
            'workflow_status' => 'draft',
            'finalized_at' => null,
            'sent_at' => null,
            'allocated_minor' => 0,
            'open_minor' => 11_900,
            'version' => 0,
            'cancels_invoice_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    private function insertSequence(int $userId, string $seriesKey, int $year, int $nextSequence): int
    {
        $now = now();

        return (int) DB::table('finance_invoice_sequences')->insertGetId([
            'user_id' => $userId,
            'series_key' => $seriesKey,
            'year' => $year,
            'next_sequence' => $nextSequence,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function insertDelivery(
        int $userId,
        int $invoiceId,
        int $seriesId,
        int $revisionId,
        array $overrides = [],
    ): int {
        $now = now();

        return (int) DB::table('finance_invoice_deliveries')->insertGetId(array_merge([
            'user_id' => $userId,
            'uuid' => '018f4ca3-224d-7d8d-9f30-300000000001',
            'invoice_id' => $invoiceId,
            'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId,
            'kind' => 'invoice',
            'recipient' => 'billing@example.test',
            'message_id' => '<invoice-delivery@example.test>',
            'status' => 'pending',
            'attempts' => 0,
            'last_error_code' => null,
            'idempotency_key_hash' => hash('sha256', 'delivery-key'),
            'request_hash' => hash('sha256', 'delivery-payload'),
            'queued_at' => $now,
            'last_attempt_at' => null,
            'sent_at' => null,
            'next_retry_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertIdempotencyRecord(
        int $userId,
        string $operation,
        string $keyHash,
        array $overrides = [],
    ): int {
        $now = now();

        return (int) DB::table('finance_idempotency_records')->insertGetId(array_merge([
            'user_id' => $userId,
            'operation' => $operation,
            'key_hash' => $keyHash,
            'request_hash' => hash('sha256', 'request-payload'),
            'status' => 'completed',
            'response_status' => 200,
            'response_payload' => json_encode(['invoiceId' => 42], JSON_THROW_ON_ERROR),
            'completed_at' => $now,
            'expires_at' => $now->copy()->addDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    /** @param callable(): mixed $operation */
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
