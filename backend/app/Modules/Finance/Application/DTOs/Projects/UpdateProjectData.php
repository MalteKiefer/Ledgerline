<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Projects\ProjectBudget;
use App\Modules\Finance\Domain\Projects\ProjectKind;
use DateTimeImmutable;

final readonly class UpdateProjectData
{
    public function __construct(
        public ProjectId $projectId,
        public int $expectedVersion,
        public string $name,
        public ProjectKind $kind,
        public ProjectBudget $budget,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
        public ?string $partnerReference = null,
        public ?DateTimeImmutable $startsOn = null,
        public ?DateTimeImmutable $dueOn = null,
    ) {}
}
