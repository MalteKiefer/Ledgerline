<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class PaymentSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_MINOR = 99_999_999_999_999;

    public function test_payment_schema_exposes_exact_signed_ledger_columns(): void
    {
        $requiredColumns = [
            'finance_payments' => [
                'id', 'user_id', 'uuid', 'amount_minor', 'currency', 'received_at',
                'reference', 'counterparty', 'payment_method_id', 'source_type',
                'source_key', 'version', 'created_at', 'updated_at',
            ],
            'finance_payment_allocation_batches' => [
                'id', 'user_id', 'payment_id', 'idempotency_key_hash', 'request_hash',
                'created_by', 'created_at',
            ],
            'finance_payment_allocations' => [
                'id', 'user_id', 'allocation_batch_id', 'payment_id', 'invoice_id',
                'amount_minor', 'reverses_allocation_id', 'created_at',
            ],
        ];

        foreach ($requiredColumns as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
            $this->assertTrue(
                Schema::hasColumns($table, $columns),
                "Table {$table} is missing one or more required columns",
            );
        }

        foreach (['updated_at', 'deleted_at'] as $mutableTimestamp) {
            $this->assertFalse(Schema::hasColumn('finance_payment_allocation_batches', $mutableTimestamp));
            $this->assertFalse(Schema::hasColumn('finance_payment_allocations', $mutableTimestamp));
        }
    }

    public function test_payment_indexes_enforce_owner_identity_source_and_batch_idempotency(): void
    {
        $this->assertTrue(Schema::hasIndex('finance_payments', ['user_id', 'uuid'], 'unique'));
        $this->assertTrue(Schema::hasIndex(
            'finance_payments',
            ['user_id', 'source_type', 'source_key'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_payment_allocation_batches',
            ['user_id', 'idempotency_key_hash'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_payment_allocations',
            ['reverses_allocation_id'],
            'unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_payments',
            ['user_id', 'received_at'],
        ));
        $this->assertTrue(Schema::hasIndex(
            'finance_payment_allocations',
            ['user_id', 'invoice_id', 'created_at'],
        ));
    }

    public function test_payments_accept_exact_positive_and_negative_minor_units_but_reject_invalid_values(): void
    {
        $owner = User::factory()->create();

        $incoming = $this->insertPayment((int) $owner->id, self::MAX_MINOR);
        $refund = $this->insertPayment((int) $owner->id, -self::MAX_MINOR, [
            'uuid' => '018f4ca3-224d-7d8d-9f40-100000000002',
            'source_type' => 'bank_transaction',
            'source_key' => 'bank-42',
        ]);

        $this->assertSame(self::MAX_MINOR, (int) DB::table('finance_payments')->where('id', $incoming)->value('amount_minor'));
        $this->assertSame(-self::MAX_MINOR, (int) DB::table('finance_payments')->where('id', $refund)->value('amount_minor'));

        foreach ([
            ['amount_minor' => 0],
            ['amount_minor' => self::MAX_MINOR + 1],
            ['amount_minor' => -self::MAX_MINOR - 1],
            ['currency' => 'EU'],
            ['currency' => 'eur'],
            ['version' => -1],
            ['source_type' => 'bank_transaction'],
            ['source_key' => 'orphan-key'],
        ] as $index => $invalid) {
            $this->expectConstraintViolation(fn (): int => $this->insertPayment(
                (int) $owner->id,
                100,
                array_merge([
                    'uuid' => sprintf('018f4ca3-224d-7d8d-9f40-%012d', 100 + $index),
                ], $invalid),
            ));
        }
    }

    public function test_payment_and_batch_uniqueness_is_owner_scoped_and_hashes_are_exact(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $paymentId = $this->insertPayment((int) $owner->id, 11_900, [
            'source_type' => 'bank_transaction',
            'source_key' => 'bank-unique',
        ]);
        $otherPaymentId = $this->insertPayment((int) $otherOwner->id, 11_900, [
            'source_type' => 'bank_transaction',
            'source_key' => 'bank-unique',
        ]);
        $keyHash = hash('sha256', 'allocate-payment');

        $this->insertBatch((int) $owner->id, $paymentId, $keyHash);
        $this->insertBatch((int) $otherOwner->id, $otherPaymentId, $keyHash);

        $this->expectConstraintViolation(fn (): int => $this->insertPayment(
            (int) $owner->id,
            1,
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertPayment(
            (int) $owner->id,
            1,
            [
                'uuid' => '018f4ca3-224d-7d8d-9f40-100000000003',
                'source_type' => 'bank_transaction',
                'source_key' => 'bank-unique',
            ],
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertBatch(
            (int) $owner->id,
            $paymentId,
            $keyHash,
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertBatch(
            (int) $owner->id,
            $paymentId,
            str_repeat('z', 64),
        ));
    }

    public function test_allocations_require_one_owner_matched_batch_payment_and_invoice(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $paymentId = $this->insertPayment((int) $owner->id, 11_900);
        $foreignPaymentId = $this->insertPayment((int) $otherOwner->id, 11_900);
        $batchId = $this->insertBatch((int) $owner->id, $paymentId);
        $foreignBatchId = $this->insertBatch((int) $otherOwner->id, $foreignPaymentId);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 201);
        $foreignInvoiceId = $this->invoiceFixture((int) $otherOwner->id, 202);

        $allocationId = $this->insertAllocation((int) $owner->id, $batchId, $paymentId, $invoiceId, 100);
        $this->assertGreaterThan(0, $allocationId);

        $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
            (int) $owner->id,
            $batchId,
            $paymentId,
            $foreignInvoiceId,
            100,
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
            (int) $owner->id,
            $foreignBatchId,
            $paymentId,
            $invoiceId,
            100,
        ));
        $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
            (int) $owner->id,
            $batchId,
            $foreignPaymentId,
            $invoiceId,
            100,
        ));
    }

    public function test_original_allocations_require_payment_and_invoice_currency_and_sign(): void
    {
        $owner = User::factory()->create();
        $positivePaymentId = $this->insertPayment((int) $owner->id, 11_900);
        $positiveBatchId = $this->insertBatch((int) $owner->id, $positivePaymentId);
        $negativePaymentId = $this->insertPayment((int) $owner->id, -11_900, [
            'uuid' => '018f4ca3-224d-7d8d-9f40-300000000002',
        ]);
        $negativeBatchId = $this->insertBatch(
            (int) $owner->id,
            $negativePaymentId,
            hash('sha256', 'negative-allocation'),
        );
        $positiveInvoiceId = $this->invoiceFixture((int) $owner->id, 301);
        $negativeInvoiceId = $this->invoiceFixture((int) $owner->id, 302, -11_900);
        $usdInvoiceId = $this->invoiceFixture((int) $owner->id, 303, 11_900, 'USD');

        $this->insertAllocation((int) $owner->id, $positiveBatchId, $positivePaymentId, $positiveInvoiceId, 100);
        $this->insertAllocation((int) $owner->id, $negativeBatchId, $negativePaymentId, $negativeInvoiceId, -100);

        foreach ([
            [$positiveBatchId, $positivePaymentId, $positiveInvoiceId, 0],
            [$positiveBatchId, $positivePaymentId, $positiveInvoiceId, -100],
            [$positiveBatchId, $positivePaymentId, $negativeInvoiceId, 100],
            [$negativeBatchId, $negativePaymentId, $positiveInvoiceId, -100],
            [$positiveBatchId, $positivePaymentId, $usdInvoiceId, 100],
            [$positiveBatchId, $positivePaymentId, $positiveInvoiceId, self::MAX_MINOR + 1],
        ] as [$batchId, $paymentId, $invoiceId, $amountMinor]) {
            $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
                (int) $owner->id,
                $batchId,
                $paymentId,
                $invoiceId,
                $amountMinor,
            ));
        }
    }

    public function test_reversal_must_be_an_exact_once_only_inverse_of_an_original_allocation(): void
    {
        $owner = User::factory()->create();
        $paymentId = $this->insertPayment((int) $owner->id, 11_900);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 401);
        $originalBatchId = $this->insertBatch((int) $owner->id, $paymentId);
        $originalId = $this->insertAllocation(
            (int) $owner->id,
            $originalBatchId,
            $paymentId,
            $invoiceId,
            5_000,
        );
        $reversalBatchId = $this->insertBatch(
            (int) $owner->id,
            $paymentId,
            hash('sha256', 'reversal'),
        );
        $reversalId = $this->insertAllocation(
            (int) $owner->id,
            $reversalBatchId,
            $paymentId,
            $invoiceId,
            -5_000,
            $originalId,
        );

        $this->assertGreaterThan($originalId, $reversalId);

        $thirdBatchId = $this->insertBatch(
            (int) $owner->id,
            $paymentId,
            hash('sha256', 'invalid-reversal'),
        );

        foreach ([
            [-4_999, $originalId],
            [-5_000, $originalId],
            [5_000, $reversalId],
        ] as [$amountMinor, $targetId]) {
            $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
                (int) $owner->id,
                $thirdBatchId,
                $paymentId,
                $invoiceId,
                $amountMinor,
                $targetId,
            ));
        }
    }

    public function test_reversal_cannot_cross_payment_invoice_or_owner(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $paymentId = $this->insertPayment((int) $owner->id, 11_900);
        $otherPaymentId = $this->insertPayment((int) $owner->id, 11_900, [
            'uuid' => '018f4ca3-224d-7d8d-9f40-500000000002',
        ]);
        $foreignPaymentId = $this->insertPayment((int) $otherOwner->id, 11_900);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 501);
        $otherInvoiceId = $this->invoiceFixture((int) $owner->id, 502);
        $foreignInvoiceId = $this->invoiceFixture((int) $otherOwner->id, 503);
        $batchId = $this->insertBatch((int) $owner->id, $paymentId);
        $originalId = $this->insertAllocation((int) $owner->id, $batchId, $paymentId, $invoiceId, 100);

        foreach ([
            [(int) $owner->id, $otherPaymentId, $invoiceId, 'other-payment'],
            [(int) $owner->id, $paymentId, $otherInvoiceId, 'other-invoice'],
            [(int) $otherOwner->id, $foreignPaymentId, $foreignInvoiceId, 'foreign-owner'],
        ] as [$userId, $targetPaymentId, $targetInvoiceId, $key]) {
            $targetBatchId = $this->insertBatch(
                $userId,
                $targetPaymentId,
                hash('sha256', $key),
            );
            $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
                $userId,
                $targetBatchId,
                $targetPaymentId,
                $targetInvoiceId,
                -100,
                $originalId,
            ));
        }
    }

    public function test_batches_and_allocations_are_append_only_and_parent_deletion_is_restricted(): void
    {
        $owner = User::factory()->create();
        $paymentId = $this->insertPayment((int) $owner->id, 11_900);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 601);
        $batchId = $this->insertBatch((int) $owner->id, $paymentId);
        $allocationId = $this->insertAllocation((int) $owner->id, $batchId, $paymentId, $invoiceId, 100);

        $this->expectConstraintViolation(fn (): int => DB::table('finance_payment_allocation_batches')
            ->where('id', $batchId)
            ->update(['request_hash' => hash('sha256', 'mutated')]));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_payment_allocations')
            ->where('id', $allocationId)
            ->update(['amount_minor' => 99]));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_payment_allocations')
            ->where('id', $allocationId)
            ->delete());
        $this->expectConstraintViolation(fn (): int => DB::table('finance_payment_allocation_batches')
            ->where('id', $batchId)
            ->delete());
        $this->expectConstraintViolation(fn (): int => DB::table('finance_payments')
            ->where('id', $paymentId)
            ->delete());
        $this->expectConstraintViolation(fn (): int => DB::table('finance_invoices')
            ->where('id', $invoiceId)
            ->delete());
    }

    public function test_sqlite_replace_cannot_overwrite_payment_id_uuid_or_source_identity(): void
    {
        $owner = User::factory()->create();
        $paymentId = $this->insertPayment((int) $owner->id, 11_900, [
            'source_type' => 'bank_transaction',
            'source_key' => 'replace-payment-source',
        ]);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 621);
        $batchId = $this->insertBatch((int) $owner->id, $paymentId);
        $this->insertAllocation((int) $owner->id, $batchId, $paymentId, $invoiceId, 100);
        $persisted = (array) DB::table('finance_payments')->find($paymentId);

        $sameId = array_merge($persisted, [
            'amount_minor' => 200,
            'uuid' => '018f4ca3-224d-7d8d-9f40-620000000002',
            'source_key' => 'replace-payment-id',
        ]);
        $this->expectConstraintViolation(fn () => $this->replaceRow('finance_payments', $sameId));

        $sameUuid = array_merge($persisted, [
            'id' => $paymentId + 10_000,
            'amount_minor' => 200,
            'source_type' => null,
            'source_key' => null,
        ]);
        $this->expectConstraintViolation(fn () => $this->replaceRow('finance_payments', $sameUuid));

        $sameSource = array_merge($persisted, [
            'id' => $paymentId + 20_000,
            'amount_minor' => 200,
            'uuid' => '018f4ca3-224d-7d8d-9f40-620000000003',
        ]);
        $this->expectConstraintViolation(fn () => $this->replaceRow('finance_payments', $sameSource));

        $this->assertSame(11_900, (int) DB::table('finance_payments')->where('id', $paymentId)->value('amount_minor'));
        $this->assertSame(1, DB::table('finance_payments')->where('user_id', $owner->id)->count());
    }

    public function test_sqlite_replace_cannot_overwrite_batch_id_or_idempotency_identity(): void
    {
        $owner = User::factory()->create();
        $paymentId = $this->insertPayment((int) $owner->id, 11_900);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 631);
        $batchId = $this->insertBatch((int) $owner->id, $paymentId);
        $this->insertAllocation((int) $owner->id, $batchId, $paymentId, $invoiceId, 100);
        $persisted = (array) DB::table('finance_payment_allocation_batches')->find($batchId);

        $sameId = array_merge($persisted, ['request_hash' => hash('sha256', 'replace-batch-id')]);
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_payment_allocation_batches',
            $sameId,
        ));

        $sameIdempotencyKey = array_merge($persisted, [
            'id' => $batchId + 10_000,
            'request_hash' => hash('sha256', 'replace-batch-idempotency'),
        ]);
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_payment_allocation_batches',
            $sameIdempotencyKey,
        ));

        $this->assertSame(
            $persisted['request_hash'],
            DB::table('finance_payment_allocation_batches')->where('id', $batchId)->value('request_hash'),
        );
        $this->assertSame(1, DB::table('finance_payment_allocation_batches')->count());
    }

    public function test_sqlite_replace_cannot_rewrite_original_or_retarget_reversal(): void
    {
        $owner = User::factory()->create();
        $paymentId = $this->insertPayment((int) $owner->id, 11_900);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 641);
        $batchId = $this->insertBatch((int) $owner->id, $paymentId);
        $originalId = $this->insertAllocation((int) $owner->id, $batchId, $paymentId, $invoiceId, 100);
        $reversalBatchId = $this->insertBatch(
            (int) $owner->id,
            $paymentId,
            hash('sha256', 'replace-reversal-batch'),
        );
        $reversalId = $this->insertAllocation(
            (int) $owner->id,
            $reversalBatchId,
            $paymentId,
            $invoiceId,
            -100,
            $originalId,
        );
        $original = (array) DB::table('finance_payment_allocations')->find($originalId);
        $reversal = (array) DB::table('finance_payment_allocations')->find($reversalId);

        $rewrittenOriginal = array_merge($original, ['amount_minor' => 200]);
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_payment_allocations',
            $rewrittenOriginal,
        ));

        $retargetedReversal = array_merge($reversal, ['id' => $reversalId + 10_000]);
        $this->expectConstraintViolation(fn () => $this->replaceRow(
            'finance_payment_allocations',
            $retargetedReversal,
        ));

        $this->assertSame(100, (int) DB::table('finance_payment_allocations')->where('id', $originalId)->value('amount_minor'));
        $this->assertSame(-100, (int) DB::table('finance_payment_allocations')->where('id', $reversalId)->value('amount_minor'));
        $this->assertSame(2, DB::table('finance_payment_allocations')->count());
    }

    public function test_allocation_freezes_the_payment_and_invoice_currency_sign_context(): void
    {
        $owner = User::factory()->create();
        $paymentId = $this->insertPayment((int) $owner->id, 11_900);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 651);
        $batchId = $this->insertBatch((int) $owner->id, $paymentId);
        $this->insertAllocation((int) $owner->id, $batchId, $paymentId, $invoiceId, 100);
        $revisionId = (int) DB::table('finance_invoices')
            ->where('id', $invoiceId)
            ->value('current_revision_id');
        $seriesId = (int) DB::table('finance_invoices')
            ->where('id', $invoiceId)
            ->value('document_series_id');
        $replacementRevisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'revision_number' => 2,
            'previous_revision_id' => $revisionId,
            'status' => 'published',
            'snapshot' => '{}',
            'net_minor' => 11_900,
            'vat_minor' => 0,
            'gross_minor' => 11_900,
            'currency' => 'EUR',
            'published_at' => now(),
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);

        $this->assertSame(1, DB::table('finance_payments')->where('id', $paymentId)->update([
            'reference' => 'corrected-bank-reference',
            'version' => 1,
        ]));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_payments')
            ->where('id', $paymentId)
            ->update(['amount_minor' => -11_900]));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_payments')
            ->where('id', $paymentId)
            ->update(['currency' => 'USD']));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_invoices')
            ->where('id', $invoiceId)
            ->update(['current_revision_id' => $replacementRevisionId]));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_document_revisions')
            ->where('id', $revisionId)
            ->update(['currency' => 'USD']));
        $this->expectConstraintViolation(fn (): int => DB::table('finance_document_revisions')
            ->where('id', $revisionId)
            ->update(['gross_minor' => -11_900]));
    }

    public function test_delete_reinsert_cannot_retarget_allocated_parent_context(): void
    {
        $owner = User::factory()->create();
        $paymentId = $this->insertPayment((int) $owner->id, 11_900);
        $invoiceId = $this->invoiceFixture((int) $owner->id, 661);
        $batchId = $this->insertBatch((int) $owner->id, $paymentId);
        $this->insertAllocation((int) $owner->id, $batchId, $paymentId, $invoiceId, 100);
        $revisionId = (int) DB::table('finance_invoices')
            ->where('id', $invoiceId)
            ->value('current_revision_id');

        $this->expectConstraintViolation(fn () => $this->deleteAndReinsertRow(
            'finance_payments',
            $paymentId,
            ['amount_minor' => -11_900],
        ));
        $this->expectConstraintViolation(fn () => $this->deleteAndReinsertRow(
            'finance_invoices',
            $invoiceId,
            ['kind' => 'credit_note'],
        ));
        $this->expectConstraintViolation(fn () => $this->deleteAndReinsertRow(
            'finance_document_revisions',
            $revisionId,
            ['gross_minor' => -11_900, 'currency' => 'USD'],
        ));

        $this->assertSame(11_900, (int) DB::table('finance_payments')->where('id', $paymentId)->value('amount_minor'));
        $this->assertSame('invoice', DB::table('finance_invoices')->where('id', $invoiceId)->value('kind'));
        $this->assertSame('EUR', DB::table('finance_document_revisions')->where('id', $revisionId)->value('currency'));
    }

    public function test_owner_deletion_cascades_payment_ledger_with_invoice_cancellation_relations(): void
    {
        $owner = User::factory()->create();
        $originalInvoiceId = $this->invoiceFixture((int) $owner->id, 701);
        $creditInvoiceId = $this->invoiceFixture(
            (int) $owner->id,
            702,
            -11_900,
            'EUR',
            $originalInvoiceId,
        );
        $paymentId = $this->insertPayment((int) $owner->id, -11_900);
        $batchId = $this->insertBatch((int) $owner->id, $paymentId);
        $allocationId = $this->insertAllocation(
            (int) $owner->id,
            $batchId,
            $paymentId,
            $creditInvoiceId,
            -11_900,
        );
        $reversalBatchId = $this->insertBatch(
            (int) $owner->id,
            $paymentId,
            hash('sha256', 'owner-cascade-reversal'),
        );
        $this->insertAllocation(
            (int) $owner->id,
            $reversalBatchId,
            $paymentId,
            $creditInvoiceId,
            11_900,
            $allocationId,
        );

        DB::transaction(function () use ($owner): void {
            DB::table('users')->where('id', $owner->id)->delete();
        });

        foreach ([
            'finance_payment_allocations',
            'finance_payment_allocation_batches',
            'finance_payments',
            'finance_invoices',
            'finance_document_revisions',
            'finance_document_series',
        ] as $table) {
            $this->assertSame(0, DB::table($table)->where('user_id', $owner->id)->count(), "Owner cascade left rows in {$table}");
        }
    }

    public function test_postgresql_executes_integrity_immutability_cascade_and_reapply_when_configured(): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');

        if (! extension_loaded('pdo_pgsql') || ! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped(
                'Set FINANCE_TEST_PGSQL_URL and install pdo_pgsql to run the PostgreSQL execution contract.',
            );
        }

        $defaultConnection = DB::getDefaultConnection();
        $postgresConnection = 'pgsql_payment_execution';
        $schema = 'finance_payment_task3_'.bin2hex(random_bytes(8));
        config([
            "database.connections.{$postgresConnection}" => array_merge(
                config('database.connections.pgsql'),
                ['url' => $postgresUrl, 'search_path' => 'public'],
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
            $paymentMigration = require database_path('migrations/2026_08_28_110100_create_finance_payments.php');
            $foundationMigration->up();
            $invoiceMigration->up();
            $paymentMigration->up();
            DB::table('users')->insert([['id' => 1], ['id' => 2]]);

            $invoiceId = $this->invoiceFixture(1, 801);
            $foreignInvoiceId = $this->invoiceFixture(2, 802);
            $usdInvoiceId = $this->invoiceFixture(1, 804, 11_900, 'USD');
            $paymentId = $this->insertPayment(1, 11_900);
            $batchId = $this->insertBatch(1, $paymentId);
            $allocationId = $this->insertAllocation(1, $batchId, $paymentId, $invoiceId, 5_000);

            $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
                1,
                $batchId,
                $paymentId,
                $foreignInvoiceId,
                100,
            ));
            $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
                1,
                $batchId,
                $paymentId,
                $usdInvoiceId,
                100,
            ));
            $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
                1,
                $batchId,
                $paymentId,
                $invoiceId,
                -100,
            ));
            $this->expectConstraintViolation(fn (): int => DB::table('finance_payment_allocations')
                ->where('id', $allocationId)
                ->update(['amount_minor' => 4_999]));
            $this->expectConstraintViolation(fn (): int => DB::table('finance_payment_allocation_batches')
                ->where('id', $batchId)
                ->update(['request_hash' => hash('sha256', 'pgsql-mutated')]));
            $this->expectConstraintViolation(fn (): int => DB::table('finance_payments')
                ->where('id', $paymentId)
                ->update(['currency' => 'USD']));
            $revisionId = (int) DB::table('finance_invoices')
                ->where('id', $invoiceId)
                ->value('current_revision_id');
            $seriesId = (int) DB::table('finance_invoices')
                ->where('id', $invoiceId)
                ->value('document_series_id');
            $replacementRevisionId = (int) DB::table('finance_document_revisions')->insertGetId([
                'user_id' => 1,
                'document_series_id' => $seriesId,
                'revision_number' => 2,
                'previous_revision_id' => $revisionId,
                'status' => 'published',
                'snapshot' => '{}',
                'net_minor' => 11_900,
                'vat_minor' => 0,
                'gross_minor' => 11_900,
                'currency' => 'EUR',
                'published_at' => now(),
                'created_by' => 1,
                'created_at' => now(),
            ]);
            $this->expectConstraintViolation(fn (): int => DB::table('finance_invoices')
                ->where('id', $invoiceId)
                ->update(['current_revision_id' => $replacementRevisionId]));
            $this->expectConstraintViolation(fn (): int => DB::table('finance_document_revisions')
                ->where('id', $revisionId)
                ->update(['currency' => 'USD']));
            $this->expectConstraintViolation(fn () => $this->deleteAndReinsertRow(
                'finance_payments',
                $paymentId,
                ['amount_minor' => -11_900],
            ));
            $this->expectConstraintViolation(fn () => $this->deleteAndReinsertRow(
                'finance_invoices',
                $invoiceId,
                ['kind' => 'credit_note'],
            ));
            $this->expectConstraintViolation(fn () => $this->deleteAndReinsertRow(
                'finance_document_revisions',
                $revisionId,
                ['gross_minor' => -11_900, 'currency' => 'USD'],
            ));

            $reversalBatchId = $this->insertBatch(1, $paymentId, hash('sha256', 'pgsql-reversal'));
            $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
                1,
                $reversalBatchId,
                $paymentId,
                $invoiceId,
                -4_999,
                $allocationId,
            ));
            $this->insertAllocation(1, $reversalBatchId, $paymentId, $invoiceId, -5_000, $allocationId);
            $duplicateReversalBatchId = $this->insertBatch(
                1,
                $paymentId,
                hash('sha256', 'pgsql-duplicate-reversal'),
            );
            $this->expectConstraintViolation(fn (): int => $this->insertAllocation(
                1,
                $duplicateReversalBatchId,
                $paymentId,
                $invoiceId,
                -5_000,
                $allocationId,
            ));

            $creditInvoiceId = $this->invoiceFixture(1, 803, -11_900, 'EUR', $invoiceId);
            $refundId = $this->insertPayment(1, -11_900, [
                'uuid' => '018f4ca3-224d-7d8d-9f40-800000000002',
            ]);
            $refundBatchId = $this->insertBatch(1, $refundId, hash('sha256', 'pgsql-refund'));
            $this->insertAllocation(1, $refundBatchId, $refundId, $creditInvoiceId, -11_900);

            $raceInvoiceId = $this->invoiceFixture(1, 805);
            $racePaymentId = $this->insertPayment(1, 11_900, [
                'uuid' => '018f4ca3-224d-7d8d-9f40-800000000004',
            ]);
            $raceBatchId = $this->insertBatch(1, $racePaymentId, hash('sha256', 'pgsql-race'));
            $this->assertPostgresAllocationSerializesParentUpdate(
                $postgresUrl,
                $schema,
                $raceBatchId,
                $racePaymentId,
                $raceInvoiceId,
            );

            DB::transaction(function (): void {
                DB::table('users')->where('id', 1)->delete();
            });

            foreach (['finance_payments', 'finance_payment_allocation_batches', 'finance_payment_allocations', 'finance_invoices'] as $table) {
                $this->assertSame(0, DB::table($table)->where('user_id', 1)->count());
            }

            $paymentMigration->down();
            $this->assertFalse(Schema::hasTable('finance_payments'));
            $this->assertFalse(Schema::hasTable('finance_payment_allocation_batches'));
            $this->assertFalse(Schema::hasTable('finance_payment_allocations'));

            $paymentMigration->up();
            $this->assertTrue(Schema::hasTable('finance_payments'));
            $this->assertTrue(Schema::hasTable('finance_payment_allocation_batches'));
            $this->assertTrue(Schema::hasTable('finance_payment_allocations'));

            $foreignPaymentId = $this->insertPayment(2, 100, [
                'uuid' => '018f4ca3-224d-7d8d-9f40-800000000003',
            ]);
            $this->assertGreaterThan(0, $foreignPaymentId);
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

    public function test_postgresql_ddl_uses_checks_deferred_owner_relations_and_immutable_triggers(): void
    {
        $defaultConnection = DB::getDefaultConnection();
        $postgresConnection = 'pgsql_payment_ddl';
        config([
            "database.connections.{$postgresConnection}" => array_merge(
                config('database.connections.pgsql'),
                ['database' => 'ledgerline_ddl_inspection'],
            ),
        ]);
        DB::setDefaultConnection($postgresConnection);
        Schema::clearResolvedInstance('db.schema');

        try {
            $migration = require database_path('migrations/2026_08_28_110100_create_finance_payments.php');
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
            'finance_payment_batches_owner_payment_foreign',
            'finance_payment_allocations_owner_batch_payment_foreign',
            'finance_payment_allocations_owner_payment_foreign',
            'finance_payment_allocations_owner_invoice_foreign',
            'finance_payment_allocations_owner_reversal_foreign',
        ] as $constraint) {
            $this->assertMatchesRegularExpression(
                "/{$constraint}.*on delete no action deferrable initially deferred/",
                $ddl,
            );
        }

        foreach ([
            'finance_payments_user_id_foreign',
            'finance_payment_allocation_batches_user_id_foreign',
            'finance_payment_allocations_user_id_foreign',
        ] as $constraint) {
            $this->assertMatchesRegularExpression("/{$constraint}.*on delete cascade/", $ddl);
        }

        $this->assertStringContainsString('finance_payments_amount_check', $ddl);
        $this->assertStringContainsString('finance_payments_currency_check', $ddl);
        $this->assertStringContainsString('finance_payment_allocations_amount_check', $ddl);
        $this->assertStringContainsString('finance_payment_allocation_guard', $ddl);
        $this->assertMatchesRegularExpression(
            '/finance_invoices as invoice.*for share.*finance_document_revisions.*for share.*finance_payments.*for share/s',
            $ddl,
        );
        $this->assertStringContainsString('finance_payment_ledger_immutable_guard', $ddl);
        $this->assertStringContainsString('finance_payment_allocated_parent_delete_guard', $ddl);
        foreach ([
            'finance_payments_allocated_context_delete_guard',
            'finance_invoices_allocated_context_delete_guard',
            'finance_document_revisions_allocated_context_delete_guard',
        ] as $trigger) {
            $this->assertStringContainsString($trigger, $ddl);
            $this->assertStringContainsString("drop trigger if exists {$trigger}", $downDdl);
        }
        $this->assertMatchesRegularExpression(
            '/create table "finance_payments".*"amount_minor" bigint/s',
            $ddl,
        );
        $this->assertMatchesRegularExpression(
            '/create table "finance_payment_allocations".*"amount_minor" bigint/s',
            $ddl,
        );
        $this->assertStringNotContainsString(' glob ', $ddl);

        foreach ([
            'finance_payment_allocations',
            'finance_payment_allocation_batches',
            'finance_payments',
        ] as $table) {
            $this->assertStringContainsString("drop table if exists \"{$table}\"", $downDdl);
        }
        $this->assertStringContainsString('drop function if exists finance_payment_allocation_guard()', $downDdl);
        $this->assertStringContainsString('drop function if exists finance_payment_allocated_context_guard()', $downDdl);
        $this->assertStringContainsString('drop function if exists finance_payment_allocated_parent_delete_guard()', $downDdl);
        $this->assertStringContainsString('drop function if exists finance_payment_ledger_immutable_guard()', $downDdl);
    }

    public function test_migration_can_be_rolled_back_and_applied_again(): void
    {
        $migration = require database_path('migrations/2026_08_28_110100_create_finance_payments.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('finance_payment_allocations'));
        $this->assertFalse(Schema::hasTable('finance_payment_allocation_batches'));
        $this->assertFalse(Schema::hasTable('finance_payments'));

        $migration->up();

        $this->assertTrue(Schema::hasTable('finance_payments'));
        $this->assertTrue(Schema::hasTable('finance_payment_allocation_batches'));
        $this->assertTrue(Schema::hasTable('finance_payment_allocations'));
    }

    private function invoiceFixture(
        int $userId,
        int $suffix,
        int $grossMinor = 11_900,
        string $currency = 'EUR',
        ?int $cancelsInvoiceId = null,
    ): int {
        $now = now();
        $seriesId = (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => $userId,
            'uuid' => sprintf('018f4ca3-224d-7d8d-9f10-%012d', $suffix),
            'document_type' => 'invoice',
            'status' => 'finalized',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $userId,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'previous_revision_id' => null,
            'status' => 'published',
            'snapshot' => '{}',
            'net_minor' => $grossMinor,
            'vat_minor' => 0,
            'gross_minor' => $grossMinor,
            'currency' => $currency,
            'published_at' => $now,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        return (int) DB::table('finance_invoices')->insertGetId([
            'user_id' => $userId,
            'uuid' => sprintf('018f4ca3-224d-7d8d-9f20-%012d', $suffix),
            'document_series_id' => $seriesId,
            'current_revision_id' => $revisionId,
            'kind' => $grossMinor < 0 ? 'credit_note' : 'invoice',
            'number' => sprintf('%s-2026-%04d', $grossMinor < 0 ? 'GS' : 'RE', $suffix),
            'year' => 2026,
            'sequence' => $suffix,
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'workflow_status' => 'finalized',
            'finalized_at' => $now,
            'allocated_minor' => 0,
            'open_minor' => $grossMinor,
            'version' => 0,
            'cancels_invoice_id' => $cancelsInvoiceId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function insertPayment(int $userId, int $amountMinor, array $overrides = []): int
    {
        $now = now();

        return (int) DB::table('finance_payments')->insertGetId(array_merge([
            'user_id' => $userId,
            'uuid' => '018f4ca3-224d-7d8d-9f40-100000000001',
            'amount_minor' => $amountMinor,
            'currency' => 'EUR',
            'received_at' => '2026-08-28 10:30:00',
            'reference' => 'RE-2026-0001',
            'counterparty' => 'ACME GmbH',
            'payment_method_id' => null,
            'source_type' => null,
            'source_key' => null,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    private function insertBatch(
        int $userId,
        int $paymentId,
        ?string $keyHash = null,
    ): int {
        return (int) DB::table('finance_payment_allocation_batches')->insertGetId([
            'user_id' => $userId,
            'payment_id' => $paymentId,
            'idempotency_key_hash' => $keyHash ?? hash('sha256', "batch-{$userId}-{$paymentId}"),
            'request_hash' => hash('sha256', "request-{$userId}-{$paymentId}-{$keyHash}"),
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    private function insertAllocation(
        int $userId,
        int $batchId,
        int $paymentId,
        int $invoiceId,
        int $amountMinor,
        ?int $reversesAllocationId = null,
    ): int {
        return (int) DB::table('finance_payment_allocations')->insertGetId([
            'user_id' => $userId,
            'allocation_batch_id' => $batchId,
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount_minor' => $amountMinor,
            'reverses_allocation_id' => $reversesAllocationId,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function replaceRow(string $table, array $row): void
    {
        $columns = array_keys($row);
        $quotedColumns = implode(', ', array_map(
            static fn (string $column): string => '"'.$column.'"',
            $columns,
        ));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        DB::statement(
            "INSERT OR REPLACE INTO \"{$table}\" ({$quotedColumns}) VALUES ({$placeholders})",
            array_values($row),
        );
    }

    /** @param array<string, mixed> $changes */
    private function deleteAndReinsertRow(string $table, int $id, array $changes): void
    {
        DB::transaction(function () use ($table, $id, $changes): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET CONSTRAINTS ALL DEFERRED');
            }

            $row = (array) DB::table($table)->find($id);

            DB::table($table)->where('id', $id)->delete();
            DB::table($table)->insert(array_merge($row, $changes));
        });
    }

    private function assertPostgresAllocationSerializesParentUpdate(
        string $postgresUrl,
        string $schema,
        int $batchId,
        int $paymentId,
        int $invoiceId,
    ): void {
        $process = null;
        DB::beginTransaction();

        try {
            $this->insertAllocation(1, $batchId, $paymentId, $invoiceId, 100);
            $process = $this->startPostgresCurrencyUpdateProcess($postgresUrl, $schema, $paymentId);
            $this->waitForProcessMarker($process, 'ready=');
            preg_match('/ready=(\d+)/', $process->getOutput(), $matches);
            $this->assertArrayHasKey(1, $matches, $process->getOutput());
            $this->waitForPostgresLock($process, (int) $matches[1]);
            $this->assertTrue(
                $process->isRunning(),
                'The concurrent parent update did not wait for allocation validation locks: '.$process->getErrorOutput(),
            );

            DB::commit();
            $process->wait();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($process instanceof Process && $process->isRunning()) {
                $process->stop(1.0);
            }
        }

        $this->assertInstanceOf(Process::class, $process);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
        $this->assertStringContainsString('sqlstate=23514', strtolower($process->getOutput()));
        $this->assertSame(
            'EUR',
            DB::table('finance_payments')->where('id', $paymentId)->value('currency'),
        );
    }

    private function startPostgresCurrencyUpdateProcess(
        string $postgresUrl,
        string $schema,
        int $paymentId,
    ): Process {
        $script = <<<'PHP'
            $url = getenv('FINANCE_TEST_PGSQL_URL');
            $schema = getenv('FINANCE_TEST_PGSQL_SCHEMA');
            $paymentId = (int) getenv('FINANCE_TEST_PAYMENT_ID');
            $parts = is_string($url) ? parse_url($url) : false;

            if (! is_array($parts) || ! isset($parts['host'], $parts['path'])) {
                fwrite(STDERR, 'invalid-postgres-url');
                exit(90);
            }

            $query = [];
            parse_str($parts['query'] ?? '', $query);
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $parts['host'],
                (int) ($parts['port'] ?? 5432),
                rawurldecode(ltrim($parts['path'], '/')),
            );

            if (isset($query['sslmode'])) {
                $dsn .= ';sslmode='.$query['sslmode'];
            }

            $pdo = new PDO(
                $dsn,
                rawurldecode($parts['user'] ?? ''),
                rawurldecode($parts['pass'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
            $pdo->exec('SET search_path TO "'.str_replace('"', '""', (string) $schema).'"');
            $pdo->exec("SET statement_timeout TO '10s'");
            echo 'ready='.$pdo->query('SELECT pg_backend_pid()')->fetchColumn()."\n";
            flush();

            try {
                $pdo->beginTransaction();
                $statement = $pdo->prepare('UPDATE finance_payments SET currency = ? WHERE id = ?');
                $statement->execute(['USD', $paymentId]);
                $pdo->commit();
                echo "unexpected-success\n";
                exit(2);
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                echo 'sqlstate='.$exception->getCode()."\n";
                exit($exception->getCode() === '23514' ? 0 : 3);
            }
            PHP;

        $process = new Process(
            [PHP_BINARY, '-r', $script],
            base_path(),
            [
                'FINANCE_TEST_PGSQL_URL' => $postgresUrl,
                'FINANCE_TEST_PGSQL_SCHEMA' => $schema,
                'FINANCE_TEST_PAYMENT_ID' => (string) $paymentId,
            ],
            null,
            15,
        );
        $process->start();

        return $process;
    }

    private function waitForProcessMarker(Process $process, string $marker): void
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            if (str_contains($process->getOutput(), $marker)) {
                return;
            }

            if (! $process->isRunning()) {
                break;
            }

            usleep(20_000);
        }

        $this->fail(
            "Concurrent PostgreSQL worker did not emit {$marker}: "
            .$process->getErrorOutput().$process->getOutput(),
        );
    }

    private function waitForPostgresLock(Process $process, int $backendPid): void
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $waitType = DB::table('pg_catalog.pg_stat_activity')
                ->where('pid', $backendPid)
                ->value('wait_event_type');

            if ($waitType === 'Lock') {
                $this->addToAssertionCount(1);

                return;
            }

            if (! $process->isRunning()) {
                break;
            }

            usleep(20_000);
        }

        $this->fail(
            'Concurrent PostgreSQL worker did not wait on a row lock: '
            .$process->getErrorOutput().$process->getOutput(),
        );
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
