<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Invoices;

use App\Modules\Finance\Application\Commands\Invoices\CancelInvoice;
use App\Modules\Finance\Application\Commands\Invoices\FinalizeInvoice;
use App\Modules\Finance\Application\DTOs\Invoices\CancelInvoiceData;
use App\Modules\Finance\Application\DTOs\Invoices\FinalizedInvoice;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Application\Queries\Invoices\GetInvoice;
use App\Modules\Finance\Http\Requests\Invoices\InvoiceActionRequest;
use App\Modules\Finance\Http\Resources\InvoiceResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class InvoiceWorkflowController
{
    public function finalize(
        InvoiceActionRequest $request,
        string $invoice,
        FinalizeInvoice $command,
        GetInvoice $getInvoice,
        InvoiceRepository $invoices,
    ): JsonResponse {
        $id = InvoiceController::invoiceId($request, $invoice, $invoices);
        try {
            $finalized = $command->handle($id, $request->idempotencyKey());

            return $this->finalizedResponse($request, $finalized);
        } catch (DomainException|InvalidArgumentException $exception) {
            return InvoiceController::actionFailure($request, $exception, $id, $getInvoice);
        }
    }

    public function cancel(
        InvoiceActionRequest $request,
        string $invoice,
        CancelInvoice $command,
        GetInvoice $getInvoice,
        InvoiceRepository $invoices,
    ): JsonResponse {
        $id = InvoiceController::invoiceId($request, $invoice, $invoices);
        try {
            $finalized = $command->handle(new CancelInvoiceData($id), $request->idempotencyKey());

            return $this->finalizedResponse($request, $finalized, 201);
        } catch (DomainException|InvalidArgumentException $exception) {
            return InvoiceController::actionFailure($request, $exception, $id, $getInvoice);
        }
    }

    private function finalizedResponse(Request $request, FinalizedInvoice $finalized, int $status = 200): JsonResponse
    {
        $payload = (new InvoiceResource($finalized->invoice))->toArray($request);
        $payload['revision'] = [
            'id' => $finalized->revisionId,
            'pdf_sha256' => $finalized->pdfSha256,
            'finalized_at' => $finalized->finalizedAt->format(DATE_ATOM),
        ];

        return response()->json($payload, $status, ['ETag' => '"'.$finalized->invoice->version.'"']);
    }
}
