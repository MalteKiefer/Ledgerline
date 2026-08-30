<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDeliveryView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceDeliveryView */
final class InvoiceDeliveryResource extends JsonResource
{
    public function __construct(private readonly InvoiceDeliveryView $delivery)
    {
        parent::__construct($delivery);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $delivery = $this->delivery;

        return [
            'id' => $delivery->id->uuid,
            'kind' => $delivery->kind,
            'recipient' => $delivery->recipient,
            'status' => $delivery->status,
            'attempts' => $delivery->attempts,
            'last_attempt_at' => $delivery->lastAttemptAt?->format(DATE_ATOM),
            'sent_at' => $delivery->sentAt?->format(DATE_ATOM),
            'next_retry_at' => $delivery->nextRetryAt?->format(DATE_ATOM),
            'last_error_code' => $delivery->lastErrorCode,
            'created_at' => $delivery->createdAt->format(DATE_ATOM),
            'updated_at' => $delivery->updatedAt->format(DATE_ATOM),
        ];
    }
}
