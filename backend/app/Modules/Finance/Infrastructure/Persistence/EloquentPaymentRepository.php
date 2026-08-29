<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Models\BankTransaction;
use App\Models\PaymentMethod;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Payments\AllocatePaymentData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationId;
use App\Modules\Finance\Application\DTOs\Payments\AllocationLineData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationResult;
use App\Modules\Finance\Application\DTOs\Payments\PaymentId;
use App\Modules\Finance\Application\DTOs\Payments\PaymentView;
use App\Modules\Finance\Application\DTOs\Payments\RecordPaymentData;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\IdempotencyStore;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Application\Ports\PaymentRepository;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\InvoiceRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\PaymentAllocationBatchRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\PaymentAllocationRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\PaymentRecord;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class EloquentPaymentRepository implements PaymentRepository
{
    public function __construct(
        private readonly IdempotencyStore $idempotency,
        private readonly Clock $clock,
        private readonly InvoiceRepository $invoices,
    ) {}

    public function get(PaymentId $id): PaymentView
    {
        return $this->view($this->ownedPayment($id));
    }

    public function record(RecordPaymentData $data, IdempotencyKey $key): PaymentView
    {
        $requestHash = $this->requestHash([
            'amount_minor' => $data->amountMinor,
            'currency' => $data->currency,
            'received_at' => $data->receivedAt->format(DATE_ATOM),
            'reference' => $data->reference,
            'counterparty' => $data->counterparty,
            'payment_method_id' => $data->paymentMethodId,
            'source_type' => $data->sourceType,
            'source_key' => $data->sourceKey,
        ]);

        try {
            return DB::transaction(function () use ($data, $key, $requestHash): PaymentView {
                $reservation = $this->idempotency->reserve('payment.record', $key, $requestHash);

                if ($reservation['status'] === 'replay') {
                    $paymentId = $reservation['response_payload']['payment_id'] ?? null;
                    if (! is_int($paymentId)) {
                        throw new LogicException('Stored payment result is incomplete.');
                    }

                    return $this->get(new PaymentId($paymentId));
                }
                if ($reservation['status'] !== 'new') {
                    throw new DomainException('idempotency_'.$reservation['status']);
                }
                $this->assertOwnedRecordReferences($data);

                $payment = new PaymentRecord;
                $payment->forceFill([
                    'user_id' => $this->ownerId(),
                    'uuid' => (string) Str::uuid(),
                    'amount_minor' => $data->amountMinor,
                    'currency' => $data->currency,
                    'received_at' => $data->receivedAt,
                    'reference' => $data->reference,
                    'counterparty' => $data->counterparty,
                    'payment_method_id' => $data->paymentMethodId,
                    'source_type' => $data->sourceType,
                    'source_key' => $data->sourceKey,
                    'version' => 0,
                ])->save();
                $this->idempotency->complete($reservation['record_id'], 201, [
                    'payment_id' => (int) $payment->id,
                ]);

                return $this->view($payment);
            }, 1);
        } catch (QueryException $exception) {
            if ($data->sourceType !== null && $data->sourceKey !== null
                && DB::table('finance_payments')
                    ->where('user_id', $this->ownerId())
                    ->where('source_type', $data->sourceType)
                    ->where('source_key', $data->sourceKey)
                    ->exists()) {
                throw new DomainException('payment_source_conflict', previous: $exception);
            }

            throw $exception;
        }
    }

    public function allocate(AllocatePaymentData $data, IdempotencyKey $key): AllocationResult
    {
        $canonicalLines = array_map(static fn (AllocationLineData $line): array => [
            'invoice_id' => $line->invoiceId->value,
            'amount_minor' => $line->amountMinor,
        ], $data->lines);
        usort(
            $canonicalLines,
            static fn (array $left, array $right): int => $left['invoice_id'] <=> $right['invoice_id'],
        );
        $requestHash = $this->requestHash([
            'payment_id' => $data->paymentId->value,
            'lines' => $canonicalLines,
            'expected_version' => $data->expectedVersion,
        ]);

        return DB::transaction(function () use ($data, $key, $requestHash): AllocationResult {
            $reservation = $this->idempotency->reserve('payment.allocate', $key, $requestHash);
            if ($reservation['status'] === 'replay') {
                return $this->replayedAllocation($reservation['response_payload']);
            }
            if ($reservation['status'] !== 'new') {
                throw new DomainException('idempotency_'.$reservation['status']);
            }

            $invoiceIds = array_map(
                static fn (AllocationLineData $line): int => $line->invoiceId->value,
                $data->lines,
            );
            $context = $this->lockContext($data->paymentId, $invoiceIds);
            $payment = $context['payment'];
            if ($data->expectedVersion !== null && (int) $payment->version !== $data->expectedVersion) {
                throw new DomainException('payment_version_conflict');
            }
            $existingPaymentAllocation = $this->exactInteger(
                $context['allocations']->sum('amount_minor'),
                'Payment allocation sum',
            );
            $newPaymentAllocation = $existingPaymentAllocation;

            foreach ($data->lines as $line) {
                if (($line->amountMinor <=> 0) !== ((int) $payment->amount_minor <=> 0)) {
                    throw new DomainException('allocation_sign_mismatch');
                }
                $invoice = $context['invoices'][$line->invoiceId->value] ?? null;
                $revision = $context['revisions'][$line->invoiceId->value] ?? null;
                if (! $invoice instanceof InvoiceRecord || ! $revision instanceof DocumentRevisionRecord) {
                    throw (new ModelNotFoundException)
                        ->setModel(InvoiceRecord::class, [$line->invoiceId->value]);
                }
                if ((string) $invoice->workflow_status === 'draft') {
                    throw new DomainException('allocation_invoice_not_finalized');
                }
                if (InvoiceRecord::query()
                    ->withoutGlobalScopes()
                    ->where('user_id', $this->ownerId())
                    ->where('cancels_invoice_id', $invoice->id)
                    ->where('workflow_status', '!=', 'draft')
                    ->exists()) {
                    throw new DomainException('allocation_invoice_cancelled');
                }
                if ((string) $revision->currency !== (string) $payment->currency) {
                    throw new DomainException('allocation_currency_mismatch');
                }
                if (($line->amountMinor <=> 0) !== ((int) $revision->gross_minor <=> 0)) {
                    throw new DomainException('allocation_invoice_sign_mismatch');
                }
                $newPaymentAllocation += $line->amountMinor;
            }
            $this->assertMagnitude($newPaymentAllocation, (int) $payment->amount_minor, 'allocation_exceeds_payment');

            $batch = $this->newBatch((int) $payment->id, 'payment.allocate', $key, $requestHash);
            $allocationIds = [];
            foreach ($data->lines as $line) {
                $allocation = new PaymentAllocationRecord;
                $allocation->forceFill([
                    'user_id' => $this->ownerId(),
                    'allocation_batch_id' => $batch->id,
                    'payment_id' => $payment->id,
                    'invoice_id' => $line->invoiceId->value,
                    'amount_minor' => $line->amountMinor,
                    'reverses_allocation_id' => null,
                    'created_at' => $this->clock->now(),
                ])->save();
                $allocationIds[] = new AllocationId((int) $allocation->id);
                $this->appendActivity(
                    $context['invoices'][$line->invoiceId->value],
                    $context['revisions'][$line->invoiceId->value],
                    'payment.allocated',
                    [
                        'payment_id' => (int) $payment->id,
                        'batch_id' => (int) $batch->id,
                        'allocation_id' => (int) $allocation->id,
                        'amount_minor' => $line->amountMinor,
                    ],
                );
            }

            $this->refreshProjections($payment, array_values(array_unique($invoiceIds)), $context['revisions']);
            $payload = [
                'batch_id' => (int) $batch->id,
                'allocation_ids' => array_map(static fn (AllocationId $id): int => $id->value, $allocationIds),
                'invoice_ids' => array_values(array_unique($invoiceIds)),
                'payment_id' => (int) $payment->id,
            ];
            $this->idempotency->complete($reservation['record_id'], 200, $payload);

            return $this->allocationResult($payload);
        }, 1);
    }

    public function reverse(
        AllocationId $id,
        IdempotencyKey $key,
        ?int $expectedPaymentVersion = null,
    ): AllocationResult {
        $requestHash = $this->requestHash([
            'allocation_id' => $id->value,
            'expected_version' => $expectedPaymentVersion,
        ]);

        return DB::transaction(function () use ($id, $key, $requestHash, $expectedPaymentVersion): AllocationResult {
            $reservation = $this->idempotency->reserve('payment.reverse', $key, $requestHash);
            if ($reservation['status'] === 'replay') {
                return $this->replayedAllocation($reservation['response_payload']);
            }
            if ($reservation['status'] !== 'new') {
                throw new DomainException('idempotency_'.$reservation['status']);
            }

            $ownerId = $this->ownerId();
            $originalLocator = PaymentAllocationRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->findOrFail($id->value);
            $context = $this->lockContext(
                new PaymentId((int) $originalLocator->payment_id),
                [(int) $originalLocator->invoice_id],
            );
            if ($expectedPaymentVersion !== null
                && (int) $context['payment']->version !== $expectedPaymentVersion) {
                throw new DomainException('payment_version_conflict');
            }
            $original = $context['allocations']->firstWhere('id', $id->value);
            if (! $original instanceof PaymentAllocationRecord) {
                throw (new ModelNotFoundException)
                    ->setModel(PaymentAllocationRecord::class, [$id->value]);
            }
            if ($original->reverses_allocation_id !== null) {
                throw new DomainException('allocation_reversal_not_reversible');
            }
            if ($context['allocations']->contains(
                static fn (PaymentAllocationRecord $allocation): bool => (int) $allocation->reverses_allocation_id === $id->value,
            )) {
                throw new DomainException('allocation_already_reversed');
            }

            $batch = $this->newBatch((int) $original->payment_id, 'payment.reverse', $key, $requestHash);
            $reversal = new PaymentAllocationRecord;
            $reversal->forceFill([
                'user_id' => $ownerId,
                'allocation_batch_id' => $batch->id,
                'payment_id' => $original->payment_id,
                'invoice_id' => $original->invoice_id,
                'amount_minor' => -(int) $original->amount_minor,
                'reverses_allocation_id' => $original->id,
                'created_at' => $this->clock->now(),
            ])->save();
            $invoiceIds = [(int) $original->invoice_id];
            $this->appendActivity(
                $context['invoices'][(int) $original->invoice_id],
                $context['revisions'][(int) $original->invoice_id],
                'payment.allocation_reversed',
                [
                    'payment_id' => (int) $original->payment_id,
                    'batch_id' => (int) $batch->id,
                    'allocation_id' => (int) $reversal->id,
                    'reverses_allocation_id' => (int) $original->id,
                    'amount_minor' => -(int) $original->amount_minor,
                ],
            );
            $this->refreshProjections($context['payment'], $invoiceIds, $context['revisions']);
            $payload = [
                'batch_id' => (int) $batch->id,
                'allocation_ids' => [(int) $reversal->id],
                'invoice_ids' => $invoiceIds,
                'payment_id' => (int) $original->payment_id,
            ];
            $this->idempotency->complete($reservation['record_id'], 200, $payload);

            return $this->allocationResult($payload);
        }, 1);
    }

    public function suggestionContext(PaymentId $id): array
    {
        $ownerId = $this->ownerId();
        $payment = $this->get($id);
        if ($payment->unappliedMinor === 0) {
            return ['payment' => $payment, 'invoices' => []];
        }
        $rows = DB::table('finance_invoices as invoices')
            ->join('finance_document_revisions as revisions', function (JoinClause $join): void {
                $join->on('revisions.user_id', '=', 'invoices.user_id')
                    ->on('revisions.document_series_id', '=', 'invoices.document_series_id')
                    ->on('revisions.id', '=', 'invoices.current_revision_id');
            })
            ->where('invoices.user_id', $ownerId)
            ->whereIn('invoices.workflow_status', ['finalized', 'sent'])
            ->where('revisions.currency', $payment->currency)
            ->where('invoices.open_minor', $payment->unappliedMinor > 0 ? '>' : '<', 0)
            ->whereNotExists(function (QueryBuilder $query) use ($ownerId): void {
                $query->selectRaw('1')
                    ->from('finance_invoices as cancellations')
                    ->whereColumn('cancellations.cancels_invoice_id', 'invoices.id')
                    ->where('cancellations.user_id', $ownerId)
                    ->where('cancellations.workflow_status', '!=', 'draft');
            })
            ->orderBy('invoices.number')
            ->orderBy('invoices.id')
            ->get([
                'invoices.id', 'invoices.number', 'invoices.open_minor',
                'invoices.issue_date', 'revisions.currency', 'revisions.snapshot',
            ]);
        $invoices = [];
        foreach ($rows as $row) {
            $number = $row->number;
            $currency = $row->currency;
            $snapshot = is_string($row->snapshot)
                ? json_decode($row->snapshot, true, 512, JSON_THROW_ON_ERROR)
                : $row->snapshot;
            $customerData = is_array($snapshot) && is_array($snapshot['customer'] ?? null)
                ? $snapshot['customer']
                : [];
            $customer = is_string($customerData['name'] ?? null) ? $customerData['name'] : '';
            if (! is_string($number) || ! is_string($currency)) {
                throw new LogicException('Payment suggestion invoice projection is incomplete.');
            }
            $invoices[] = [
                'invoice_id' => $this->exactInteger($row->id, 'Suggestion invoice ID'),
                'number' => $number,
                'currency' => $currency,
                'open_minor' => $this->exactInteger($row->open_minor, 'Suggestion open amount'),
                'issue_date' => $this->immutableDate($row->issue_date),
                'customer' => $customer,
            ];
        }

        return ['payment' => $payment, 'invoices' => $invoices];
    }

    private function ownedPayment(PaymentId $id): PaymentRecord
    {
        return PaymentRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id->value);
    }

    private function ownerId(): int
    {
        $ownerId = Auth::id();

        if (! is_numeric($ownerId) || (int) $ownerId < 1) {
            throw new LogicException('Payment persistence requires an authenticated owner.');
        }

        return (int) $ownerId;
    }

    private function assertOwnedRecordReferences(RecordPaymentData $data): void
    {
        if ($data->paymentMethodId !== null && ! DB::table('payment_methods')
            ->where('id', $data->paymentMethodId)
            ->where('user_id', $this->ownerId())
            ->whereNull('deleted_at')
            ->exists()) {
            throw (new ModelNotFoundException)->setModel(PaymentMethod::class, [$data->paymentMethodId]);
        }
        if ($data->sourceType !== 'bank_transaction') {
            return;
        }
        $sourceId = filter_var($data->sourceKey, FILTER_VALIDATE_INT);
        if (! is_int($sourceId) || $sourceId < 1 || ! DB::table('bank_transactions')
            ->where('id', $sourceId)
            ->where('user_id', $this->ownerId())
            ->whereNull('deleted_at')
            ->exists()) {
            throw (new ModelNotFoundException)->setModel(
                BankTransaction::class,
                [$data->sourceKey ?? ''],
            );
        }
    }

    private function view(PaymentRecord $payment): PaymentView
    {
        $allocated = (int) $payment->allocations()->sum('amount_minor');
        $receivedAt = $payment->getAttribute('received_at');

        if (! $receivedAt instanceof DateTimeInterface) {
            throw new LogicException('Payment received-at metadata is incomplete.');
        }

        return new PaymentView(
            new PaymentId((int) $payment->id),
            (string) $payment->uuid,
            (int) $payment->amount_minor,
            $allocated,
            (int) $payment->amount_minor - $allocated,
            (string) $payment->currency,
            DateTimeImmutable::createFromInterface($receivedAt),
            is_string($payment->reference) ? $payment->reference : null,
            is_string($payment->counterparty) ? $payment->counterparty : null,
            (int) $payment->version,
            $payment->payment_method_id !== null ? (int) $payment->payment_method_id : null,
            is_string($payment->source_type) ? $payment->source_type : null,
            is_string($payment->source_key) ? $payment->source_key : null,
        );
    }

    /**
     * @param  list<int>  $invoiceIds
     * @return array{
     *   payment: PaymentRecord,
     *   invoices: array<int, InvoiceRecord>,
     *   revisions: array<int, DocumentRevisionRecord>,
     *   allocations: Collection<int, PaymentAllocationRecord>
     * }
     */
    private function lockContext(PaymentId $paymentId, array $invoiceIds): array
    {
        $ownerId = $this->ownerId();
        $paymentLocator = $this->ownedPayment($paymentId);
        $invoiceIds = array_values(array_unique($invoiceIds));
        sort($invoiceIds, SORT_NUMERIC);
        $invoiceLocators = InvoiceRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('id', $invoiceIds)
            ->get(['id', 'document_series_id', 'current_revision_id']);
        if ($invoiceLocators->count() !== count($invoiceIds)) {
            throw (new ModelNotFoundException)
                ->setModel(InvoiceRecord::class, $invoiceIds);
        }
        $seriesIds = $invoiceLocators->map(
            fn (InvoiceRecord $invoice): int => $this->exactInteger(
                $invoice->getAttribute('document_series_id'),
                'Invoice document series ID',
            ),
        )->all();
        sort($seriesIds, SORT_NUMERIC);
        DocumentSeriesRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('id', $seriesIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
        $invoiceModels = InvoiceRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $invoices = [];
        foreach ($invoiceModels as $invoice) {
            $invoices[$this->exactInteger($invoice->getAttribute('id'), 'Invoice ID')] = $invoice;
        }
        $revisionIds = $invoiceLocators->map(
            fn (InvoiceRecord $invoice): int => $this->exactInteger(
                $invoice->getAttribute('current_revision_id'),
                'Invoice revision ID',
            ),
        )->all();
        sort($revisionIds, SORT_NUMERIC);
        $revisionModels = DocumentRevisionRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('id', $revisionIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $revisionByInvoice = [];
        foreach ($invoices as $invoice) {
            $revision = $revisionModels->firstWhere('id', (int) $invoice->current_revision_id);
            if ($revision instanceof DocumentRevisionRecord) {
                $revisionByInvoice[(int) $invoice->id] = $revision;
            }
        }
        $payment = PaymentRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereKey($paymentLocator->id)
            ->lockForUpdate()
            ->firstOrFail();
        $allocations = PaymentAllocationRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('payment_id', $payment->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return [
            'payment' => $payment,
            'invoices' => $invoices,
            'revisions' => $revisionByInvoice,
            'allocations' => $allocations,
        ];
    }

    private function newBatch(
        int $paymentId,
        string $operation,
        IdempotencyKey $key,
        string $requestHash,
    ): PaymentAllocationBatchRecord {
        $ownerId = $this->ownerId();
        $batchKeyHash = hash('sha256', $operation."\0".$key->hash());

        try {
            return DB::transaction(function () use ($ownerId, $paymentId, $batchKeyHash, $requestHash): PaymentAllocationBatchRecord {
                $batch = new PaymentAllocationBatchRecord;
                $batch->forceFill([
                    'user_id' => $ownerId,
                    'payment_id' => $paymentId,
                    'idempotency_key_hash' => $batchKeyHash,
                    'request_hash' => $requestHash,
                    'created_by' => $ownerId,
                    'created_at' => $this->clock->now(),
                ])->save();

                return $batch;
            }, 1);
        } catch (QueryException $exception) {
            if (DB::table('finance_payment_allocation_batches')
                ->where('user_id', $ownerId)
                ->where('idempotency_key_hash', $batchKeyHash)
                ->exists()) {
                throw new DomainException('allocation_idempotency_conflict', previous: $exception);
            }

            throw $exception;
        }
    }

    /**
     * @param  list<int>  $invoiceIds
     * @param  array<int, DocumentRevisionRecord>  $revisions
     */
    private function refreshProjections(PaymentRecord $payment, array $invoiceIds, array $revisions): void
    {
        $ownerId = $this->ownerId();
        foreach ($invoiceIds as $invoiceId) {
            $allocated = (int) DB::table('finance_payment_allocations')
                ->where('user_id', $ownerId)
                ->where('invoice_id', $invoiceId)
                ->sum('amount_minor');
            $revision = $revisions[$invoiceId] ?? null;
            if (! $revision instanceof DocumentRevisionRecord) {
                throw new LogicException('Locked invoice revision context is incomplete.');
            }
            $gross = (int) $revision->gross_minor;
            $this->assertMagnitude($allocated, $gross, 'invoice_overallocated');
            DB::table('finance_invoices')
                ->where('id', $invoiceId)
                ->where('user_id', $ownerId)
                ->update([
                    'allocated_minor' => $allocated,
                    'open_minor' => $gross - $allocated,
                    'version' => DB::raw('version + 1'),
                    'updated_at' => $this->clock->now(),
                ]);
        }
        DB::table('finance_payments')
            ->where('id', $payment->id)
            ->where('user_id', $ownerId)
            ->update([
                'version' => DB::raw('version + 1'),
                'updated_at' => $this->clock->now(),
            ]);
    }

    private function assertMagnitude(int $allocated, int $total, string $error): void
    {
        if ($allocated !== 0 && ($allocated <=> 0) !== ($total <=> 0)) {
            throw new DomainException($error);
        }
        if (abs($allocated) > abs($total)) {
            throw new DomainException($error);
        }
    }

    /** @param array<string, int|string|null> $payload */
    private function appendActivity(
        InvoiceRecord $invoice,
        DocumentRevisionRecord $revision,
        string $type,
        array $payload,
    ): void {
        $activity = new DocumentActivityRecord;
        $activity->forceFill([
            'user_id' => $this->ownerId(),
            'document_series_id' => $invoice->document_series_id,
            'document_revision_id' => $revision->id,
            'type' => $type,
            'payload' => $payload,
            'created_by' => $this->ownerId(),
            'created_at' => $this->clock->now(),
        ])->save();
    }

    /** @param array<string, mixed>|null $payload */
    private function replayedAllocation(?array $payload): AllocationResult
    {
        if (! is_array($payload)) {
            throw new LogicException('Stored allocation result is incomplete.');
        }

        return $this->allocationResult($payload);
    }

    /** @param array<string, mixed> $payload */
    private function allocationResult(array $payload): AllocationResult
    {
        $batchId = $payload['batch_id'] ?? null;
        $paymentId = $payload['payment_id'] ?? null;
        if (! is_int($batchId) || ! is_int($paymentId)) {
            throw new LogicException('Stored allocation result is incomplete.');
        }
        $allocationIds = $this->positiveIntegerList($payload['allocation_ids'] ?? null);
        $invoiceIds = $this->positiveIntegerList($payload['invoice_ids'] ?? null);

        return new AllocationResult(
            $batchId,
            array_map(static fn (int $id): AllocationId => new AllocationId($id), $allocationIds),
            $this->get(new PaymentId($paymentId)),
            array_map(fn (int $id) => $this->invoices->get(new InvoiceId($id)), $invoiceIds),
        );
    }

    private function exactInteger(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/\A-?(?:0|[1-9]\d*)\z/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
            if (is_int($integer)) {
                return $integer;
            }
        }

        throw new LogicException("{$field} must be an exact integer.");
    }

    private function immutableDate(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new LogicException('Payment suggestion date is incomplete.');
    }

    /** @return list<int> */
    private function positiveIntegerList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new LogicException('Stored allocation result is incomplete.');
        }
        $integers = [];
        foreach ($value as $id) {
            if (! is_int($id) || $id < 1) {
                throw new LogicException('Stored allocation result is incomplete.');
            }
            $integers[] = $id;
        }

        return $integers;
    }

    /** @param array<string, mixed> $payload */
    private function requestHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
