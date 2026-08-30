<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use UnexpectedValueException;

/** @mixin DocumentRevisionRecord */
final class InvoiceRevisionResource extends JsonResource
{
    public function __construct(
        private readonly DocumentRevisionRecord $revision,
        private readonly string $invoiceUuid,
    ) {
        parent::__construct($revision);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $publishedAt = $this->revision->getAttribute('published_at');
        $snapshot = $this->revision->getAttribute('snapshot');
        $sha256 = $this->revision->getAttribute('pdf_sha256');

        return [
            'id' => $this->integerAttribute('id'),
            'revision_number' => $this->integerAttribute('revision_number'),
            'status' => $this->stringAttribute('status'),
            'snapshot' => is_array($snapshot) ? $snapshot : [],
            'net_minor' => $this->integerAttribute('net_minor'),
            'vat_minor' => $this->integerAttribute('vat_minor'),
            'gross_minor' => $this->integerAttribute('gross_minor'),
            'currency' => $this->stringAttribute('currency'),
            'pdf_sha256' => is_string($sha256) ? $sha256 : null,
            'pdf_url' => route('api.finance-v2.invoices.revisions.pdf', [
                'invoice' => $this->invoiceUuid,
                'revision' => $this->revision->getKey(),
            ]),
            'published_at' => $publishedAt instanceof DateTimeInterface
                ? $publishedAt->format(DATE_ATOM)
                : null,
        ];
    }

    private function integerAttribute(string $name): int
    {
        $value = $this->revision->getAttribute($name);
        if (! is_int($value)) {
            throw new UnexpectedValueException("Invoice revision {$name} must be an integer.");
        }

        return $value;
    }

    private function stringAttribute(string $name): string
    {
        $value = $this->revision->getAttribute($name);
        if (! is_string($value)) {
            throw new UnexpectedValueException("Invoice revision {$name} must be a string.");
        }

        return $value;
    }
}
