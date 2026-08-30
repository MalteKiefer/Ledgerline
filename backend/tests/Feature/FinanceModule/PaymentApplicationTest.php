<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Payments\AllocatePayment;
use App\Modules\Finance\Application\Commands\Payments\RecordPayment;
use App\Modules\Finance\Application\Commands\Payments\ReversePaymentAllocation;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Payments\AllocatePaymentData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationLineData;
use App\Modules\Finance\Application\DTOs\Payments\PaymentId;
use App\Modules\Finance\Application\DTOs\Payments\RecordPaymentData;
use App\Modules\Finance\Application\Ports\PaymentRepository;
use App\Modules\Finance\Application\Queries\SuggestPaymentAllocations;
use App\Modules\Finance\Infrastructure\Persistence\EloquentPaymentRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;
use TypeError;

final class PaymentApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(PaymentRepository::class, EloquentPaymentRepository::class);
    }

    public function test_record_payment_preserves_exact_signed_minor_units_and_replays_one_source(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $data = new RecordPaymentData(
            15_000,
            'EUR',
            new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
            'RE-2026-0042',
            'Customer GmbH',
            null,
            'bank_import',
            'statement-17:row-4',
        );
        $key = new IdempotencyKey('record-import-row-4');

        $first = app(RecordPayment::class)->handle($data, $key);
        $replay = app(RecordPayment::class)->handle($data, $key);

        $this->assertSame($first->id->value, $replay->id->value);
        $this->assertSame(15_000, $first->amountMinor);
        $this->assertSame(15_000, $first->unappliedMinor);
        $this->assertSame('EUR', $first->currency);
        $this->assertSame('bank_import', $first->sourceType);
        $this->assertSame('statement-17:row-4', $first->sourceKey);
        $this->assertSame(0, $first->version);
        $this->assertDatabaseCount('finance_payments', 1);
    }

    public function test_same_source_and_canonical_payload_replay_with_a_new_transport_key(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $data = new RecordPaymentData(
            15_000,
            'EUR',
            new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
            'RE-2026-0042',
            'Customer GmbH',
            sourceType: 'bank_import',
            sourceKey: 'statement-17:row-5',
        );

        $first = app(RecordPayment::class)->handle(
            $data,
            new IdempotencyKey('transport-attempt-one'),
        );
        $replay = app(RecordPayment::class)->handle(
            $data,
            new IdempotencyKey('transport-attempt-two'),
        );

        $this->assertSame($first->id->value, $replay->id->value);
        $this->assertDatabaseCount('finance_payments', 1);
        $this->assertSame(2, DB::table('finance_idempotency_records')
            ->where('operation', 'payment.record')
            ->where('status', 'completed')
            ->count());
    }

    public function test_source_replay_canonicalizes_equivalent_received_at_offsets(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $first = app(RecordPayment::class)->handle(new RecordPaymentData(
            15_000,
            'EUR',
            new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
            'RE-2026-0042',
            'Customer GmbH',
            sourceType: 'bank_import',
            sourceKey: 'statement-17:row-7',
        ), new IdempotencyKey('source-offset-first'));

        $replay = app(RecordPayment::class)->handle(new RecordPaymentData(
            15_000,
            'EUR',
            new DateTimeImmutable('2026-08-29T12:15:00+02:00'),
            'RE-2026-0042',
            'Customer GmbH',
            sourceType: 'bank_import',
            sourceKey: 'statement-17:row-7',
        ), new IdempotencyKey('source-offset-retry'));

        $this->assertSame($first->id->value, $replay->id->value);
        $this->assertDatabaseCount('finance_payments', 1);
    }

    public function test_same_source_with_changed_canonical_payment_payload_conflicts_stably(): void
    {
        $owner = User::factory()->create();
        $firstMethod = $this->paymentMethod($owner, 'First import account');
        $secondMethod = $this->paymentMethod($owner, 'Second import account');
        $this->actingAs($owner);
        $sourceType = 'bank_import';
        $sourceKey = 'statement-17:row-6';
        $base = new RecordPaymentData(
            15_000,
            'EUR',
            new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
            'RE-2026-0042',
            'Customer GmbH',
            $firstMethod,
            $sourceType,
            $sourceKey,
        );
        app(RecordPayment::class)->handle($base, new IdempotencyKey('source-payload-original'));
        $changedPayloads = [
            'amount' => new RecordPaymentData(15_001, 'EUR', $base->receivedAt, $base->reference, $base->counterparty, $firstMethod, $sourceType, $sourceKey),
            'currency' => new RecordPaymentData(15_000, 'USD', $base->receivedAt, $base->reference, $base->counterparty, $firstMethod, $sourceType, $sourceKey),
            'date' => new RecordPaymentData(15_000, 'EUR', new DateTimeImmutable('2026-08-29T10:15:01+00:00'), $base->reference, $base->counterparty, $firstMethod, $sourceType, $sourceKey),
            'reference' => new RecordPaymentData(15_000, 'EUR', $base->receivedAt, 'RE-2026-0043', $base->counterparty, $firstMethod, $sourceType, $sourceKey),
            'counterparty' => new RecordPaymentData(15_000, 'EUR', $base->receivedAt, $base->reference, 'Other GmbH', $firstMethod, $sourceType, $sourceKey),
            'payment_method' => new RecordPaymentData(15_000, 'EUR', $base->receivedAt, $base->reference, $base->counterparty, $secondMethod, $sourceType, $sourceKey),
        ];

        foreach ($changedPayloads as $field => $changed) {
            try {
                app(RecordPayment::class)->handle(
                    $changed,
                    new IdempotencyKey('source-payload-changed-'.$field),
                );
                $this->fail("A changed {$field} was accepted for an existing payment source.");
            } catch (DomainException $exception) {
                $this->assertSame('payment_source_conflict', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('finance_payments', 1);
        $this->assertDatabaseCount('finance_idempotency_records', 1);
    }

    public function test_record_payment_rejects_zero_minor_units_before_persistence(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RecordPaymentData(0, 'EUR', new DateTimeImmutable('2026-08-29T10:15:00+00:00'));
    }

    public function test_record_payment_rejects_float_amounts_at_the_strict_boundary(): void
    {
        $this->expectException(TypeError::class);

        new RecordPaymentData(119.0, 'EUR', new DateTimeImmutable('2026-08-29T10:15:00+00:00'));
    }

    public function test_record_payment_rejects_non_iso_currency_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RecordPaymentData(11_900, 'eur', new DateTimeImmutable('2026-08-29T10:15:00+00:00'));
    }

    public function test_record_payment_rejects_minor_units_outside_database_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RecordPaymentData(
            100_000_000_000_000,
            'EUR',
            new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
        );
    }

    public function test_allocation_line_rejects_minor_units_outside_database_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AllocationLineData(new InvoiceId(1), -100_000_000_000_000);
    }

    public function test_record_payment_rejects_a_foreign_payment_method_without_writing(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $methodId = DB::table('payment_methods')->insertGetId([
            'user_id' => $foreign->id,
            'type' => 'bank',
            'name' => 'Foreign account',
            'business' => true,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($owner);

        try {
            app(RecordPayment::class)->handle(new RecordPaymentData(
                -11_900,
                'EUR',
                new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
                paymentMethodId: $methodId,
            ), new IdempotencyKey('foreign-payment-method'));
            $this->fail('A payment was recorded against another owner payment method.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('finance_payments', 0);
        $this->assertDatabaseCount('finance_idempotency_records', 0);
    }

    public function test_record_payment_rejects_a_foreign_bank_transaction_source(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $methodId = DB::table('payment_methods')->insertGetId([
            'user_id' => $foreign->id,
            'type' => 'bank',
            'name' => 'Foreign account',
            'business' => true,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $transactionId = DB::table('bank_transactions')->insertGetId([
            'user_id' => $foreign->id,
            'payment_method_id' => $methodId,
            'date' => '2026-08-29',
            'amount' => '119.00',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($owner);

        try {
            app(RecordPayment::class)->handle(new RecordPaymentData(
                11_900,
                'EUR',
                new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
                sourceType: 'bank_transaction',
                sourceKey: (string) $transactionId,
            ), new IdempotencyKey('foreign-bank-source'));
            $this->fail('A payment was recorded from another owner bank transaction.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('finance_payments', 0);
    }

    public function test_exact_record_replay_precedes_mutable_account_and_source_preflight(): void
    {
        $owner = User::factory()->create();
        $methodId = DB::table('payment_methods')->insertGetId([
            'user_id' => $owner->id,
            'type' => 'bank',
            'name' => 'Import account',
            'business' => true,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $transactionId = DB::table('bank_transactions')->insertGetId([
            'user_id' => $owner->id,
            'payment_method_id' => $methodId,
            'date' => '2026-08-29',
            'amount' => '119.00',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($owner);
        $data = new RecordPaymentData(
            11_900,
            'EUR',
            new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
            paymentMethodId: $methodId,
            sourceType: 'bank_transaction',
            sourceKey: (string) $transactionId,
        );
        $key = new IdempotencyKey('replay-deleted-payment-source');
        $first = app(RecordPayment::class)->handle($data, $key);
        DB::table('bank_transactions')->where('id', $transactionId)->update(['deleted_at' => now()]);
        DB::table('payment_methods')->where('id', $methodId)->update(['deleted_at' => now()]);

        $replay = app(RecordPayment::class)->handle($data, $key);

        $this->assertSame($first->id->value, $replay->id->value);
        $this->assertDatabaseCount('finance_payments', 1);
    }

    public function test_partial_then_final_allocation_projects_effective_status_and_append_only_activity(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $invoice = $this->invoice($owner, 11_900, 'EUR', 'sent', '000000000101');
        $payment = app(RecordPayment::class)->handle(new RecordPaymentData(
            11_900,
            'EUR',
            new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
            'RE-2026-0101',
            'Customer GmbH',
        ), new IdempotencyKey('record-partial-payment'));

        $partial = app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment->id,
            [new AllocationLineData($invoice, 5_000)],
        ), new IdempotencyKey('allocate-partial-5000'));
        $paid = app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment->id,
            [new AllocationLineData($invoice, 6_900)],
        ), new IdempotencyKey('allocate-final-6900'));

        $this->assertSame(5_000, $partial->payment->allocatedMinor);
        $this->assertSame(6_900, $partial->payment->unappliedMinor);
        $this->assertSame('partially_paid', $partial->invoices[0]->status);
        $this->assertSame(6_900, $partial->invoices[0]->openMinor);
        $this->assertSame(11_900, $paid->payment->allocatedMinor);
        $this->assertSame(0, $paid->payment->unappliedMinor);
        $this->assertSame('paid', $paid->invoices[0]->status);
        $this->assertSame(0, $paid->invoices[0]->openMinor);
        $this->assertDatabaseCount('finance_payment_allocation_batches', 2);
        $this->assertDatabaseCount('finance_payment_allocations', 2);
        $this->assertSame(2, DB::table('finance_document_activities')
            ->where('type', 'payment.allocated')->count());
    }

    public function test_one_payment_allocates_exact_amounts_across_multiple_invoices(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $firstInvoice = $this->invoice(
            $owner, 11_900, 'EUR', 'sent', '000000000102', 'RE-2026-0102',
        );
        $secondInvoice = $this->invoice(
            $owner, 8_100, 'EUR', 'sent', '000000000103', 'RE-2026-0103',
        );
        $payment = $this->recordPayment(25_000, 'multi-invoice-payment');

        $result = app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment,
            [
                new AllocationLineData($secondInvoice, 8_100),
                new AllocationLineData($firstInvoice, 11_900),
            ],
        ), new IdempotencyKey('allocate-two-invoices'));

        $this->assertSame(20_000, $result->payment->allocatedMinor);
        $this->assertSame(5_000, $result->payment->unappliedMinor);
        $this->assertSame(
            [$secondInvoice->value, $firstInvoice->value],
            array_map(static fn ($invoice): int => $invoice->id->value, $result->invoices),
        );
        $this->assertSame(['paid', 'paid'], array_column($result->invoices, 'status'));
        $this->assertDatabaseCount('finance_payment_allocations', 2);
    }

    public function test_allocation_idempotency_hash_canonicalizes_line_order(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $firstInvoice = $this->invoice(
            $owner, 11_900, 'EUR', 'sent', '000000000117', 'RE-2026-0117',
        );
        $secondInvoice = $this->invoice(
            $owner, 8_100, 'EUR', 'sent', '000000000118', 'RE-2026-0118',
        );
        $payment = $this->recordPayment(20_000, 'canonical-line-order');
        $key = new IdempotencyKey('canonical-line-order-key');

        $first = app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment,
            [
                new AllocationLineData($secondInvoice, 8_100),
                new AllocationLineData($firstInvoice, 11_900),
            ],
        ), $key);
        $replay = app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment,
            [
                new AllocationLineData($firstInvoice, 11_900),
                new AllocationLineData($secondInvoice, 8_100),
            ],
        ), $key);

        $this->assertSame($first->batchId, $replay->batchId);
        $this->assertSame(
            array_map(static fn ($id): int => $id->value, $first->allocationIds),
            array_map(static fn ($id): int => $id->value, $replay->allocationIds),
        );
        $this->assertDatabaseCount('finance_payment_allocation_batches', 1);
        $this->assertDatabaseCount('finance_payment_allocations', 2);
        $this->assertSame(2, DB::table('finance_document_activities')
            ->where('type', 'payment.allocated')->count());
    }

    public function test_overpayment_remains_unapplied_instead_of_overallocating_invoice(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $invoice = $this->invoice($owner, 11_900, 'EUR', 'sent', '000000000104');
        $payment = $this->recordPayment(15_000, 'overpayment');

        $result = app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment,
            [new AllocationLineData($invoice, 11_900)],
        ), new IdempotencyKey('allocate-overpayment'));

        $this->assertSame(3_100, $result->payment->unappliedMinor);
        $this->assertSame(0, $result->invoices[0]->openMinor);
        $this->assertSame('paid', $result->invoices[0]->status);
    }

    public function test_negative_refund_settles_negative_credit_note_with_signed_entry(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $credit = $this->invoice(
            $owner,
            -11_900,
            'EUR',
            'sent',
            '000000000105',
            'GS-2026-0105',
            kind: 'credit_note',
        );
        $payment = $this->recordPayment(-11_900, 'refund');

        $result = app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment,
            [new AllocationLineData($credit, -11_900)],
        ), new IdempotencyKey('allocate-refund'));

        $this->assertSame(0, $result->payment->unappliedMinor);
        $this->assertSame(-11_900, $result->payment->allocatedMinor);
        $this->assertSame(0, $result->invoices[0]->openMinor);
        $this->assertSame('paid', $result->invoices[0]->status);
        $this->assertSame(-11_900, DB::table('finance_payment_allocations')->value('amount_minor'));
    }

    public function test_allocation_rejects_an_invoice_with_a_finalized_cancellation_document(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $invoice = $this->invoice(
            $owner, 11_900, 'EUR', 'sent', '000000000106', 'RE-2026-0106',
        );
        $this->invoice(
            $owner,
            -11_900,
            'EUR',
            'finalized',
            '000000000107',
            'GS-2026-0107',
            kind: 'credit_note',
            cancels: $invoice,
        );
        $payment = $this->recordPayment(11_900, 'cancelled-target');

        try {
            app(AllocatePayment::class)->handle(new AllocatePaymentData(
                $payment,
                [new AllocationLineData($invoice, 11_900)],
            ), new IdempotencyKey('allocate-cancelled-target'));
            $this->fail('A cancelled invoice accepted a payment allocation.');
        } catch (DomainException $exception) {
            $this->assertSame('allocation_invoice_cancelled', $exception->getMessage());
        }

        $this->assertDatabaseCount('finance_payment_allocation_batches', 0);
        $this->assertDatabaseCount('finance_payment_allocations', 0);
        $this->assertDatabaseMissing('finance_document_activities', ['type' => 'payment.allocated']);
    }

    public function test_allocation_rejects_owner_currency_state_sign_and_magnitude_mismatches_atomically(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $this->actingAs($owner);
        $payment = $this->recordPayment(11_900, 'invalid-targets');
        $foreignInvoice = $this->invoice(
            $foreign, 11_900, 'EUR', 'sent', '000000000108', 'RE-2026-0108',
        );
        $usdInvoice = $this->invoice(
            $owner, 11_900, 'USD', 'sent', '000000000109', 'RE-2026-0109',
        );
        $draft = $this->invoice(
            $owner, 11_900, 'EUR', 'draft', '000000000110', 'RE-2026-0110',
        );
        $eligible = $this->invoice(
            $owner, 11_900, 'EUR', 'sent', '000000000111', 'RE-2026-0111',
        );

        try {
            app(AllocatePayment::class)->handle(new AllocatePaymentData(
                $payment,
                [new AllocationLineData($foreignInvoice, 100)],
            ), new IdempotencyKey('allocate-foreign'));
            $this->fail('A foreign invoice accepted an allocation.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
        $this->assertAllocationCode($payment, $usdInvoice, 100, 'allocation_currency_mismatch', 'currency');
        $this->assertAllocationCode($payment, $draft, 100, 'allocation_invoice_not_finalized', 'draft');
        $this->assertAllocationCode($payment, $eligible, -100, 'allocation_sign_mismatch', 'sign');
        $this->assertAllocationCode($payment, $eligible, 12_000, 'allocation_exceeds_payment', 'magnitude');

        $this->assertDatabaseCount('finance_payment_allocation_batches', 0);
        $this->assertDatabaseCount('finance_payment_allocations', 0);
        $this->assertDatabaseMissing('finance_document_activities', ['type' => 'payment.allocated']);
    }

    public function test_allocation_rejects_a_stale_payment_version_without_appending_history(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $invoice = $this->invoice(
            $owner, 11_900, 'EUR', 'sent', '000000000112', 'RE-2026-0112',
        );
        $payment = $this->recordPayment(11_900, 'version-cas');

        app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment,
            [new AllocationLineData($invoice, 100)],
            expectedVersion: 0,
        ), new IdempotencyKey('version-first'));
        try {
            app(AllocatePayment::class)->handle(new AllocatePaymentData(
                $payment,
                [new AllocationLineData($invoice, 100)],
                expectedVersion: 0,
            ), new IdempotencyKey('version-stale'));
            $this->fail('A stale payment version appended an allocation.');
        } catch (DomainException $exception) {
            $this->assertSame('payment_version_conflict', $exception->getMessage());
        }

        $this->assertDatabaseCount('finance_payment_allocation_batches', 1);
        $this->assertDatabaseCount('finance_payment_allocations', 1);
        $this->assertSame(1, DB::table('finance_document_activities')
            ->where('type', 'payment.allocated')->count());
    }

    public function test_exact_allocation_replay_precedes_version_cas_but_changed_payload_conflicts(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $invoice = $this->invoice(
            $owner, 11_900, 'EUR', 'sent', '000000000116', 'RE-2026-0116',
        );
        $payment = $this->recordPayment(11_900, 'allocation-replay');
        $key = new IdempotencyKey('allocation-replay-key');
        $data = new AllocatePaymentData(
            $payment,
            [new AllocationLineData($invoice, 5_000)],
            expectedVersion: 0,
        );

        $first = app(AllocatePayment::class)->handle($data, $key);
        $replay = app(AllocatePayment::class)->handle($data, $key);

        $this->assertSame($first->batchId, $replay->batchId);
        $this->assertSame($first->allocationIds[0]->value, $replay->allocationIds[0]->value);
        try {
            app(AllocatePayment::class)->handle(new AllocatePaymentData(
                $payment,
                [new AllocationLineData($invoice, 4_000)],
                expectedVersion: 0,
            ), $key);
            $this->fail('A payment allocation idempotency key was reused for a changed amount.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }
        $this->assertDatabaseCount('finance_payment_allocation_batches', 1);
        $this->assertDatabaseCount('finance_payment_allocations', 1);
        $this->assertSame(1, DB::table('finance_document_activities')
            ->where('type', 'payment.allocated')->count());
    }

    public function test_reversal_appends_exact_negation_once_and_replays_without_duplicate_activity(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $invoice = $this->invoice(
            $owner, 11_900, 'EUR', 'sent', '000000000113', 'RE-2026-0113',
        );
        $payment = $this->recordPayment(11_900, 'reverse-once');
        $allocated = app(AllocatePayment::class)->handle(new AllocatePaymentData(
            $payment,
            [new AllocationLineData($invoice, 5_000)],
            expectedVersion: 0,
        ), new IdempotencyKey('reverse-source'));
        $key = new IdempotencyKey('reverse-allocation-once');

        $reversed = app(ReversePaymentAllocation::class)->handle(
            $allocated->allocationIds[0],
            $key,
            expectedPaymentVersion: 1,
        );
        $replay = app(ReversePaymentAllocation::class)->handle(
            $allocated->allocationIds[0],
            $key,
            expectedPaymentVersion: 1,
        );

        $this->assertSame($reversed->batchId, $replay->batchId);
        $this->assertSame(0, $reversed->payment->allocatedMinor);
        $this->assertSame(11_900, $reversed->payment->unappliedMinor);
        $this->assertSame('sent', $reversed->invoices[0]->status);
        $this->assertSame(11_900, $reversed->invoices[0]->openMinor);
        $entry = DB::table('finance_payment_allocations')
            ->whereNotNull('reverses_allocation_id')
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame(-5_000, $entry->amount_minor);
        $this->assertSame($allocated->allocationIds[0]->value, $entry->reverses_allocation_id);
        $this->assertSame(1, DB::table('finance_document_activities')
            ->where('type', 'payment.allocation_reversed')->count());
        try {
            app(ReversePaymentAllocation::class)->handle(
                $allocated->allocationIds[0],
                new IdempotencyKey('reverse-allocation-twice'),
                expectedPaymentVersion: 2,
            );
            $this->fail('An allocation was reversed twice.');
        } catch (DomainException $exception) {
            $this->assertSame('allocation_already_reversed', $exception->getMessage());
        }
        $this->assertDatabaseCount('finance_payment_allocations', 2);
    }

    public function test_equal_suggestions_are_deterministic_ambiguous_and_never_auto_applied(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $first = $this->invoice(
            $owner, 11_900, 'EUR', 'sent', '000000000114', 'RE-2026-0114',
        );
        $second = $this->invoice(
            $owner, 11_900, 'EUR', 'sent', '000000000115', 'RE-2026-0115',
        );
        $payment = app(RecordPayment::class)->handle(new RecordPaymentData(
            11_900,
            'EUR',
            new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
            counterparty: 'Customer GmbH',
        ), new IdempotencyKey('record-ambiguous-suggestion'));

        $result = app(SuggestPaymentAllocations::class)->forPayment($payment->id);

        $this->assertSame('ambiguous', $result->status);
        $this->assertTrue($result->requiresConfirmation);
        $this->assertSame(
            [$first->value, $second->value],
            array_map(static fn ($candidate): int => $candidate->invoiceId->value, $result->candidates),
        );
        $this->assertDatabaseCount('finance_payment_allocation_batches', 0);
        $this->assertDatabaseCount('finance_payment_allocations', 0);
        $this->assertDatabaseMissing('finance_document_activities', ['type' => 'payment.allocated']);
        $this->assertSame(0, DB::table('finance_payments')->where('id', $payment->id->value)->value('version'));
    }

    private function invoice(
        User $owner,
        int $grossMinor,
        string $currency,
        string $status,
        string $suffix,
        string $number = 'RE-2026-0101',
        string $customer = 'Customer GmbH',
        string $kind = 'invoice',
        ?InvoiceId $cancels = null,
    ): InvoiceId {
        $now = now();
        $uuid = '018f4ca3-224d-7d8d-9f00-'.$suffix;
        $seriesId = DB::table('finance_document_series')->insertGetId([
            'user_id' => $owner->id,
            'uuid' => $uuid,
            'document_type' => 'invoice',
            'status' => $status,
            'created_by' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $revisionId = DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $owner->id,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'status' => 'published',
            'snapshot' => json_encode([
                'document_number' => $number,
                'customer' => ['name' => $customer, 'email' => 'customer@example.test'],
            ], JSON_THROW_ON_ERROR),
            'net_minor' => $grossMinor,
            'vat_minor' => 0,
            'gross_minor' => $grossMinor,
            'currency' => $currency,
            'published_at' => $now,
            'created_by' => $owner->id,
            'created_at' => $now,
        ]);
        $invoiceId = DB::table('finance_invoices')->insertGetId([
            'user_id' => $owner->id,
            'uuid' => $uuid,
            'document_series_id' => $seriesId,
            'current_revision_id' => $revisionId,
            'kind' => $kind,
            'number' => $number,
            'year' => 2026,
            'sequence' => (int) substr($suffix, -3),
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-20',
            'workflow_status' => $status,
            'finalized_at' => $now,
            'sent_at' => $status === 'sent' ? $now : null,
            'allocated_minor' => 0,
            'open_minor' => $grossMinor,
            'version' => 0,
            'cancels_invoice_id' => $cancels?->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new InvoiceId($invoiceId);
    }

    private function recordPayment(int $amountMinor, string $key): PaymentId
    {
        return app(RecordPayment::class)->handle(new RecordPaymentData(
            $amountMinor,
            'EUR',
            new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
            'payment-reference',
            'Customer GmbH',
        ), new IdempotencyKey('record-'.$key))->id;
    }

    private function paymentMethod(User $owner, string $name): int
    {
        return (int) DB::table('payment_methods')->insertGetId([
            'user_id' => $owner->id,
            'type' => 'bank',
            'name' => $name,
            'business' => true,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertAllocationCode(
        PaymentId $payment,
        InvoiceId $invoice,
        int $amountMinor,
        string $expected,
        string $key,
    ): void {
        try {
            app(AllocatePayment::class)->handle(new AllocatePaymentData(
                $payment,
                [new AllocationLineData($invoice, $amountMinor)],
            ), new IdempotencyKey('invalid-'.$key));
            $this->fail("The {$key} allocation mismatch was accepted.");
        } catch (DomainException $exception) {
            $this->assertSame($expected, $exception->getMessage());
        }
    }
}
