<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Invoices;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Invoices\CreateInvoiceDraft;
use App\Modules\Finance\Application\Commands\Invoices\DeleteInvoiceDraft;
use App\Modules\Finance\Application\Commands\Invoices\UpdateInvoiceDraft;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Application\Queries\Invoices\GetInvoice;
use App\Modules\Finance\Application\Queries\Invoices\ListInvoices;
use App\Modules\Finance\Domain\Invoices\Exception\InvalidInvoiceState;
use App\Modules\Finance\Domain\Shared\Exception\InvalidDocument;
use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;
use App\Modules\Finance\Domain\Shared\Exception\InvalidQuantity;
use App\Modules\Finance\Http\Requests\Invoices\InvoiceActionRequest;
use App\Modules\Finance\Http\Requests\Invoices\InvoiceDraftRequest;
use App\Modules\Finance\Http\Requests\Invoices\InvoiceListRequest;
use App\Modules\Finance\Http\Resources\InvoicePageResource;
use App\Modules\Finance\Http\Resources\InvoiceResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InvoiceController
{
    public function index(InvoiceListRequest $request, ListInvoices $query): JsonResponse
    {
        $page = $query->handle(
            $request->filters(),
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        );

        return response()->json((new InvoicePageResource($page))->resolve($request));
    }

    public function store(InvoiceDraftRequest $request, CreateInvoiceDraft $command): JsonResponse
    {
        try {
            $invoice = $command->handle($request->draft());
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return $this->invoiceResponse($request, $invoice, 201);
    }

    public function show(Request $request, string $invoice, GetInvoice $query, InvoiceRepository $invoices): JsonResponse
    {
        return $this->invoiceResponse($request, $query->handle($this->invoiceId($request, $invoice, $invoices)));
    }

    public function update(
        InvoiceDraftRequest $request,
        string $invoice,
        UpdateInvoiceDraft $command,
        GetInvoice $getInvoice,
        InvoiceRepository $invoices,
    ): JsonResponse {
        $id = $this->invoiceId($request, $invoice, $invoices);
        try {
            $expectedVersion = $request->expectedVersion();
            $view = $command->handle($id, $expectedVersion, $request->draft());
            if ($view->version !== $expectedVersion + 1) {
                throw new DomainException('version_conflict');
            }

            return $this->invoiceResponse($request, $view);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->actionFailure($request, $exception, $id, $getInvoice);
        }
    }

    public function destroy(
        InvoiceActionRequest $request,
        string $invoice,
        DeleteInvoiceDraft $command,
        GetInvoice $getInvoice,
        InvoiceRepository $invoices,
    ): JsonResponse {
        $id = $this->invoiceId($request, $invoice, $invoices);
        try {
            $command->handle($id, $request->expectedVersion());

            return response()->json(null, 204);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->actionFailure($request, $exception, $id, $getInvoice);
        }
    }

    public static function ownerId(Request $request): int
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return (int) $user->id;
    }

    public static function invoiceId(Request $request, string $uuid, InvoiceRepository $invoices): InvoiceId
    {
        self::ownerId($request);

        return $invoices->idForUuid($uuid);
    }

    public static function invoiceResponse(Request $request, InvoiceView $invoice, int $status = 200): JsonResponse
    {
        return response()->json(
            (new InvoiceResource($invoice))->resolve($request),
            $status,
            ['ETag' => '"'.$invoice->version.'"'],
        );
    }

    public static function actionFailure(
        Request $request,
        DomainException|InvalidArgumentException $exception,
        InvoiceId $id,
        GetInvoice $getInvoice,
    ): JsonResponse {
        $code = self::errorCode($exception);
        $status = self::conflictCode($code) ? 409 : 422;
        $payload = ['error' => $code];

        if ($status === 409) {
            $current = $getInvoice->handle($id);
            $payload['current'] = (new InvoiceResource($current))->resolve($request);

            return response()->json($payload, $status, ['ETag' => '"'.$current->version.'"']);
        }

        return response()->json($payload, $status);
    }

    public static function failure(DomainException|InvalidArgumentException $exception): JsonResponse
    {
        $code = self::errorCode($exception);

        return response()->json(['error' => $code], self::conflictCode($code) ? 409 : 422);
    }

    private static function conflictCode(string $code): bool
    {
        return str_contains($code, 'idempotency')
            || str_contains($code, 'conflict')
            || str_contains($code, 'stale')
            || str_contains($code, 'operation_in_progress');
    }

    /**
     * Every invoice-module DomainException already carries its stable error
     * code as the exception message (see EloquentInvoiceRepository,
     * CancelInvoice, CompanyInvoiceMailer, QueueInvoiceReminder). Only
     * InvalidArgumentException from DTO constructors carries a free-text,
     * human-readable message that still needs mapping to a stable code.
     */
    private static function errorCode(DomainException|InvalidArgumentException $exception): string
    {
        if ($exception instanceof InvalidInvoiceState) {
            return $exception->errorCode;
        }
        if ($exception instanceof InvalidMoney) {
            return 'invalid_money';
        }
        if ($exception instanceof InvalidQuantity) {
            return 'invalid_quantity';
        }
        if ($exception instanceof InvalidDocument) {
            return 'invalid_document';
        }
        if ($exception instanceof DomainException) {
            return $exception->getMessage();
        }

        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'tax rate') => 'invalid_tax_rate',
            str_contains($message, 'discount') => 'invalid_discount',
            str_contains($message, 'due date'), str_contains($message, 'dates') => 'invalid_invoice_dates',
            str_contains($message, 'customer') => 'invalid_customer',
            str_contains($message, 'partner') => 'invalid_partner',
            str_contains($message, 'product') => 'invalid_product',
            default => 'invalid_invoice_input',
        };
    }
}
