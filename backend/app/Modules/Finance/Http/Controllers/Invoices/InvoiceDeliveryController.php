<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Invoices;

use App\Modules\Finance\Application\Commands\Invoices\QueueInvoiceDelivery;
use App\Modules\Finance\Application\Commands\Invoices\QueueInvoiceReminder;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Application\Queries\Invoices\GetInvoice;
use App\Modules\Finance\Application\Queries\Invoices\GetInvoiceDelivery;
use App\Modules\Finance\Http\Requests\Invoices\InvoiceDeliveryRequest;
use App\Modules\Finance\Http\Requests\Invoices\InvoiceReminderRequest;
use App\Modules\Finance\Http\Resources\InvoiceDeliveryResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class InvoiceDeliveryController
{
    public function send(
        InvoiceDeliveryRequest $request,
        string $invoice,
        QueueInvoiceDelivery $command,
        GetInvoiceDelivery $getDelivery,
        GetInvoice $getInvoice,
        InvoiceRepository $invoices,
    ): JsonResponse {
        $id = InvoiceController::invoiceId($request, $invoice, $invoices);
        try {
            $deliveryId = $command->handle($id, $request->recipient(), $request->idempotencyKey());

            return response()->json(
                (new InvoiceDeliveryResource($getDelivery->handle($deliveryId)))->resolve($request),
                202,
            );
        } catch (DomainException|InvalidArgumentException $exception) {
            return InvoiceController::actionFailure($request, $exception, $id, $getInvoice);
        }
    }

    public function remind(
        InvoiceReminderRequest $request,
        string $invoice,
        QueueInvoiceReminder $command,
        GetInvoiceDelivery $getDelivery,
        GetInvoice $getInvoice,
        InvoiceRepository $invoices,
    ): JsonResponse {
        $id = InvoiceController::invoiceId($request, $invoice, $invoices);
        try {
            $deliveryId = $command->handle($id, $request->level(), $request->recipient());

            return response()->json(
                (new InvoiceDeliveryResource($getDelivery->handle($deliveryId)))->resolve($request),
                202,
            );
        } catch (DomainException|InvalidArgumentException $exception) {
            return InvoiceController::actionFailure($request, $exception, $id, $getInvoice);
        }
    }
}
