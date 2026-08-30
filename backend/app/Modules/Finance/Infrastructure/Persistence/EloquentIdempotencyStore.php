<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\IdempotencyStore;
use App\Modules\Finance\Infrastructure\Persistence\Models\IdempotencyRecord;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class EloquentIdempotencyStore implements IdempotencyStore
{
    public function __construct(private readonly Clock $clock) {}

    public function reserve(string $operation, IdempotencyKey $key, string $requestHash): array
    {
        $this->assertInput($operation, $requestHash);
        $ownerId = $this->ownerId();

        try {
            $recordId = DB::transaction(fn (): int => (int) DB::table('finance_idempotency_records')
                ->insertGetId([
                    'user_id' => $ownerId,
                    'operation' => $operation,
                    'key_hash' => $key->hash(),
                    'request_hash' => $requestHash,
                    'status' => 'pending',
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]), 1);

            return [
                'record_id' => $recordId,
                'status' => 'new',
                'response_status' => null,
                'response_payload' => null,
            ];
        } catch (UniqueConstraintViolationException $exception) {
            $record = IdempotencyRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('operation', $operation)
                ->where('key_hash', $key->hash())
                ->first();

            if (! $record instanceof IdempotencyRecord) {
                throw $exception;
            }
            if (! hash_equals((string) $record->request_hash, $requestHash)) {
                throw new DomainException('idempotency_key_reused');
            }

            return $this->reservation($record);
        }
    }

    public function complete(int $recordId, int $responseStatus, array $payload): void
    {
        $this->finish($recordId, 'completed', $responseStatus, $payload);
    }

    public function fail(int $recordId, int $responseStatus, array $payload): void
    {
        $this->finish($recordId, 'failed', $responseStatus, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function finish(int $recordId, string $status, int $responseStatus, array $payload): void
    {
        if ($recordId < 1 || $responseStatus < 100 || $responseStatus > 599) {
            throw new InvalidArgumentException('Idempotency completion metadata is invalid.');
        }
        $ownerId = $this->ownerId();

        DB::transaction(function () use ($recordId, $status, $responseStatus, $payload, $ownerId): void {
            $record = IdempotencyRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($recordId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array((string) $record->status, ['completed', 'failed'], true)) {
                return;
            }

            DB::table('finance_idempotency_records')
                ->where('id', $recordId)
                ->where('user_id', $ownerId)
                ->where('status', 'pending')
                ->update([
                    'status' => $status,
                    'response_status' => $responseStatus,
                    'response_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'completed_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);
        }, 1);
    }

    private function assertInput(string $operation, string $requestHash): void
    {
        if (trim($operation) === '' || strlen($operation) > 128
            || preg_match('/\A[0-9a-f]{64}\z/D', $requestHash) !== 1) {
            throw new InvalidArgumentException('Idempotency reservation metadata is invalid.');
        }
    }

    private function ownerId(): int
    {
        $ownerId = Auth::id();

        if (! is_numeric($ownerId) || (int) $ownerId < 1) {
            throw new LogicException('Idempotency persistence requires an authenticated owner.');
        }

        return (int) $ownerId;
    }

    /** @return array{record_id: int, status: string, response_status: int|null, response_payload: array<string, mixed>|null} */
    private function reservation(IdempotencyRecord $record): array
    {
        $status = match ((string) $record->status) {
            'pending' => 'in_progress',
            'completed' => 'replay',
            'failed' => 'failed',
            default => throw new LogicException('Unknown idempotency state.'),
        };
        $payload = $record->getAttribute('response_payload');

        if ($payload !== null && ! is_array($payload)) {
            throw new LogicException('Idempotency response payload must be an object.');
        }
        $normalizedPayload = null;
        if (is_array($payload)) {
            $normalizedPayload = [];
            foreach ($payload as $key => $value) {
                if (! is_string($key)) {
                    throw new LogicException('Idempotency response payload must be an object.');
                }
                $normalizedPayload[$key] = $value;
            }
        }

        return [
            'record_id' => (int) $record->id,
            'status' => $status,
            'response_status' => $record->response_status !== null ? (int) $record->response_status : null,
            'response_payload' => $normalizedPayload,
        ];
    }
}
