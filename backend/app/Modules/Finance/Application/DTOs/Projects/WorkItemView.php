<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Projects\WorkItemStatus;
use DateTimeImmutable;

final readonly class WorkItemView
{
    public function __construct(
        public ProjectId $projectId,
        public string $uuid,
        public string $title,
        public ?string $description,
        public WorkItemStatus $status,
        public ?DateTimeImmutable $startsOn,
        public ?DateTimeImmutable $dueOn,
        public ?int $estimateQuantityScaled,
        public bool $isMilestone,
        public int $sort,
        public ?int $sourceRevisionId,
        public ?int $sourceLineIndex,
        public ?string $productReference,
        public int $version,
    ) {}
}
