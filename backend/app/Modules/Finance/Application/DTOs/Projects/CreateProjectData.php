<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Projects\ProjectBudget;
use App\Modules\Finance\Domain\Projects\ProjectKind;
use DateTimeImmutable;

final readonly class CreateProjectData
{
    public function __construct(
        public int $ownerId,
        public string $name,
        public ProjectKind $kind,
        public ProjectBudget $budget,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
        public ?ProjectId $parentId = null,
        public ?string $partnerReference = null,
        public ?DateTimeImmutable $startsOn = null,
        public ?DateTimeImmutable $dueOn = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
    ) {}
}
