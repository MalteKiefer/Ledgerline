<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\WorkItemView;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use App\Modules\Finance\Application\Services\Projects\ProjectDataValidator;
use App\Modules\Finance\Domain\Projects\ProjectWorkflow;
use DateTimeImmutable;

final readonly class ReorderWorkItems
{
    public function __construct(
        private ProjectWorkRepository $work,
        private ProjectRepository $projects,
        private ProjectDataValidator $validator,
        private ProjectWorkflow $workflow,
    ) {}

    /**
     * @param  list<string>  $orderedUuids
     * @return list<WorkItemView>
     */
    public function handle(ProjectId $projectId, array $orderedUuids, int $actorId, DateTimeImmutable $occurredAt): array
    {
        $this->validator->actor($projectId, $actorId);
        $this->workflow->assertNotArchived($this->projects->get($projectId)->archived);

        return $this->work->reorderWorkItems($projectId, $orderedUuids, $actorId, $occurredAt);
    }
}
