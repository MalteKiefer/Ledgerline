<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ChangeProjectStatusData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectMutationResult;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Services\Projects\ProjectDataValidator;
use App\Modules\Finance\Domain\Projects\ProjectWorkflow;

final readonly class ChangeProjectStatus
{
    public function __construct(
        private ProjectRepository $projects,
        private ProjectDataValidator $validator,
        private ProjectWorkflow $workflow,
    ) {}

    public function handle(ChangeProjectStatusData $data): ProjectMutationResult
    {
        $this->validator->actor($data->projectId, $data->actorId);
        $current = $this->projects->get($data->projectId);
        if ($current->version !== $data->expectedVersion) {
            return ProjectMutationResult::conflict($current);
        }

        $this->workflow->assertNotArchived($current->archived);
        if ($this->workflow->canReopen($current->status, $data->target)) {
            $this->workflow->assertCanReopen($current->status, $data->target);
        } else {
            $this->workflow->assertCan($current->status, $data->target);
        }

        return $this->projects->changeStatus($data);
    }
}
