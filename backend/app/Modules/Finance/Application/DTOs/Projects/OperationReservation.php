<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

final readonly class OperationReservation
{
    /** @param array<string, mixed>|null $result */
    public function __construct(
        public int $recordId,
        public int $ownerId,
        public string $operation,
        public string $key,
        public string $requestSha256,
        public string $status,
        public ?array $result = null,
        public ?string $errorCode = null,
    ) {}
}
