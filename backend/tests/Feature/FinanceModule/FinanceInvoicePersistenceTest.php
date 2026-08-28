<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Models\User;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\FinalizedInvoice;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Payments\AllocatePaymentData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationId;
use App\Modules\Finance\Application\DTOs\Payments\AllocationLineData;
use App\Modules\Finance\Application\DTOs\Payments\PaymentId;
use App\Modules\Finance\Application\DTOs\Payments\RecordPaymentData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Infrastructure\Persistence\EloquentIdempotencyStore;
use App\Modules\Finance\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentPaymentRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentRecurringInvoiceRepository;
use App\Modules\Finance\Infrastructure\Persistence\Models\IdempotencyRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceDeliveryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceSequenceRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\PaymentAllocationBatchRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\PaymentAllocationRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\PaymentRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\RecurringInvoiceRunRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\RecurringInvoiceTemplateRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\RecurringInvoiceTemplateVersionRecord;
use App\Modules\Finance\Infrastructure\SystemClock;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FinanceInvoicePersistenceTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string, array{class-string}> */
    public static function persistenceTypes(): iterable
    {
        foreach ([
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\InvoiceRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\InvoiceSequenceRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\InvoiceDeliveryRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\IdempotencyRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\PaymentRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\PaymentAllocationBatchRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\PaymentAllocationRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\RecurringInvoiceTemplateRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\RecurringInvoiceTemplateVersionRecord',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\Models\\RecurringInvoiceRunRecord',
            'App\\Modules\\Finance\\Application\\Ports\\InvoiceRepository',
            'App\\Modules\\Finance\\Application\\Ports\\PaymentRepository',
            'App\\Modules\\Finance\\Application\\Ports\\RecurringInvoiceRepository',
            'App\\Modules\\Finance\\Application\\Ports\\IdempotencyStore',
            'App\\Modules\\Finance\\Application\\DTOs\\IdempotencyKey',
            'App\\Modules\\Finance\\Application\\DTOs\\Invoices\\InvoiceId',
            'App\\Modules\\Finance\\Application\\DTOs\\Invoices\\InvoiceLineData',
            'App\\Modules\\Finance\\Application\\DTOs\\Invoices\\InvoiceDraftData',
            'App\\Modules\\Finance\\Application\\DTOs\\Invoices\\InvoiceView',
            'App\\Modules\\Finance\\Application\\DTOs\\Invoices\\FinalizedInvoice',
            'App\\Modules\\Finance\\Application\\DTOs\\Invoices\\DeliveryId',
            'App\\Modules\\Finance\\Application\\DTOs\\Payments\\PaymentId',
            'App\\Modules\\Finance\\Application\\DTOs\\Payments\\RecordPaymentData',
            'App\\Modules\\Finance\\Application\\DTOs\\Payments\\AllocatePaymentData',
            'App\\Modules\\Finance\\Application\\DTOs\\Payments\\AllocationLineData',
            'App\\Modules\\Finance\\Application\\DTOs\\Payments\\PaymentView',
            'App\\Modules\\Finance\\Application\\DTOs\\Payments\\AllocationId',
            'App\\Modules\\Finance\\Application\\DTOs\\Payments\\AllocationResult',
            'App\\Modules\\Finance\\Application\\DTOs\\Recurring\\RecurringTemplateId',
            'App\\Modules\\Finance\\Application\\DTOs\\Recurring\\RecurringRunId',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\EloquentInvoiceRepository',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\EloquentPaymentRepository',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\EloquentRecurringInvoiceRepository',
            'App\\Modules\\Finance\\Infrastructure\\Persistence\\EloquentIdempotencyStore',
            'App\\Modules\\Finance\\Infrastructure\\SystemClock',
        ] as $class) {
            yield $class => [$class];
        }
    }

    #[DataProvider('persistenceTypes')]
    public function test_task_five_persistence_surface_is_available(string $class): void
    {
        $this->assertTrue(class_exists($class) || interface_exists($class), $class);
    }

    /** @return iterable<string, array{callable(): object}> */
    public static function invalidIdValues(): iterable
    {
        foreach ([0, -1] as $value) {
            yield "invoice {$value}" => [static fn (): InvoiceId => new InvoiceId($value)];
            yield "delivery {$value}" => [static fn (): DeliveryId => new DeliveryId($value)];
            yield "payment {$value}" => [static fn (): PaymentId => new PaymentId($value)];
            yield "allocation {$value}" => [static fn (): AllocationId => new AllocationId($value)];
            yield "template {$value}" => [static fn (): RecurringTemplateId => new RecurringTemplateId($value)];
            yield "run {$value}" => [static fn (): RecurringRunId => new RecurringRunId($value)];
        }
    }

    #[DataProvider('invalidIdValues')]
    public function test_ids_require_positive_integers(callable $create): void
    {
        $this->expectException(InvalidArgumentException::class);

        $create();
    }

    public function test_idempotency_keys_are_trimmed_bounded_and_expose_only_a_hash(): void
    {
        $key = new IdempotencyKey('  opaque-client-key  ');

        $this->assertSame(hash('sha256', 'opaque-client-key'), $key->hash());
        $this->assertFalse(method_exists($key, '__toString'));

        foreach (['   ', str_repeat('x', 129)] as $invalid) {
            try {
                new IdempotencyKey($invalid);
                $this->fail('An invalid idempotency key was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_invoice_and_payment_inputs_require_exact_integer_and_scale_four_values(): void
    {
        $line = new InvoiceLineData('Work', '2.5000', 10_000, 1_900, 'h', null, null);
        $draft = new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME'],
            lines: [$line],
            discount: Discount::none('EUR'),
            controlNetMinor: 25_000,
            controlVatMinor: 4_750,
            controlGrossMinor: 29_750,
        );
        $payment = new RecordPaymentData(
            amountMinor: 29_750,
            currency: 'EUR',
            receivedAt: new DateTimeImmutable('2026-08-28T10:00:00+00:00'),
            reference: 'INV-1',
            counterparty: 'ACME',
        );
        $allocation = new AllocatePaymentData(
            new PaymentId(1),
            [new AllocationLineData(new InvoiceId(2), 29_750)],
        );

        $this->assertSame('2.5000', $draft->lines[0]->quantity);
        $this->assertSame(29_750, $payment->amountMinor);
        $this->assertSame(29_750, $allocation->lines[0]->amountMinor);

        foreach ([
            static fn () => new InvoiceLineData('Work', '2.5', 10_000, 1_900, 'h', null, null),
            static fn () => new InvoiceLineData('Work', '2.5000', 10_000, 10_001, 'h', null, null),
            static fn () => new RecordPaymentData(0, 'EUR', new DateTimeImmutable),
            static fn () => new RecordPaymentData(100, 'eur', new DateTimeImmutable),
            static fn () => new RecordPaymentData(100, 'EUR', new DateTimeImmutable, sourceType: '', sourceKey: 'key'),
            static fn () => new RecordPaymentData(100, 'EUR', new DateTimeImmutable, reference: str_repeat('r', 256)),
            static fn () => new RecordPaymentData(100, 'EUR', new DateTimeImmutable, counterparty: str_repeat('c', 256)),
            static fn () => new RecordPaymentData(100, 'EUR', new DateTimeImmutable, sourceType: str_repeat('s', 65), sourceKey: 'key'),
            static fn () => new RecordPaymentData(100, 'EUR', new DateTimeImmutable, sourceType: 'manual', sourceKey: str_repeat('k', 256)),
            static fn () => new AllocationLineData(new InvoiceId(1), 0),
            static fn () => new AllocatePaymentData(new PaymentId(1), [
                new AllocationLineData(new InvoiceId(2), 50),
                new AllocationLineData(new InvoiceId(2), 50),
            ]),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('An inexact finance input was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_authenticated_models_and_repositories_never_cross_owner_ids_or_uuids(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $owned = $this->storedFinanceAggregate((int) $owner->id, 1);
        $other = $this->storedFinanceAggregate((int) $foreign->id, 2);
        $this->actingAs($owner);

        $models = [
            InvoiceRecord::class => 'invoice_id',
            InvoiceSequenceRecord::class => 'sequence_id',
            InvoiceDeliveryRecord::class => 'delivery_id',
            IdempotencyRecord::class => 'idempotency_id',
            PaymentRecord::class => 'payment_id',
            PaymentAllocationBatchRecord::class => 'batch_id',
            PaymentAllocationRecord::class => 'allocation_id',
            RecurringInvoiceTemplateRecord::class => 'template_id',
            RecurringInvoiceTemplateVersionRecord::class => 'template_version_id',
            RecurringInvoiceRunRecord::class => 'run_id',
        ];

        foreach ($models as $model => $key) {
            $this->assertSame([$owned[$key]], $model::query()->pluck('id')->all(), $model);
            $this->assertNull($model::query()->find($other[$key]), $model);
        }

        $this->assertNull(InvoiceRecord::query()->where('uuid', $other['invoice_uuid'])->first());
        $this->assertNull(PaymentRecord::query()->where('uuid', $other['payment_uuid'])->first());
        $this->assertNull(RecurringInvoiceTemplateRecord::query()->where('uuid', $other['template_uuid'])->first());
        $this->assertNull(RecurringInvoiceRunRecord::query()->where('uuid', $other['run_uuid'])->first());
        $this->assertSame($owned['revision_id'], (int) InvoiceRecord::query()->findOrFail($owned['invoice_id'])
            ->currentRevision()->firstOrFail()->id);

        foreach ([
            fn () => $this->invoiceRepository()->get(new InvoiceId($other['invoice_id'])),
            fn () => $this->paymentRepository()->get(new PaymentId($other['payment_id'])),
            static fn () => (new EloquentRecurringInvoiceRepository)->template(new RecurringTemplateId($other['template_id'])),
            static fn () => (new EloquentRecurringInvoiceRepository)->run(new RecurringRunId($other['run_id'])),
        ] as $lookup) {
            try {
                $lookup();
                $this->fail('A repository resolved another owner\'s numeric ID.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_real_sanctum_token_applies_owner_scope_inside_request_resolution(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $owned = $this->storedFinanceAggregate((int) $owner->id, 10);
        $this->storedFinanceAggregate((int) $foreign->id, 11);
        Route::middleware('auth:sanctum')->post('/_finance-task5-owner-scope', static fn (): array => [
            'invoices' => InvoiceRecord::query()->pluck('id')->all(),
            'payments' => PaymentRecord::query()->pluck('id')->all(),
            'templates' => RecurringInvoiceTemplateRecord::query()->pluck('id')->all(),
            'runs' => RecurringInvoiceRunRecord::query()->pluck('id')->all(),
        ]);
        $token = $owner->createToken('task5-owner', ['device'])->plainTextToken;

        $this->postJson('/_finance-task5-owner-scope', [], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertExactJson([
                'invoices' => [$owned['invoice_id']],
                'payments' => [$owned['payment_id']],
                'templates' => [$owned['template_id']],
                'runs' => [$owned['run_id']],
            ]);
    }

    public function test_server_owned_invoice_delivery_and_payment_fields_are_not_mass_assignable(): void
    {
        foreach ([
            [new InvoiceRecord, [
                'user_id', 'uuid', 'document_series_id', 'current_revision_id', 'kind',
                'number', 'year', 'sequence', 'source_type', 'source_key',
                'source_revision_id', 'source_snapshot_sha256', 'workflow_status',
                'finalized_at', 'sent_at', 'allocated_minor', 'open_minor', 'version',
                'cancels_invoice_id',
            ]],
            [new InvoiceDeliveryRecord, [
                'user_id', 'uuid', 'invoice_id', 'document_series_id', 'document_revision_id',
                'kind', 'message_id', 'status', 'attempts', 'last_error_code',
                'idempotency_key_hash', 'request_hash', 'queued_at', 'last_attempt_at',
                'sent_at', 'next_retry_at',
            ]],
            [new PaymentRecord, [
                'user_id', 'uuid', 'amount_minor', 'currency', 'received_at', 'reference',
                'counterparty', 'payment_method_id', 'source_type', 'source_key', 'version',
            ]],
        ] as [$record, $fields]) {
            foreach ($fields as $field) {
                $this->assertFalse($record->isFillable($field), $record::class.' '.$field);
            }
        }
    }

    public function test_append_only_and_repository_owned_records_reject_instance_quiet_and_bulk_mutation(): void
    {
        $owner = User::factory()->create();
        $aggregate = $this->storedFinanceAggregate((int) $owner->id, 20);
        $this->actingAs($owner);
        $records = [
            [PaymentRecord::query()->findOrFail($aggregate['payment_id']), 'reference', 'rewritten', 'amount_minor'],
            [PaymentAllocationBatchRecord::query()->findOrFail($aggregate['batch_id']), 'request_hash', str_repeat('a', 64), 'payment_id'],
            [PaymentAllocationRecord::query()->findOrFail($aggregate['allocation_id']), 'amount_minor', 2_000, 'amount_minor'],
            [InvoiceDeliveryRecord::query()->findOrFail($aggregate['delivery_id']), 'status', 'sent', 'attempts'],
            [RecurringInvoiceTemplateVersionRecord::query()->findOrFail($aggregate['template_version_id']), 'snapshot_sha256', str_repeat('b', 64), 'version_number'],
            [RecurringInvoiceRunRecord::query()->findOrFail($aggregate['run_id']), 'attempts', 1, 'attempts'],
        ];

        foreach ($records as [$record, $field, $value, $numericField]) {
            $this->assertImmutableMutation(fn () => $record->forceFill([$field => $value])->save());
            $record->refresh();
            $this->assertImmutableMutation(fn () => $record->forceFill([$field => $value])->saveQuietly());
            $record->refresh();
            $this->assertImmutableMutation(fn () => $record->update([$field => $value]));
            $this->assertImmutableMutation(fn () => $record->updateQuietly([$field => $value]));
            $this->assertImmutableMutation(fn () => $record->increment($numericField));
            $this->assertImmutableMutation(fn () => $record->decrement($numericField));
            $this->assertImmutableMutation(fn () => $record->delete());
            $this->assertImmutableMutation(fn () => $record->deleteQuietly());
            $this->assertImmutableMutation(fn () => $record::query()->whereKey($record->getKey())->update([$field => $value]));
            $this->assertImmutableMutation(fn () => $record::query()->whereKey($record->getKey())->touch());
            $this->assertImmutableMutation(fn () => $record::query()->whereKey($record->getKey())->increment($numericField));
            $this->assertImmutableMutation(fn () => $record::query()->whereKey($record->getKey())->decrement($numericField));
            $this->assertImmutableMutation(fn () => $record::query()->whereKey($record->getKey())->delete());
            $this->assertImmutableMutation(fn () => $record::query()->whereKey($record->getKey())->forceDelete());
            $this->assertImmutableMutation(fn () => $record::query()->updateOrInsert(['id' => $record->getKey()], [$field => $value]));
            $this->assertImmutableMutation(fn () => $record::query()->upsert([
                ['id' => $record->getKey(), $field => $value],
            ], ['id'], [$field]));
            $this->assertImmutableMutation(fn () => $record::query()->truncate());
        }
    }

    public function test_sqlite_replace_cannot_rewrite_payment_or_recurring_history(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('INSERT OR REPLACE is SQLite-specific.');
        }

        $owner = User::factory()->create();
        $aggregate = $this->storedFinanceAggregate((int) $owner->id, 21);
        foreach ([
            ['finance_payments', $aggregate['payment_id'], 'reference', 'replace-payment'],
            ['finance_payment_allocation_batches', $aggregate['batch_id'], 'request_hash', str_repeat('c', 64)],
            ['finance_payment_allocations', $aggregate['allocation_id'], 'amount_minor', 3_000],
            ['finance_recurring_invoice_template_versions', $aggregate['template_version_id'], 'snapshot_sha256', str_repeat('d', 64)],
            ['finance_recurring_invoice_runs', $aggregate['run_id'], 'attempts', 9],
        ] as [$table, $id, $field, $value]) {
            $row = (array) DB::table($table)->find($id);
            $row[$field] = $value;

            try {
                $this->replaceRow($table, $row);
                $this->fail("INSERT OR REPLACE rewrote {$table}.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }

            $this->assertNotSame($value, DB::table($table)->where('id', $id)->value($field));
        }
    }

    public function test_idempotency_store_hashes_keys_replays_results_and_rejects_request_reuse(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $this->actingAs($owner);
        $store = new EloquentIdempotencyStore(new SystemClock);
        $key = new IdempotencyKey(' raw-key-never-stored ');
        $requestHash = hash('sha256', 'canonical-request');

        $reserved = $store->reserve('payment.record', $key, $requestHash);
        $this->assertSame('new', $reserved['status']);
        $this->assertDatabaseMissing('finance_idempotency_records', ['key_hash' => 'raw-key-never-stored']);
        $this->assertDatabaseHas('finance_idempotency_records', ['key_hash' => $key->hash()]);
        $this->assertSame('in_progress', $store->reserve('payment.record', $key, $requestHash)['status']);

        try {
            $store->reserve('payment.record', $key, hash('sha256', 'different-request'));
            $this->fail('An idempotency key was reused for a different request.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }

        $store->complete($reserved['record_id'], 201, ['payment_id' => 42]);
        $replay = $store->reserve('payment.record', $key, $requestHash);
        $this->assertSame('replay', $replay['status']);
        $this->assertSame(201, $replay['response_status']);
        $this->assertSame(['payment_id' => 42], $replay['response_payload']);

        $this->actingAs($foreign);
        $this->assertSame('new', $store->reserve('payment.record', $key, $requestHash)['status']);
        $this->assertSame(2, DB::table('finance_idempotency_records')->where('operation', 'payment.record')->count());
    }

    public function test_recurring_repository_paginates_stably_and_locks_owner_context(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $first = $this->storedFinanceAggregate((int) $owner->id, 31);
        $second = $this->storedFinanceAggregate((int) $owner->id, 32);
        $this->storedFinanceAggregate((int) $foreign->id, 33);
        $this->actingAs($owner);
        $repository = new EloquentRecurringInvoiceRepository;

        $templatePageOne = $repository->templates(1, 1);
        $templatePageTwo = $repository->templates(2, 1);
        $runPage = $repository->runs(1, 100);

        $this->assertSame(2, $templatePageOne['total']);
        $this->assertSame($first['template_id'], $templatePageOne['items'][0]['id']);
        $this->assertSame($second['template_id'], $templatePageTwo['items'][0]['id']);
        $this->assertSame([$first['run_id'], $second['run_id']], array_column($runPage['items'], 'id'));
        $this->assertArrayNotHasKey('idempotency_key_hash', $runPage['items'][0]);

        $this->assertSame(
            $first['template_id'],
            $repository->withLockedTemplate(
                new RecurringTemplateId($first['template_id']),
                static fn (array $template): int => $template['id'],
            ),
        );
        $this->assertSame(
            $first['run_id'],
            $repository->withLockedRun(
                new RecurringRunId($first['run_id']),
                static fn (array $run): int => $run['id'],
            ),
        );

        foreach ([[0, 25], [1, 0], [1, 101]] as [$page, $perPage]) {
            try {
                $repository->templates($page, $perPage);
                $this->fail('Invalid recurring pagination was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_invoice_repository_creates_and_updates_exact_owner_scoped_drafts_with_cas(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $repository = $this->invoiceRepository();
        $invoiceId = $repository->createDraft($this->invoiceDraft(10_000, 1_900, 11_900));
        $stored = DB::table('finance_invoices')->find($invoiceId->value);

        $this->assertSame($owner->id, $stored->user_id);
        $this->assertNull($stored->number);
        $this->assertNull($stored->source_type);
        $this->assertNull($stored->cancels_invoice_id);
        $this->assertSame('draft', $stored->workflow_status);
        $this->assertSame(11_900, $repository->get($invoiceId)->grossMinor);

        $updated = $repository->updateDraft(
            $invoiceId,
            $this->invoiceDraft(20_000, 3_800, 23_800),
            0,
        );
        $this->assertSame(1, $updated->version);
        $this->assertSame(23_800, $updated->grossMinor);
        $this->assertSame(23_800, $updated->openMinor);

        try {
            $repository->updateDraft($invoiceId, $this->invoiceDraft(30_000, 5_700, 35_700), 0);
            $this->fail('A stale invoice version updated the draft.');
        } catch (DomainException $exception) {
            $this->assertSame('invoice_version_conflict', $exception->getMessage());
        }
    }

    public function test_invoice_finalize_is_idempotent_and_delivery_advances_only_by_compare_and_set(): void
    {
        $owner = User::factory()->create();
        $aggregate = $this->storedFinanceAggregate((int) $owner->id, 40);
        $this->actingAs($owner);
        $repository = $this->invoiceRepository();
        $publishCalls = 0;
        $key = new IdempotencyKey('finalize-40');
        $publish = function ($invoice) use (&$publishCalls, $aggregate): FinalizedInvoice {
            $publishCalls++;

            return new FinalizedInvoice(
                $invoice,
                $aggregate['revision_id'],
                'finance/invoices/finalized-40.pdf',
                str_repeat('a', 64),
                new DateTimeImmutable('2026-08-28T12:00:00+00:00'),
            );
        };

        $first = $repository->finalize(new InvoiceId($aggregate['invoice_id']), $key, $publish);
        $second = $repository->finalize(
            new InvoiceId($aggregate['invoice_id']),
            $key,
            static fn () => throw new \RuntimeException('Publisher must not run on replay.'),
        );

        $this->assertSame(1, $publishCalls);
        $this->assertSame($first->pdfSha256, $second->pdfSha256);
        $this->assertSame($first->revisionId, $second->revisionId);
        $this->assertDatabaseMissing('finance_idempotency_records', ['key_hash' => 'finalize-40']);

        $at = new DateTimeImmutable('2026-08-28T13:00:00+00:00');
        $view = $repository->markDeliverySent(new DeliveryId($aggregate['delivery_id']), $at);
        $this->assertSame($aggregate['invoice_id'], $view->id->value);
        $this->assertDatabaseHas('finance_invoice_deliveries', [
            'id' => $aggregate['delivery_id'],
            'status' => 'sent',
            'sent_at' => '2026-08-28 13:00:00',
        ]);
        $repository->markDeliverySent(new DeliveryId($aggregate['delivery_id']), $at);

        $foreign = User::factory()->create();
        $foreignAggregate = $this->storedFinanceAggregate((int) $foreign->id, 41);
        $this->assertModelNotFound(
            fn () => $repository->markDeliverySent(new DeliveryId($foreignAggregate['delivery_id']), $at),
        );
    }

    public function test_payment_recording_is_owner_scoped_and_idempotent_without_persisting_raw_keys(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $repository = $this->paymentRepository();
        $key = new IdempotencyKey('payment-record-raw-key');
        $data = new RecordPaymentData(
            15_000,
            'EUR',
            new DateTimeImmutable('2026-08-28T10:30:00+00:00'),
            'RE-2026-100',
            'ACME',
            sourceType: 'manual',
            sourceKey: 'manual-100',
        );

        $first = $repository->record($data, $key);
        $second = $repository->record($data, $key);

        $this->assertSame($first->id->value, $second->id->value);
        $this->assertSame(15_000, $first->unappliedMinor);
        $this->assertSame(1, DB::table('finance_payments')->where('user_id', $owner->id)->count());
        $this->assertDatabaseMissing('finance_payments', ['source_key' => 'payment-record-raw-key']);
        $this->assertDatabaseHas('finance_idempotency_records', ['key_hash' => $key->hash()]);

        try {
            $repository->record(new RecordPaymentData(
                15_001,
                'EUR',
                new DateTimeImmutable('2026-08-28T10:30:00+00:00'),
            ), $key);
            $this->fail('A payment idempotency key was reused for another request.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused', $exception->getMessage());
        }

        try {
            $repository->record($data, new IdempotencyKey('payment-record-other-key'));
            $this->fail('A payment source was recorded twice under different idempotency keys.');
        } catch (DomainException $exception) {
            $this->assertSame('payment_source_conflict', $exception->getMessage());
        }
        $this->assertSame(1, DB::table('finance_payments')->where('user_id', $owner->id)->count());
        $this->assertDatabaseMissing('finance_idempotency_records', [
            'key_hash' => (new IdempotencyKey('payment-record-other-key'))->hash(),
        ]);
    }

    public function test_payment_allocation_and_reversal_append_once_and_recompute_exact_balances(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $aggregate = $this->storedFinanceAggregate((int) $owner->id, 50);
        $foreignAggregate = $this->storedFinanceAggregate((int) $foreign->id, 51);
        $this->actingAs($owner);
        $repository = $this->paymentRepository();
        $allocationData = new AllocatePaymentData(
            new PaymentId($aggregate['payment_id']),
            [new AllocationLineData(new InvoiceId($aggregate['invoice_id']), 5_000)],
        );
        $key = new IdempotencyKey('allocate-50');

        $first = $repository->allocate($allocationData, $key);
        $replay = $repository->allocate($allocationData, $key);

        $this->assertSame($first->batchId, $replay->batchId);
        $this->assertSame($first->allocationIds[0]->value, $replay->allocationIds[0]->value);
        $this->assertSame(6_000, $first->payment->allocatedMinor);
        $this->assertSame(5_900, $first->payment->unappliedMinor);
        $this->assertSame(6_000, $first->invoices[0]->allocatedMinor);
        $this->assertSame(5_900, $first->invoices[0]->openMinor);
        $this->assertSame(2, DB::table('finance_payment_allocations')
            ->where('payment_id', $aggregate['payment_id'])->count());

        $reversed = $repository->reverse($first->allocationIds[0], new IdempotencyKey('reverse-50'));
        $reverseReplay = $repository->reverse($first->allocationIds[0], new IdempotencyKey('reverse-50'));
        $this->assertSame($reversed->batchId, $reverseReplay->batchId);
        $this->assertSame(1_000, $reversed->payment->allocatedMinor);
        $this->assertSame(10_900, $reversed->payment->unappliedMinor);
        $this->assertSame(1_000, $reversed->invoices[0]->allocatedMinor);
        $this->assertSame(10_900, $reversed->invoices[0]->openMinor);
        $this->assertSame(1, DB::table('finance_payment_allocations')
            ->where('reverses_allocation_id', $first->allocationIds[0]->value)->count());
        try {
            $repository->reverse($first->allocationIds[0], new IdempotencyKey('second-reverse-50'));
            $this->fail('An allocation was reversed a second time.');
        } catch (DomainException $exception) {
            $this->assertSame('allocation_already_reversed', $exception->getMessage());
        }

        $this->assertModelNotFound(fn () => $repository->allocate(new AllocatePaymentData(
            new PaymentId($aggregate['payment_id']),
            [new AllocationLineData(new InvoiceId($foreignAggregate['invoice_id']), 100)],
        ), new IdempotencyKey('foreign-allocation')));

        try {
            $repository->allocate(new AllocatePaymentData(
                new PaymentId($aggregate['payment_id']),
                [new AllocationLineData(new InvoiceId($aggregate['invoice_id']), -100)],
            ), new IdempotencyKey('wrong-sign'));
            $this->fail('An allocation with the wrong sign was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('allocation_sign_mismatch', $exception->getMessage());
        }
    }

    public function test_postgresql_executes_owner_idempotency_and_lock_contract_when_configured(): void
    {
        $this->withIsolatedPostgresSchema(function (string $connectionName): void {
            $owner = new User;
            $owner->forceFill(['id' => 1]);
            $this->actingAs($owner);
            $aggregate = $this->storedFinanceAggregate(1, 60);
            $repository = $this->paymentRepository();

            $queries = [];
            DB::listen(static function (QueryExecuted $query) use (&$queries, $connectionName): void {
                if ($query->connectionName === $connectionName) {
                    $queries[] = strtolower($query->sql);
                }
            });

            $allocation = $repository->allocate(new AllocatePaymentData(
                new PaymentId($aggregate['payment_id']),
                [new AllocationLineData(new InvoiceId($aggregate['invoice_id']), 500)],
            ), new IdempotencyKey('pgsql-allocation-60'));
            $replay = $repository->allocate(new AllocatePaymentData(
                new PaymentId($aggregate['payment_id']),
                [new AllocationLineData(new InvoiceId($aggregate['invoice_id']), 500)],
            ), new IdempotencyKey('pgsql-allocation-60'));

            $this->assertSame($allocation->batchId, $replay->batchId);
            $this->assertSame(1_500, $allocation->payment->allocatedMinor);
            $this->assertSame(10_400, $allocation->invoices[0]->openMinor);

            $lockQueries = array_values(array_filter(
                $queries,
                static fn (string $sql): bool => str_contains($sql, 'for update'),
            ));
            $lockedTables = array_map(static function (string $sql): string {
                foreach ([
                    'finance_document_series',
                    'finance_invoices',
                    'finance_document_revisions',
                    'finance_payments',
                    'finance_payment_allocations',
                ] as $table) {
                    if (str_contains($sql, $table)) {
                        return $table;
                    }
                }

                return 'other';
            }, $lockQueries);
            $this->assertSame([
                'finance_document_series',
                'finance_invoices',
                'finance_document_revisions',
                'finance_payments',
                'finance_payment_allocations',
            ], array_slice(array_values(array_filter(
                $lockedTables,
                static fn (string $table): bool => $table !== 'other',
            )), 0, 5));

            $recurring = new EloquentRecurringInvoiceRepository;
            $this->assertSame(
                $aggregate['run_id'],
                $recurring->withLockedRun(
                    new RecurringRunId($aggregate['run_id']),
                    static fn (array $run): int => (int) $run['id'],
                ),
            );
            $reversed = $repository->reverse(
                $allocation->allocationIds[0],
                new IdempotencyKey('pgsql-reversal-60'),
            );
            $this->assertSame(1_000, $reversed->payment->allocatedMinor);
            $this->assertSame(10_900, $reversed->invoices[0]->openMinor);

            $foreign = new User;
            $foreign->forceFill(['id' => 2]);
            $this->actingAs($foreign);
            $this->assertModelNotFound(fn () => $repository->get(new PaymentId($aggregate['payment_id'])));
        });
    }

    /**
     * @return array{
     *   invoice_id: int, invoice_uuid: string, revision_id: int, sequence_id: int,
     *   delivery_id: int, idempotency_id: int, payment_id: int, payment_uuid: string,
     *   batch_id: int, allocation_id: int, template_id: int, template_uuid: string,
     *   template_version_id: int, run_id: int, run_uuid: string
     * }
     */
    private function storedFinanceAggregate(int $ownerId, int $suffix): array
    {
        $now = '2026-08-28 10:00:00';
        $scheduledDay = ($suffix % 20) + 1;
        $seriesId = (int) DB::table('finance_document_series')->insertGetId([
            'user_id' => $ownerId,
            'uuid' => sprintf('018f4ca3-224d-7d8d-9f01-%012d', $suffix),
            'document_type' => 'invoice',
            'status' => 'finalized',
            'created_by' => $ownerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $revisionId = (int) DB::table('finance_document_revisions')->insertGetId([
            'user_id' => $ownerId,
            'document_series_id' => $seriesId,
            'revision_number' => 1,
            'previous_revision_id' => null,
            'status' => 'published',
            'snapshot' => json_encode(['customer' => ['name' => 'ACME']], JSON_THROW_ON_ERROR),
            'net_minor' => 10_000,
            'vat_minor' => 1_900,
            'gross_minor' => 11_900,
            'currency' => 'EUR',
            'pdf_path' => "finance/invoices/{$ownerId}-{$suffix}.pdf",
            'pdf_sha256' => hash('sha256', "pdf-{$ownerId}-{$suffix}"),
            'published_at' => $now,
            'created_by' => $ownerId,
            'created_at' => $now,
        ]);
        $invoiceUuid = sprintf('018f4ca3-224d-7d8d-9f02-%012d', $suffix);
        $invoiceId = (int) DB::table('finance_invoices')->insertGetId([
            'user_id' => $ownerId,
            'uuid' => $invoiceUuid,
            'document_series_id' => $seriesId,
            'current_revision_id' => $revisionId,
            'kind' => 'invoice',
            'number' => "RE-2026-{$suffix}",
            'year' => 2026,
            'sequence' => $suffix,
            'issue_date' => '2026-08-28',
            'due_date' => '2026-09-11',
            'workflow_status' => 'finalized',
            'finalized_at' => $now,
            'allocated_minor' => 1_000,
            'open_minor' => 10_900,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sequenceId = (int) DB::table('finance_invoice_sequences')->insertGetId([
            'user_id' => $ownerId,
            'series_key' => "invoice-{$suffix}",
            'year' => 2026,
            'next_sequence' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $deliveryId = (int) DB::table('finance_invoice_deliveries')->insertGetId([
            'user_id' => $ownerId,
            'uuid' => sprintf('018f4ca3-224d-7d8d-9f03-%012d', $suffix),
            'invoice_id' => $invoiceId,
            'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId,
            'kind' => 'invoice',
            'recipient' => "owner-{$ownerId}@example.test",
            'message_id' => "<invoice-{$ownerId}-{$suffix}@example.test>",
            'status' => 'pending',
            'attempts' => 0,
            'idempotency_key_hash' => hash('sha256', "delivery-key-{$ownerId}-{$suffix}"),
            'request_hash' => hash('sha256', "delivery-request-{$ownerId}-{$suffix}"),
            'queued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $idempotencyId = (int) DB::table('finance_idempotency_records')->insertGetId([
            'user_id' => $ownerId,
            'operation' => 'fixture',
            'key_hash' => hash('sha256', "idempotency-{$ownerId}-{$suffix}"),
            'request_hash' => hash('sha256', "request-{$ownerId}-{$suffix}"),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $paymentUuid = sprintf('018f4ca3-224d-7d8d-9f04-%012d', $suffix);
        $paymentId = (int) DB::table('finance_payments')->insertGetId([
            'user_id' => $ownerId,
            'uuid' => $paymentUuid,
            'amount_minor' => 11_900,
            'currency' => 'EUR',
            'received_at' => $now,
            'reference' => "RE-2026-{$suffix}",
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $batchId = (int) DB::table('finance_payment_allocation_batches')->insertGetId([
            'user_id' => $ownerId,
            'payment_id' => $paymentId,
            'idempotency_key_hash' => hash('sha256', "batch-key-{$ownerId}-{$suffix}"),
            'request_hash' => hash('sha256', "batch-request-{$ownerId}-{$suffix}"),
            'created_by' => $ownerId,
            'created_at' => $now,
        ]);
        $allocationId = (int) DB::table('finance_payment_allocations')->insertGetId([
            'user_id' => $ownerId,
            'allocation_batch_id' => $batchId,
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount_minor' => 1_000,
            'reverses_allocation_id' => null,
            'created_at' => $now,
        ]);
        $templateUuid = sprintf('018f4ca3-224d-7d8d-9f05-%012d', $suffix);
        $templateId = (int) DB::table('finance_recurring_invoice_templates')->insertGetId([
            'user_id' => $ownerId,
            'uuid' => $templateUuid,
            'mode' => 'draft',
            'interval' => 'monthly',
            'timezone' => 'Europe/Berlin',
            'start_date' => '2026-08-28',
            'run_time' => '08:00:00',
            'anchor_day' => 28,
            'month_end_anchor' => false,
            'next_run_at' => '2026-09-28 06:00:00',
            'status' => 'active',
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templateVersionId = (int) DB::table('finance_recurring_invoice_template_versions')->insertGetId([
            'user_id' => $ownerId,
            'template_id' => $templateId,
            'version_number' => 1,
            'effective_from' => '2026-08-28',
            'draft_snapshot' => json_encode(['currency' => 'EUR'], JSON_THROW_ON_ERROR),
            'snapshot_sha256' => hash('sha256', "template-{$ownerId}-{$suffix}"),
            'created_by' => $ownerId,
            'created_at' => $now,
        ]);
        $runUuid = sprintf('018f4ca3-224d-7d8d-9f06-%012d', $suffix);
        $runId = (int) DB::table('finance_recurring_invoice_runs')->insertGetId([
            'user_id' => $ownerId,
            'uuid' => $runUuid,
            'template_id' => $templateId,
            'template_version_id' => $templateVersionId,
            'scheduled_for' => sprintf('2026-10-%02d 06:00:00', $scheduledDay),
            'scheduled_local_date' => sprintf('2026-10-%02d', $scheduledDay),
            'status' => 'pending',
            'attempts' => 0,
            'idempotency_key_hash' => hash('sha256', "run-key-{$ownerId}-{$suffix}"),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'invoice_id' => $invoiceId,
            'invoice_uuid' => $invoiceUuid,
            'revision_id' => $revisionId,
            'sequence_id' => $sequenceId,
            'delivery_id' => $deliveryId,
            'idempotency_id' => $idempotencyId,
            'payment_id' => $paymentId,
            'payment_uuid' => $paymentUuid,
            'batch_id' => $batchId,
            'allocation_id' => $allocationId,
            'template_id' => $templateId,
            'template_uuid' => $templateUuid,
            'template_version_id' => $templateVersionId,
            'run_id' => $runId,
            'run_uuid' => $runUuid,
        ];
    }

    private function assertImmutableMutation(callable $mutation): void
    {
        try {
            $mutation();
            $this->fail('A repository-owned record accepted direct mutation.');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @param callable(string): void $test */
    private function withIsolatedPostgresSchema(callable $test): void
    {
        $postgresUrl = getenv('FINANCE_TEST_PGSQL_URL');
        if (! extension_loaded('pdo_pgsql') || ! is_string($postgresUrl) || trim($postgresUrl) === '') {
            $this->markTestSkipped(
                'Set FINANCE_TEST_PGSQL_URL and install pdo_pgsql to run the Task 5 PostgreSQL repository contract.',
            );
        }

        $postgresConfig = config('database.connections.pgsql');
        if (! is_array($postgresConfig)) {
            throw new \LogicException('PostgreSQL connection configuration is unavailable.');
        }

        $defaultConnection = DB::getDefaultConnection();
        $connectionName = 'pgsql_invoice_persistence';
        $schema = 'finance_invoice_task5_'.bin2hex(random_bytes(8));
        config([
            "database.connections.{$connectionName}" => array_merge(
                $postgresConfig,
                ['url' => $postgresUrl, 'search_path' => 'public'],
            ),
        ]);
        DB::purge($connectionName);
        $connection = DB::connection($connectionName);
        $schemaCreated = false;

        try {
            $connection->statement("CREATE SCHEMA \"{$schema}\"");
            $schemaCreated = true;
            $connection->statement("SET search_path TO \"{$schema}\"");
            DB::setDefaultConnection($connectionName);
            Schema::clearResolvedInstance('db.schema');
            Schema::create('users', static function (Blueprint $table): void {
                $table->id();
            });
            foreach ([
                '2026_08_28_100000_create_finance_document_core.php',
                '2026_08_28_110000_create_finance_invoices.php',
                '2026_08_28_110100_create_finance_payments.php',
                '2026_08_28_110200_create_finance_recurring_invoices.php',
            ] as $migrationFile) {
                $migration = require database_path('migrations/'.$migrationFile);
                if (! is_object($migration) || ! is_callable([$migration, 'up'])) {
                    throw new \LogicException("Finance migration {$migrationFile} is unavailable.");
                }
                $migration->up();
            }
            DB::table('users')->insert([['id' => 1], ['id' => 2]]);

            $test($connectionName);
        } finally {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            DB::setDefaultConnection($defaultConnection);
            Schema::clearResolvedInstance('db.schema');

            try {
                if ($schemaCreated) {
                    $connection->statement('SET search_path TO public');
                    $connection->statement("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE");
                }
            } finally {
                DB::purge($connectionName);
            }
        }
    }

    private function assertModelNotFound(callable $lookup): void
    {
        try {
            $lookup();
            $this->fail('A foreign or missing model was resolved.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    private function invoiceRepository(): EloquentInvoiceRepository
    {
        return new EloquentInvoiceRepository(new EloquentIdempotencyStore(new SystemClock), new SystemClock);
    }

    private function paymentRepository(): EloquentPaymentRepository
    {
        $clock = new SystemClock;

        return new EloquentPaymentRepository(
            new EloquentIdempotencyStore($clock),
            $clock,
            new EloquentInvoiceRepository(new EloquentIdempotencyStore($clock), $clock),
        );
    }

    private function invoiceDraft(int $netMinor, int $vatMinor, int $grossMinor): InvoiceDraftData
    {
        return new InvoiceDraftData(
            issueDate: new DateTimeImmutable('2026-08-28'),
            dueDate: new DateTimeImmutable('2026-09-11'),
            currency: 'EUR',
            customer: ['name' => 'ACME'],
            lines: [new InvoiceLineData('Work', '1.0000', $netMinor, 1_900, 'h', null, 'service')],
            discount: Discount::none('EUR'),
            controlNetMinor: $netMinor,
            controlVatMinor: $vatMinor,
            controlGrossMinor: $grossMinor,
        );
    }

    /** @param array<string, mixed> $row */
    private function replaceRow(string $table, array $row): void
    {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();
        $columns = array_keys($row);
        $sql = sprintf(
            'INSERT OR REPLACE INTO %s (%s) VALUES (%s)',
            $grammar->wrapTable($table),
            implode(', ', array_map($grammar->wrap(...), $columns)),
            implode(', ', array_fill(0, count($columns), '?')),
        );

        $connection->statement($sql, array_values($row));
    }
}
