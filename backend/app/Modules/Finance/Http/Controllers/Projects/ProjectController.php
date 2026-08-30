<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Projects\CreateProject;
use App\Modules\Finance\Application\Commands\Projects\UpdateProject;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectMutationResult;
use App\Modules\Finance\Application\DTOs\Projects\ProjectView;
use App\Modules\Finance\Application\Queries\Projects\GetProject;
use App\Modules\Finance\Application\Queries\Projects\ListProjects;
use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Http\Requests\Projects\ProjectListRequest;
use App\Modules\Finance\Http\Requests\Projects\ProjectWriteRequest;
use App\Modules\Finance\Http\Resources\Projects\ProjectPageResource;
use App\Modules\Finance\Http\Resources\Projects\ProjectResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProjectController
{
    public function index(ProjectListRequest $request, ListProjects $query): JsonResponse
    {
        $page = $query->handle($request->filters($this->ownerId($request)));

        return response()->json((new ProjectPageResource($page))->resolve($request));
    }

    public function store(ProjectWriteRequest $request, CreateProject $command): JsonResponse
    {
        try {
            $project = $command->handle($request->createData($this->ownerId($request)));
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return $this->projectResponse($request, $project, 201);
    }

    public function show(Request $request, string $project, GetProject $query): JsonResponse
    {
        return $this->projectResponse($request, $query->handle($this->projectId($request, $project)));
    }

    public function update(ProjectWriteRequest $request, string $project, UpdateProject $command): JsonResponse
    {
        try {
            $result = $command->handle($request->updateData($this->projectId($request, $project)));
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return $this->mutationResponse($request, $result);
    }

    protected function ownerId(Request $request): int
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return (int) $user->id;
    }

    protected function projectId(Request $request, string $uuid): ProjectId
    {
        return new ProjectId($this->ownerId($request), $uuid);
    }

    protected function mutationResponse(Request $request, ProjectMutationResult $result): JsonResponse
    {
        if (! $result->applied) {
            return response()->json([
                'error' => 'version_conflict',
                'current' => (new ProjectResource($result->current))->resolve($request),
            ], 409, ['ETag' => '"'.$result->current->version.'"']);
        }

        return $this->projectResponse($request, $result->current);
    }

    protected function projectResponse(Request $request, ProjectView $project, int $status = 200): JsonResponse
    {
        return response()->json(
            (new ProjectResource($project))->resolve($request),
            $status,
            ['ETag' => '"'.$project->version.'"'],
        );
    }

    protected function failure(DomainException|InvalidArgumentException $exception): JsonResponse
    {
        $code = $exception instanceof InvalidProjectAction ? $exception->errorCode : $exception->getMessage();
        $known = [
            'invalid_transition', 'project_archived', 'time_invoiced', 'time_entry_invoiced',
            'document_already_attached', 'document_not_attached', 'idempotency_key_reused',
            'version_conflict', 'operation_in_progress',
        ];
        if (! in_array($code, $known, true)) {
            $code = 'invalid_project_input';
        }
        if ($code === 'time_entry_invoiced') {
            $code = 'time_invoiced';
        }

        $conflicts = ['project_archived', 'time_invoiced', 'document_already_attached', 'document_not_attached', 'idempotency_key_reused', 'version_conflict', 'operation_in_progress'];

        return response()->json(['error' => $code], in_array($code, $conflicts, true) ? 409 : 422);
    }
}
