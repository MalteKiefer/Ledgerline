<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\CreateProjectData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectView;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Services\Projects\ProjectDataValidator;

final readonly class CreateProject
{
    public function __construct(
        private ProjectRepository $projects,
        private ProjectDataValidator $validator,
    ) {}

    public function handle(CreateProjectData $data): ProjectView
    {
        return $this->projects->create($this->validator->create($data));
    }
}
