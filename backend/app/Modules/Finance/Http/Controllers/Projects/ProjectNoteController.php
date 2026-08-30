<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Modules\Finance\Application\Commands\Projects\AppendProjectNote;
use App\Modules\Finance\Application\Queries\Projects\ListProjectNotes;
use App\Modules\Finance\Http\Requests\Projects\ProjectNoteRequest;
use App\Modules\Finance\Http\Resources\Projects\ProjectHistoryResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class ProjectNoteController extends ProjectController
{
    public function notes(ProjectNoteRequest $request, string $project, ListProjectNotes $query): JsonResponse
    {
        return response()->json((new ProjectHistoryResource($query->handle($this->projectId($request, $project), $request->filter())))->resolve($request));
    }

    public function append(ProjectNoteRequest $request, string $project, AppendProjectNote $command): JsonResponse
    {
        try {
            $note = $command->handle($request->projectData($this->projectId($request, $project)));
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return response()->json((new ProjectHistoryResource($note))->resolve($request), 201);
    }
}
