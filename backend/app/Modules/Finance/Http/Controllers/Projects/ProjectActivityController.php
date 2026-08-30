<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Modules\Finance\Application\Queries\Projects\ListProjectActivity;
use App\Modules\Finance\Http\Requests\Projects\ProjectNoteRequest;
use App\Modules\Finance\Http\Resources\Projects\ProjectHistoryResource;
use Illuminate\Http\JsonResponse;

final class ProjectActivityController extends ProjectController
{
    public function __invoke(ProjectNoteRequest $request, string $project, ListProjectActivity $query): JsonResponse
    {
        return response()->json((new ProjectHistoryResource($query->handle($this->projectId($request, $project), $request->cursor(), $request->perPage())))->resolve($request));
    }
}
