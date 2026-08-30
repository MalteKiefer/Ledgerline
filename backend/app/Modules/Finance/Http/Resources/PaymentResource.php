<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Application\DTOs\Payments\PaymentView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentView */
final class PaymentResource extends JsonResource
{
    public function __construct(private readonly PaymentView $payment)
    {
        parent::__construct($payment);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payment = $this->payment;

        return FinanceWireValues::exactIntegerStrings([
            'id' => $payment->uuid,
            'amount_minor' => $payment->amountMinor,
            'allocated_minor' => $payment->allocatedMinor,
            'unapplied_minor' => $payment->unappliedMinor,
            'currency' => $payment->currency,
            'received_at' => $payment->receivedAt->format(DATE_ATOM),
            'reference' => $payment->reference,
            'counterparty' => $payment->counterparty,
            'payment_method_id' => $payment->paymentMethodId,
            'source' => $payment->sourceType === null ? null : [
                'type' => $payment->sourceType,
                'key' => $payment->sourceKey,
            ],
            'version' => $payment->version,
        ]);
    }
}
