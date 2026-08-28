<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Projects\WorkItemStatus;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class UpdateWorkItemData
{
    public ?DecimalQuantity $estimate;

    public function __construct(
        public ProjectId $projectId,
        public string $workItemUuid,
        public int $expectedVersion,
        public string $title,
        public WorkItemStatus $status,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
        public ?string $description = null,
        public ?DateTimeImmutable $startsOn = null,
        public ?DateTimeImmutable $dueOn = null,
        mixed $estimateHours = null,
        public bool $isMilestone = false,
        public ?string $productReference = null,
    ) {
        if ($estimateHours !== null && ! is_string($estimateHours)) {
            throw new InvalidArgumentException('work_item_estimate_must_be_decimal_string');
        }

        $this->estimate = $estimateHours !== null ? DecimalQuantity::fromString($estimateHours) : null;
    }
}
