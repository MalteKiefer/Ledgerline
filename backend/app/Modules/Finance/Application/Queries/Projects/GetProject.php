<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectView;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;

final readonly class GetProject
{
    public function __construct(private ProjectRepository $projects) {}

    public function handle(ProjectId $id): ProjectView
    {
        return $this->projects->get($id);
    }
}
