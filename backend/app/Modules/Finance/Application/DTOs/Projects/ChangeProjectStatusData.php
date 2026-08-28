<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Projects\ProjectStatus;
use DateTimeImmutable;

final readonly class ChangeProjectStatusData
{
    public function __construct(
        public ProjectId $projectId,
        public int $expectedVersion,
        public ProjectStatus $target,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
