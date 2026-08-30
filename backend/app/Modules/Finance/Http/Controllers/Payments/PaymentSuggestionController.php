<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Payments;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Application\Ports\PaymentRepository;
use App\Modules\Finance\Application\Queries\SuggestPaymentAllocations;
use App\Modules\Finance\Http\Resources\FinanceWireValues;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentSuggestionController
{
    public function __invoke(
        Request $request,
        string $payment,
        SuggestPaymentAllocations $query,
        PaymentRepository $payments,
        InvoiceRepository $invoices,
    ): JsonResponse {
        $paymentId = PaymentController::paymentId($request, $payment, $payments);
        $result = $query->forPayment($paymentId);

        return response()->json(FinanceWireValues::exactIntegerStrings([
            'status' => $result->status,
            'requires_confirmation' => $result->requiresConfirmation,
            'candidates' => array_map(
                static fn ($candidate): array => [
                    'invoice_id' => $invoices->get(new InvoiceId($candidate->invoiceId->value))->uuid,
                    'number' => $candidate->number,
                    'open_minor' => $candidate->openMinor,
                    'currency' => $candidate->currency,
                    'score' => $candidate->score,
                    'reason' => $candidate->reason,
                ],
                $result->candidates,
            ),
        ]));
    }
}
