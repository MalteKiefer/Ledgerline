<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Application\DTOs\Quotes\OperationReservation;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteOperationRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteSeriesRecord;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class EloquentQuoteOperationRepository implements QuoteOperationRepository
{
    public function __construct(private readonly Clock $clock) {}

    public function reserve(
        int $ownerId,
        string $operation,
        string $key,
        string $requestSha256,
        ?QuoteId $quoteId,
    ): OperationReservation {
        $this->validateReservationInput($ownerId, $operation, $key, $requestSha256);
        $seriesId = $this->resolveSeriesId($ownerId, $quoteId);

        try {
            $recordId = DB::transaction(fn (): int => (int) DB::table('finance_quote_operations')
                ->insertGetId([
                    'user_id' => $ownerId,
                    'document_series_id' => $seriesId,
                    'operation' => $operation,
                    'idempotency_key' => $key,
                    'request_sha256' => $requestSha256,
                    'state' => 'reserved',
                    'result' => null,
                    'error_code' => null,
                    'started_at' => $this->clock->now(),
                    'completed_at' => null,
                ]), 1);

            $record = QuoteOperationRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_operations.user_id', $ownerId)
                ->findOrFail($recordId);

            return $this->reservation($record, 'new');
        } catch (UniqueConstraintViolationException $exception) {
            return $this->existingReservation(
                $ownerId,
                $operation,
                $key,
                $requestSha256,
                $seriesId,
                $exception,
            );
        }
    }

    public function succeed(OperationReservation $reservation, array $result): void
    {
        $this->complete($reservation, 'succeeded', $result, null);
    }

    public function checkpoint(OperationReservation $reservation, array $result): OperationReservation
    {
        return DB::transaction(function () use ($reservation, $result): OperationReservation {
            $record = QuoteOperationRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_operations.user_id', $reservation->ownerId)
                ->whereKey($reservation->recordId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals((string) $record->request_sha256, $reservation->requestSha256)) {
                throw new DomainException('idempotency_key_reused');
            }
            if ((string) $record->state === 'succeeded') {
                return $this->reservation($record, 'replay');
            }
            if ((string) $record->state === 'failed') {
                return $this->reservation($record, 'failed');
            }

            $existing = $this->operationResult($record->getAttribute('result')) ?? [];
            $record->forceFill([
                'state' => 'running',
                'result' => [...$existing, ...$result],
                'error_code' => null,
                'completed_at' => null,
            ])->save();

            return $this->reservation($record, 'in_progress');
        }, 1);
    }

    public function fail(OperationReservation $reservation, string $errorCode): void
    {
        $this->complete($reservation, 'failed', null, $errorCode);
    }

    private function resolveSeriesId(int $ownerId, ?QuoteId $quoteId): ?int
    {
        if ($quoteId === null) {
            return null;
        }

        $series = DocumentSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_document_series.user_id', $ownerId)
            ->where('finance_document_series.user_id', $quoteId->ownerId)
            ->where('uuid', $quoteId->uuid)
            ->where('document_type', 'quote')
            ->firstOrFail(['id']);
        QuoteSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_series.user_id', $ownerId)
            ->where('document_series_id', $series->id)
            ->firstOrFail(['document_series_id']);

        return (int) $series->id;
    }

    private function existingReservation(
        int $ownerId,
        string $operation,
        string $key,
        string $requestSha256,
        ?int $seriesId,
        UniqueConstraintViolationException $exception,
    ): OperationReservation {
        $record = QuoteOperationRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_operations.user_id', $ownerId)
            ->where('operation', $operation)
            ->where('idempotency_key', $key)
            ->first();

        if (! $record instanceof QuoteOperationRecord) {
            throw $exception;
        }

        $recordSeriesId = $record->document_series_id !== null
            ? (int) $record->document_series_id
            : null;

        if ($recordSeriesId !== $seriesId
            || ! hash_equals((string) $record->request_sha256, $requestSha256)) {
            throw new DomainException('idempotency_key_reused');
        }

        $status = match ((string) $record->state) {
            'succeeded' => 'replay',
            'failed' => 'failed',
            'reserved', 'running' => 'in_progress',
            default => throw new LogicException('Unknown quote operation state.'),
        };

        return $this->reservation($record, $status);
    }

    private function validateReservationInput(
        int $ownerId,
        string $operation,
        string $key,
        string $requestSha256,
    ): void {
        if ($ownerId < 1) {
            throw new InvalidArgumentException('Quote operation owner ID must be positive.');
        }

        if (trim($operation) === '' || strlen($operation) > 64) {
            throw new InvalidArgumentException('Quote operation must contain between 1 and 64 bytes.');
        }

        if (trim($key) === '' || strlen($key) > 255) {
            throw new InvalidArgumentException('Quote idempotency key must contain between 1 and 255 bytes.');
        }

        if (preg_match('/\A[0-9a-f]{64}\z/D', $requestSha256) !== 1) {
            throw new InvalidArgumentException('Quote request hash must be canonical lowercase SHA-256 hex.');
        }
    }

    /** @param array<string, mixed>|null $result */
    private function complete(
        OperationReservation $reservation,
        string $state,
        ?array $result,
        ?string $errorCode,
    ): void {
        DB::transaction(function () use ($reservation, $state, $result, $errorCode): void {
            $record = QuoteOperationRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_operations.user_id', $reservation->ownerId)
                ->whereKey($reservation->recordId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals((string) $record->request_sha256, $reservation->requestSha256)) {
                throw new DomainException('idempotency_key_reused');
            }

            if (in_array((string) $record->state, ['succeeded', 'failed'], true)) {
                return;
            }

            DB::table('finance_quote_operations')
                ->where('id', $record->id)
                ->where('user_id', $reservation->ownerId)
                ->update([
                    'state' => $state,
                    'result' => $result !== null ? json_encode($result, JSON_THROW_ON_ERROR) : null,
                    'error_code' => $errorCode,
                    'completed_at' => $this->clock->now(),
                ]);
        }, 1);
    }

    private function reservation(QuoteOperationRecord $record, string $status): OperationReservation
    {
        return new OperationReservation(
            (int) $record->id,
            (int) $record->user_id,
            (string) $record->operation,
            (string) $record->idempotency_key,
            (string) $record->request_sha256,
            $status,
            $this->operationResult($record->getAttribute('result')),
            is_string($record->error_code) ? $record->error_code : null,
        );
    }

    /** @return array<string, mixed>|null */
    private function operationResult(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new LogicException('Quote operation result must be an object.');
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new LogicException('Quote operation result must use string keys.');
            }

            $result[$key] = $item;
        }

        return $result;
    }
}
