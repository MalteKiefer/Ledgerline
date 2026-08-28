<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

use InvalidArgumentException;

final readonly class InvoiceLineData
{
    public function __construct(
        public string $description,
        public string $quantity,
        public int $unitPriceMinor,
        public int $taxRateBasisPoints,
        public string $unit,
        public ?int $productId,
        public ?string $kind,
    ) {
        if (trim($description) === '' || trim($unit) === '') {
            throw new InvalidArgumentException('Invoice line description and unit must not be empty.');
        }
        if (preg_match('/\A-?(?:0|[1-9]\d*)\.\d{4}\z/D', $quantity) !== 1) {
            throw new InvalidArgumentException('Invoice line quantity must be a canonical scale-4 decimal.');
        }
        if ($taxRateBasisPoints < 0 || $taxRateBasisPoints > 10_000) {
            throw new InvalidArgumentException('Invoice tax rate must be between 0 and 10000 basis points.');
        }
        if ($productId !== null && $productId < 1) {
            throw new InvalidArgumentException('Invoice product IDs must be positive.');
        }
    }
}
