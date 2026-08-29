<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Modules\Finance\Application\Commands\Projects\ChangeProjectStatus;
use App\Modules\Finance\Application\DTOs\Projects\ChangeProjectStatusData;
use App\Modules\Finance\Domain\Projects\ProjectStatus;
use App\Modules\Finance\Http\Requests\Projects\ProjectActionRequest;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class ProjectStatusController extends ProjectController
{
    public function __invoke(ProjectActionRequest $request, string $project, ChangeProjectStatus $command): JsonResponse
    {
        $id = $this->projectId($request, $project);
        try {
            $result = $command->handle(new ChangeProjectStatusData(
                $id,
                $request->expectedVersion(),
                ProjectStatus::from($request->status()),
                $id->ownerId,
                new DateTimeImmutable,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return $this->mutationResponse($request, $result);
    }
}
