<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Modules\Finance\Application\Commands\Projects\MoveProject;
use App\Modules\Finance\Application\DTOs\Projects\MoveProjectData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Http\Requests\Projects\ProjectActionRequest;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class ProjectMoveController extends ProjectController
{
    public function __invoke(ProjectActionRequest $request, string $project, MoveProject $command): JsonResponse
    {
        $id = $this->projectId($request, $project);
        $parent = $request->parentUuid();
        try {
            $result = $command->handle(new MoveProjectData($id, $request->expectedVersion(),
                $parent !== null ? new ProjectId($id->ownerId, $parent) : null, $id->ownerId, new DateTimeImmutable));
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return $this->mutationResponse($request, $result);
    }
}
