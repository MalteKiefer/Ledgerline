<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;

final readonly class MoveProjectData
{
    public function __construct(
        public ProjectId $projectId,
        public int $expectedVersion,
        public ?ProjectId $parentId,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
