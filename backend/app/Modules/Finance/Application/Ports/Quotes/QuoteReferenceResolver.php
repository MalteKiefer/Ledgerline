<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Quotes;

interface QuoteReferenceResolver
{
    public function assertOwnedPartner(?int $partnerId): void;

    /** @param list<int> $productIds */
    public function assertOwnedProducts(array $productIds): void;
}
