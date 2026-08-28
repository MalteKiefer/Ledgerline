<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectMutationResult;
use App\Modules\Finance\Application\DTOs\Projects\UpdateProjectData;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Services\Projects\ProjectDataValidator;
use App\Modules\Finance\Domain\Projects\ProjectWorkflow;

final readonly class UpdateProject
{
    public function __construct(
        private ProjectRepository $projects,
        private ProjectDataValidator $validator,
        private ProjectWorkflow $workflow,
    ) {}

    public function handle(UpdateProjectData $data): ProjectMutationResult
    {
        $this->validator->actor($data->projectId, $data->actorId);
        $current = $this->projects->get($data->projectId);
        if ($current->version !== $data->expectedVersion) {
            return ProjectMutationResult::conflict($current);
        }

        $this->workflow->assertNotArchived($current->archived);

        return $this->projects->update($this->validator->update($data));
    }
}
