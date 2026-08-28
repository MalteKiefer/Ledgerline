<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectMutationResult;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Services\Projects\ProjectDataValidator;
use DateTimeImmutable;

final readonly class RestoreProject
{
    public function __construct(
        private ProjectRepository $projects,
        private ProjectDataValidator $validator,
    ) {}

    public function handle(
        ProjectId $projectId,
        int $expectedVersion,
        int $actorId,
        DateTimeImmutable $occurredAt,
    ): ProjectMutationResult {
        $this->validator->actor($projectId, $actorId);
        $current = $this->projects->get($projectId);
        if ($current->version !== $expectedVersion) {
            return ProjectMutationResult::conflict($current);
        }
        if (! $current->archived) {
            return ProjectMutationResult::applied($current);
        }

        return $this->projects->restore($projectId, $expectedVersion, $actorId, $occurredAt);
    }
}
