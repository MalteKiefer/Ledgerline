<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

final readonly class QuoteDraftData
{
    /**
     * @param  array<string, mixed>  $customer
     * @param  list<QuoteLineData>  $lines
     */
    public function __construct(
        public string $title,
        public ?int $partnerId,
        public array $customer,
        public ?string $issueDate,
        public ?string $validUntil,
        public string $currency,
        public array $lines,
        public string $discountType,
        public ?string $discountValue,
        public ?string $introText,
        public ?string $outroText,
        public ?string $internalNote,
        public ?int $controlNetMinor = null,
        public ?int $controlVatMinor = null,
        public ?int $controlGrossMinor = null,
    ) {}
}
