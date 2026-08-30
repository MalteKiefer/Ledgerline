<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Modules\Finance\Application\Commands\Projects\CreateInvoiceDraftFromTime;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceTimeData;
use App\Modules\Finance\Http\Requests\Projects\ProjectWorkRequest;
use App\Modules\Finance\Http\Resources\Projects\ProjectWorkResource;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class ProjectTimeInvoiceController extends ProjectController
{
    public function __invoke(ProjectWorkRequest $request, string $project, CreateInvoiceDraftFromTime $command): JsonResponse
    {
        $id = $this->projectId($request, $project);
        try {
            $target = $command->handle(new InvoiceTimeData($id, $request->ids('time_entry_ids'), $request->idempotencyKey(), $id->ownerId, new DateTimeImmutable));
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return response()->json((new ProjectWorkResource($target))->resolve($request), 201);
    }
}
