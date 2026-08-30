<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Application\DTOs\Projects\OperationReservation;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectOperationRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectRecord;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class EloquentProjectOperationRepository implements ProjectOperationRepository
{
    public function __construct(private readonly Clock $clock) {}

    public function reserve(
        int $ownerId,
        string $operation,
        string $key,
        string $requestSha256,
        ?ProjectId $projectId,
    ): OperationReservation {
        $this->assertReservationInput($ownerId, $operation, $key, $requestSha256);
        $internalProjectId = $this->resolveProjectId($ownerId, $projectId);

        try {
            $recordId = DB::transaction(function () use ($ownerId, $operation, $key, $requestSha256, $internalProjectId): int {
                if ($internalProjectId !== null) {
                    $this->lockProject($ownerId, $internalProjectId);
                }

                return (int) DB::table('finance_project_operations')->insertGetId([
                    'user_id' => $ownerId,
                    'project_id' => $internalProjectId,
                    'operation' => $operation,
                    'idempotency_key' => $key,
                    'request_sha256' => $requestSha256,
                    'state' => 'reserved',
                    'result' => null,
                    'error_code' => null,
                    'started_at' => $this->clock->now(),
                    'completed_at' => null,
                ]);
            }, 1);

            return $this->reservation($this->ownedOperation($ownerId, $recordId), 'new');
        } catch (UniqueConstraintViolationException $exception) {
            return $this->existingReservation(
                $ownerId,
                $operation,
                $key,
                $requestSha256,
                $internalProjectId,
                $exception,
            );
        }
    }

    public function succeed(OperationReservation $reservation, array $result): void
    {
        $this->complete($reservation, 'succeeded', $result, null);
    }

    public function fail(OperationReservation $reservation, string $errorCode): void
    {
        $this->complete($reservation, 'failed', null, $errorCode);
    }

    public function retryFailed(OperationReservation $reservation): OperationReservation
    {
        return DB::transaction(function () use ($reservation): OperationReservation {
            $record = ProjectOperationRecord::query()->withoutGlobalScopes()->where('user_id', $reservation->ownerId)->whereKey($reservation->recordId)->lockForUpdate()->firstOrFail();
            if ((string) $record->state !== 'failed' || ! hash_equals((string) $record->request_sha256, $reservation->requestSha256)) {
                throw new DomainException('operation_not_retryable');
            }
            DB::table('finance_project_operations')->where('id', $record->id)->update(['state' => 'reserved', 'error_code' => null, 'result' => null, 'completed_at' => null]);

            return new OperationReservation($reservation->recordId, $reservation->ownerId, $reservation->operation, $reservation->key, $reservation->requestSha256, 'new');
        }, 1);
    }

    private function assertReservationInput(int $ownerId, string $operation, string $key, string $sha256): void
    {
        if ($ownerId < 1 || $operation === '' || strlen($operation) > 64 || $key === '' || strlen($key) > 255
            || preg_match('/\A[0-9a-f]{64}\z/D', $sha256) !== 1) {
            throw new InvalidArgumentException('Project operation reservation is invalid.');
        }
    }

    private function resolveProjectId(int $ownerId, ?ProjectId $projectId): ?int
    {
        if ($projectId === null) {
            return null;
        }
        if ($projectId->ownerId !== $ownerId) {
            throw (new ModelNotFoundException)->setModel(ProjectRecord::class, [$projectId->uuid]);
        }

        return (int) ProjectRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('uuid', $projectId->uuid)
            ->firstOrFail(['id'])
            ->id;
    }

    private function lockProject(int $ownerId, int $projectId): void
    {
        ProjectRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereKey($projectId)
            ->lockForUpdate()
            ->firstOrFail(['id']);
    }

    private function existingReservation(
        int $ownerId,
        string $operation,
        string $key,
        string $requestSha256,
        ?int $projectId,
        UniqueConstraintViolationException $exception,
    ): OperationReservation {
        $record = ProjectOperationRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('operation', $operation)
            ->where('idempotency_key', $key)
            ->first();

        if (! $record instanceof ProjectOperationRecord) {
            throw $exception;
        }
        if (! hash_equals((string) $record->request_sha256, $requestSha256)) {
            throw new DomainException('idempotency_key_reused');
        }
        $reservedProjectId = $record->project_id !== null ? (int) $record->project_id : null;
        if ($reservedProjectId !== $projectId) {
            throw new DomainException('idempotency_key_reused');
        }

        $status = match ((string) $record->state) {
            'succeeded' => 'replay',
            'failed' => 'failed',
            'reserved', 'running' => 'in_progress',
            default => throw new LogicException('Unknown project operation state.'),
        };

        return $this->reservation($record, $status);
    }

    /** @param array<string, mixed>|null $result */
    private function complete(
        OperationReservation $reservation,
        string $state,
        ?array $result,
        ?string $errorCode,
    ): void {
        DB::transaction(function () use ($reservation, $state, $result, $errorCode): void {
            $unlocked = $this->ownedOperation($reservation->ownerId, $reservation->recordId);
            if ($unlocked->project_id !== null) {
                ProjectRecord::query()
                    ->withoutGlobalScopes()
                    ->where('user_id', $reservation->ownerId)
                    ->whereKey($unlocked->project_id)
                    ->lockForUpdate()
                    ->firstOrFail(['id']);
            }

            $record = ProjectOperationRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $reservation->ownerId)
                ->whereKey($reservation->recordId)
                ->lockForUpdate()
                ->firstOrFail();
            if (! hash_equals((string) $record->request_sha256, $reservation->requestSha256)) {
                throw new DomainException('idempotency_key_reused');
            }
            if (in_array((string) $record->state, ['succeeded', 'failed'], true)) {
                return;
            }

            DB::table('finance_project_operations')
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

    private function ownedOperation(int $ownerId, int $recordId): ProjectOperationRecord
    {
        return ProjectOperationRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->findOrFail($recordId);
    }

    private function reservation(ProjectOperationRecord $record, string $status): OperationReservation
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
            throw new LogicException('Project operation result must be an object.');
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new LogicException('Project operation result must use string keys.');
            }
            $result[$key] = $item;
        }

        return $result;
    }
}
