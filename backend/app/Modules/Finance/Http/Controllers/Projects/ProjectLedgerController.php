<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Modules\Finance\Application\Commands\Projects\CreateLedgerEntry;
use App\Modules\Finance\Application\Commands\Projects\DeleteLedgerEntry;
use App\Modules\Finance\Application\Commands\Projects\UpdateLedgerEntry;
use App\Modules\Finance\Application\DTOs\Projects\LedgerEntryView;
use App\Modules\Finance\Application\Queries\Projects\ListProjectLedger;
use App\Modules\Finance\Http\Requests\Projects\ProjectWorkRequest;
use App\Modules\Finance\Http\Resources\Projects\ProjectWorkResource;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class ProjectLedgerController extends ProjectController
{
    public function list(ProjectWorkRequest $request, string $project, ListProjectLedger $query): JsonResponse
    {
        $page = $query->handle(
            $this->projectId($request, $project),
            $request->optionalString('direction'),
            $request->optionalDate('from'),
            $request->optionalDate('to'),
            $request->optionalString('category_reference'),
            $request->page(),
            $request->perPage(),
        );

        return response()->json((new ProjectWorkResource($page))->resolve($request));
    }

    public function storeLedger(ProjectWorkRequest $request, string $project, CreateLedgerEntry $command): JsonResponse
    {
        return $this->write($request, fn () => $command->handle($request->ledgerData($this->projectId($request, $project))), 201);
    }

    public function updateLedger(ProjectWorkRequest $request, string $project, string $entry, UpdateLedgerEntry $command): JsonResponse
    {
        $id = $this->projectId($request, $project);

        return $this->write($request, fn () => $command->handle($id, $entry, $request->expectedVersion(), $request->ledgerData($id)));
    }

    public function deleteLedger(ProjectWorkRequest $request, string $project, string $entry, DeleteLedgerEntry $command): JsonResponse
    {
        try {
            $command->handle($this->projectId($request, $project), $entry, $request->expectedVersion(), $this->ownerId($request), new DateTimeImmutable);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return response()->json(null, 204);
    }

    /** @param \Closure(): LedgerEntryView $operation */
    private function write(ProjectWorkRequest $request, \Closure $operation, int $status = 200): JsonResponse
    {
        try {
            $value = $operation();
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return response()->json((new ProjectWorkResource($value))->resolve($request), $status);
    }
}
