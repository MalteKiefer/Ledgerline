<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared;

final readonly class TaxBreakdown
{
    public function __construct(
        public int $taxRateBasisPoints,
        public Money $net,
        public Money $vat,
        public Money $gross,
    ) {}
}
