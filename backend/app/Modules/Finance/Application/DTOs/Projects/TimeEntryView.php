<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;

final readonly class TimeEntryView
{
    public function __construct(
        public ProjectId $projectId,
        public string $uuid,
        public ?string $workItemUuid,
        public DateTimeImmutable $workedOn,
        public int $quantityScaled,
        public ?string $description,
        public bool $billable,
        public ?int $hourlyRateMinor,
        public string $currency,
        public ?string $invoiceTargetReference,
        public ?DateTimeImmutable $invoicedAt,
        public int $version,
    ) {}
}
