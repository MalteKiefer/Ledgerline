<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

final readonly class QuoteTotalsView
{
    /** @param list<array{tax_rate_basis_points: int, net_minor: int, vat_minor: int, gross_minor: int}> $taxBreakdowns */
    public function __construct(
        public int $netMinor,
        public int $vatMinor,
        public int $grossMinor,
        public int $discountMinor,
        public string $currency,
        public array $taxBreakdowns,
        public string $issueDate,
        public string $validUntil,
    ) {}
}
