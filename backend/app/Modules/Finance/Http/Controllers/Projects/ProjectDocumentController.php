<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Modules\Finance\Application\Commands\Projects\AttachProjectDocument;
use App\Modules\Finance\Application\Commands\Projects\DetachProjectDocument;
use App\Modules\Finance\Application\Queries\Projects\GetProject;
use App\Modules\Finance\Application\Queries\Projects\ListProjectDocuments;
use App\Modules\Finance\Application\Queries\Projects\SearchProjectDocumentSources;
use App\Modules\Finance\Http\Requests\Projects\ProjectDocumentRequest;
use App\Modules\Finance\Http\Resources\Projects\ProjectDocumentResource;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class ProjectDocumentController extends ProjectController
{
    public function documents(ProjectDocumentRequest $request, string $project, ListProjectDocuments $query): JsonResponse
    {
        return response()->json((new ProjectDocumentResource($query->handle($request->documentFilter($this->projectId($request, $project)))))->resolve($request));
    }

    public function sources(ProjectDocumentRequest $request, string $project, GetProject $getProject, SearchProjectDocumentSources $query): JsonResponse
    {
        $id = $this->projectId($request, $project);
        $getProject->handle($id);

        return response()->json((new ProjectDocumentResource($query->handle($request->sourceFilter($id->ownerId))))->resolve($request));
    }

    public function attach(ProjectDocumentRequest $request, string $project, AttachProjectDocument $command): JsonResponse
    {
        try {
            $view = $command->handle($this->projectId($request, $project), $request->source(), $request->role(), $this->ownerId($request), new DateTimeImmutable, $request->idempotencyKey());
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return response()->json((new ProjectDocumentResource($view))->resolve($request), 201);
    }

    public function detach(ProjectDocumentRequest $request, string $project, int $link, DetachProjectDocument $command): JsonResponse
    {
        try {
            $view = $command->handle($this->projectId($request, $project), $link, $this->ownerId($request), new DateTimeImmutable, $request->idempotencyKey());
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return response()->json((new ProjectDocumentResource($view))->resolve($request));
    }
}
