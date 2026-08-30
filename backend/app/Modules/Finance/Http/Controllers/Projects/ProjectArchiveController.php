<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Modules\Finance\Application\Commands\Projects\ArchiveProject;
use App\Modules\Finance\Application\Commands\Projects\RestoreProject;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectMutationResult;
use App\Modules\Finance\Http\Requests\Projects\ProjectActionRequest;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class ProjectArchiveController extends ProjectController
{
    public function archive(ProjectActionRequest $request, string $project, ArchiveProject $command): JsonResponse
    {
        return $this->change($request, $project, fn (ProjectId $id): ProjectMutationResult => $command->handle($id, $request->expectedVersion(), $id->ownerId, new DateTimeImmutable));
    }

    public function restore(ProjectActionRequest $request, string $project, RestoreProject $command): JsonResponse
    {
        return $this->change($request, $project, fn (ProjectId $id): ProjectMutationResult => $command->handle($id, $request->expectedVersion(), $id->ownerId, new DateTimeImmutable));
    }

    /** @param \Closure(ProjectId): ProjectMutationResult $operation */
    private function change(ProjectActionRequest $request, string $project, \Closure $operation): JsonResponse
    {
        try {
            $result = $operation($this->projectId($request, $project));
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return $this->mutationResponse($request, $result);
    }
}
