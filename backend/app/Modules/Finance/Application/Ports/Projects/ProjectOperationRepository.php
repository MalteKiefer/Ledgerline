<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

use App\Modules\Finance\Application\DTOs\Projects\OperationReservation;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;

interface ProjectOperationRepository
{
    public function reserve(
        int $ownerId,
        string $operation,
        string $key,
        string $requestSha256,
        ?ProjectId $projectId,
    ): OperationReservation;

    /** @param array<string, mixed> $result */
    public function succeed(OperationReservation $reservation, array $result): void;

    public function fail(OperationReservation $reservation, string $errorCode): void;
}
