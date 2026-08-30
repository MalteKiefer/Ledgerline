<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Modules\Finance\Application\Queries\Projects\GetProjectTotals;
use App\Modules\Finance\Http\Resources\Projects\ProjectTotalsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProjectTotalsController extends ProjectController
{
    public function __invoke(Request $request, string $project, GetProjectTotals $query): JsonResponse
    {
        return response()->json((new ProjectTotalsResource($query->handle($this->projectId($request, $project))))->resolve($request));
    }
}
