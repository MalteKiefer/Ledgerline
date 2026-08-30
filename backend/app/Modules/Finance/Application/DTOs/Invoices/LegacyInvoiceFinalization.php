<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Historical facts an already-issued legacy invoice number/PDF/timestamp
 * migration must reproduce exactly rather than recompute. Unlike a live
 * finalize (which allocates the NEXT number and renders a fresh PDF), a
 * migrated invoice already happened: its number was already handed to a
 * customer and its PDF bytes were already sent — reproducing anything else
 * would silently rewrite GoBD-relevant history.
 */
final readonly class LegacyInvoiceFinalization
{
    public function __construct(
        public string $number,
        public int $year,
        public int $sequence,
        public DateTimeImmutable $finalizedAt,
        public ?DateTimeImmutable $sentAt,
        public ?int $cancelsInvoiceId,
        public string $pdfBytes,
    ) {
        if (trim($number) === '') {
            throw new InvalidArgumentException('Legacy invoice number must not be empty.');
        }
        if ($year < 1) {
            throw new InvalidArgumentException('Legacy invoice year must be positive.');
        }
        if ($sequence < 1) {
            throw new InvalidArgumentException('Legacy invoice sequence must be positive.');
        }
        if ($cancelsInvoiceId !== null && $cancelsInvoiceId < 1) {
            throw new InvalidArgumentException('Legacy cancels-invoice ID must be positive.');
        }
        if (! str_starts_with($pdfBytes, '%PDF-')) {
            throw new InvalidArgumentException('Legacy invoice PDF bytes are not a PDF.');
        }
    }
}
