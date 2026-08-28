<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\OperationReservation;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;

interface QuoteOperationRepository
{
    public function existing(
        int $ownerId,
        string $operation,
        string $key,
        string $requestSha256,
        ?QuoteId $quoteId,
    ): ?OperationReservation;

    public function reserve(
        int $ownerId,
        string $operation,
        string $key,
        string $requestSha256,
        ?QuoteId $quoteId,
    ): OperationReservation;

    /** @param array<string, mixed> $result */
    public function checkpoint(OperationReservation $reservation, array $result): OperationReservation;

    /** @param array<string, mixed> $result */
    public function succeed(OperationReservation $reservation, array $result): void;

    public function fail(OperationReservation $reservation, string $errorCode): void;
}
