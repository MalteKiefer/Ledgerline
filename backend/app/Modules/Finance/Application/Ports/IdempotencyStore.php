<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;

interface IdempotencyStore
{
    /** @return array{record_id: int, status: string, response_status: int|null, response_payload: array<string, mixed>|null} */
    public function reserve(string $operation, IdempotencyKey $key, string $requestHash): array;

    /** @param array<string, mixed> $payload */
    public function complete(int $recordId, int $responseStatus, array $payload): void;

    /** @param array<string, mixed> $payload */
    public function fail(int $recordId, int $responseStatus, array $payload): void;
}
