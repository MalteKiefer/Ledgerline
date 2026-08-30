<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class InvoiceTimeData
{
    /** @param list<string> $timeEntryUuids */
    public function __construct(public ProjectId $projectId, public array $timeEntryUuids, public string $idempotencyKey, public int $actorId, public DateTimeImmutable $occurredAt)
    {
        if ($timeEntryUuids === [] || count($timeEntryUuids) !== count(array_unique($timeEntryUuids)) || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('invoice_time_invalid');
        }
    }
}
