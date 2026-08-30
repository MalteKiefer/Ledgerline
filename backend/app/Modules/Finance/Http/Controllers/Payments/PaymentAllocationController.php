<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Payments;

use App\Modules\Finance\Application\Commands\Payments\AllocatePayment;
use App\Modules\Finance\Application\Commands\Payments\ReversePaymentAllocation;
use App\Modules\Finance\Application\DTOs\Payments\AllocatePaymentData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationId;
use App\Modules\Finance\Application\DTOs\Payments\AllocationResult;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Application\Ports\PaymentRepository;
use App\Modules\Finance\Http\Requests\Payments\AllocatePaymentRequest;
use App\Modules\Finance\Http\Requests\Payments\ReverseAllocationRequest;
use App\Modules\Finance\Http\Resources\InvoiceResource;
use App\Modules\Finance\Http\Resources\PaymentResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class PaymentAllocationController
{
    public function allocate(
        AllocatePaymentRequest $request,
        string $payment,
        AllocatePayment $command,
        InvoiceRepository $invoices,
        PaymentRepository $payments,
    ): JsonResponse {
        try {
            $paymentId = PaymentController::paymentId($request, $payment, $payments);
            $data = new AllocatePaymentData(
                $paymentId,
                $request->lines($invoices),
                $request->expectedVersion(),
            );
            $result = $command->handle($data, $request->idempotencyKey());

            return $this->allocationResponse($request, $result, 201);
        } catch (DomainException|InvalidArgumentException $exception) {
            return PaymentController::failure($exception);
        }
    }

    public function reverse(
        ReverseAllocationRequest $request,
        string $allocation,
        ReversePaymentAllocation $command,
    ): JsonResponse {
        try {
            $result = $command->handle(
                new AllocationId($this->allocationId($allocation)),
                $request->idempotencyKey(),
                $request->expectedPaymentVersion(),
            );

            return $this->allocationResponse($request, $result);
        } catch (DomainException|InvalidArgumentException $exception) {
            return PaymentController::failure($exception);
        }
    }

    private function allocationResponse(Request $request, AllocationResult $result, int $status = 200): JsonResponse
    {
        return response()->json([
            'payment' => (new PaymentResource($result->payment))->toArray($request),
            'invoices' => array_map(
                static fn ($invoice): array => (new InvoiceResource($invoice))->toArray($request),
                $result->invoices,
            ),
        ], $status);
    }

    private function allocationId(string $value): int
    {
        if (preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
            abort(404);
        }

        return (int) $value;
    }
}
