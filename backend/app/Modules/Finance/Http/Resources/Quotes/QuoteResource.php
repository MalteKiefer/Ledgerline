<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuoteView */
final class QuoteResource extends JsonResource
{
    public function __construct(private readonly QuoteView $quote)
    {
        parent::__construct($quote);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $quote = $this->quote;

        return [
            'id' => $quote->id->uuid,
            'status' => $quote->status,
            'effective_status' => $quote->effectiveStatus,
            'partner_id' => $quote->partnerId,
            'number' => $quote->number,
            'version' => $quote->version,
            'has_pending_draft' => $quote->draft !== null,
            'current_revision' => $quote->currentRevision === null
                ? null
                : (new QuoteRevisionResource($quote->currentRevision, $quote->id->uuid))->resolve($request),
            'draft' => $quote->draft,
            'totals' => [
                'net_minor' => $quote->netMinor,
                'vat_minor' => $quote->vatMinor,
                'gross_minor' => $quote->grossMinor,
                'currency' => $quote->currency,
            ],
            'conversions' => $quote->conversions,
            'delivery' => $quote->latestDelivery === null
                ? null
                : (new QuoteDeliveryResource($quote->latestDelivery))->resolve($request),
            'published_at' => $quote->publishedAt?->format(DATE_ATOM),
            'accepted_at' => $quote->acceptedAt?->format(DATE_ATOM),
            'declined_at' => $quote->declinedAt?->format(DATE_ATOM),
            'converted_at' => $quote->convertedAt?->format(DATE_ATOM),
            'created_at' => $quote->createdAt->format(DATE_ATOM),
            'updated_at' => $quote->updatedAt->format(DATE_ATOM),
        ];
    }
}
