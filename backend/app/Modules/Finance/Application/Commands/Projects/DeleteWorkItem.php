<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use App\Modules\Finance\Application\Services\Projects\ProjectDataValidator;
use App\Modules\Finance\Domain\Projects\ProjectWorkflow;
use DateTimeImmutable;

final readonly class DeleteWorkItem
{
    public function __construct(
        private ProjectWorkRepository $work,
        private ProjectRepository $projects,
        private ProjectDataValidator $validator,
        private ProjectWorkflow $workflow,
    ) {}

    public function handle(ProjectId $projectId, string $uuid, int $expectedVersion, int $actorId, DateTimeImmutable $occurredAt): void
    {
        $this->validator->actor($projectId, $actorId);
        $this->workflow->assertNotArchived($this->projects->get($projectId)->archived);
        $this->work->deleteWorkItem($projectId, $uuid, $expectedVersion, $actorId, $occurredAt);
    }
}
