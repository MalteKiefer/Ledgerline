<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Recurring;

use App\Modules\Finance\Application\Commands\Recurring\RetryRecurringInvoiceRun;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;
use App\Modules\Finance\Application\Queries\Recurring\GetRecurringInvoiceRun;
use App\Modules\Finance\Application\Queries\Recurring\ListRecurringInvoiceRuns;
use App\Modules\Finance\Http\Requests\Recurring\RecurringRunListRequest;
use App\Modules\Finance\Http\Resources\RecurringInvoiceRunPageResource;
use App\Modules\Finance\Http\Resources\RecurringInvoiceRunResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class RecurringInvoiceRunController
{
    public function index(
        RecurringRunListRequest $request,
        string $template,
        ListRecurringInvoiceRuns $query,
        RecurringInvoiceRepository $templates,
    ): JsonResponse {
        $id = RecurringInvoiceTemplateController::templateId($request, $template, $templates);
        $page = $query->handle(
            $id,
            $request->filters(),
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        );

        return response()->json((new RecurringInvoiceRunPageResource($page))->resolve($request));
    }

    public function retry(
        Request $request,
        string $run,
        RetryRecurringInvoiceRun $command,
        GetRecurringInvoiceRun $getRun,
        RecurringInvoiceRepository $templates,
    ): JsonResponse {
        $id = $this->runId($request, $run, $templates);
        try {
            $command->handle($id);

            return response()->json((new RecurringInvoiceRunResource($getRun->handle($id)))->resolve($request), 202);
        } catch (DomainException|InvalidArgumentException $exception) {
            $code = $this->errorCode($exception);
            $status = str_contains($code, 'conflict') || str_contains($code, 'invalid_transition') ? 409 : 422;

            return response()->json(['error' => $code], $status);
        }
    }

    private function runId(Request $request, string $uuid, RecurringInvoiceRepository $templates): RecurringRunId
    {
        RecurringInvoiceTemplateController::ownerId($request);

        return $templates->runIdForUuid($uuid);
    }

    private function errorCode(DomainException|InvalidArgumentException $exception): string
    {
        return $exception instanceof DomainException ? $exception->getMessage() : 'invalid_recurring_run_input';
    }
}
