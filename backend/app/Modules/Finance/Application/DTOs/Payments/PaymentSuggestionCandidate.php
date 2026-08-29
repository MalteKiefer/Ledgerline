<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Payments;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;

final readonly class PaymentSuggestionCandidate
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $number,
        public int $openMinor,
        public string $currency,
        public int $score,
        public string $reason,
    ) {}
}
