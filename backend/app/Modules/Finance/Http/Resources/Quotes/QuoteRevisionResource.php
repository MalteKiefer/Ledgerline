<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuoteRevisionRef */
final class QuoteRevisionResource extends JsonResource
{
    public function __construct(
        private readonly QuoteRevisionRef $revision,
        private readonly string $quoteUuid,
    ) {
        parent::__construct($revision);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->revision->id,
            'revision_number' => $this->revision->revisionNumber,
            'previous_revision_id' => $this->revision->previousRevisionId,
            'status' => $this->revision->status,
            'snapshot' => $this->revision->snapshot,
            'totals' => [
                'net_minor' => $this->revision->netMinor,
                'vat_minor' => $this->revision->vatMinor,
                'gross_minor' => $this->revision->grossMinor,
                'currency' => $this->revision->currency,
            ],
            'pdf_sha256' => $this->revision->pdfSha256,
            'pdf_url' => $this->revision->pdfSha256 === null ? null : route('api.finance-v2.quotes.revisions.pdf', [
                'quote' => $this->quoteUuid,
                'revision' => $this->revision->id,
            ]),
            'pdf_download_url' => $this->revision->pdfSha256 === null ? null : route('api.finance-v2.quotes.revisions.pdf', [
                'quote' => $this->quoteUuid,
                'revision' => $this->revision->id,
                'download' => 1,
            ]),
            'published_at' => $this->revision->publishedAt?->format(DATE_ATOM),
            'created_at' => $this->revision->createdAt->format(DATE_ATOM),
        ];
    }
}
