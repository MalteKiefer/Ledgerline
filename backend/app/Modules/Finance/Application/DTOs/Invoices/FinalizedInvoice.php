<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

use DateTimeImmutable;

final readonly class FinalizedInvoice
{
    public function __construct(
        public InvoiceView $invoice,
        public int $revisionId,
        public string $pdfPath,
        public string $pdfSha256,
        public DateTimeImmutable $finalizedAt,
    ) {}
}
