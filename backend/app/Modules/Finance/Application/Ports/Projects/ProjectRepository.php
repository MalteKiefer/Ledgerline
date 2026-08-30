<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ChangeProjectStatusData;
use App\Modules\Finance\Application\DTOs\Projects\CreateProjectData;
use App\Modules\Finance\Application\DTOs\Projects\MoveProjectData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectListFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectMutationResult;
use App\Modules\Finance\Application\DTOs\Projects\ProjectPage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectView;
use App\Modules\Finance\Application\DTOs\Projects\UpdateProjectData;
use DateTimeImmutable;

interface ProjectRepository
{
    public function get(ProjectId $id): ProjectView;

    public function page(ProjectListFilter $filter): ProjectPage;

    public function create(CreateProjectData $data): ProjectView;

    public function update(UpdateProjectData $data): ProjectMutationResult;

    public function changeStatus(ChangeProjectStatusData $data): ProjectMutationResult;

    public function move(MoveProjectData $data): ProjectMutationResult;

    public function archive(
        ProjectId $id,
        int $expectedVersion,
        int $actorId,
        DateTimeImmutable $occurredAt,
    ): ProjectMutationResult;

    public function restore(
        ProjectId $id,
        int $expectedVersion,
        int $actorId,
        DateTimeImmutable $occurredAt,
    ): ProjectMutationResult;
}
