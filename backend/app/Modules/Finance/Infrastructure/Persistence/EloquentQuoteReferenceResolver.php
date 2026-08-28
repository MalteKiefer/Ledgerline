<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Models\FinancePartner;
use App\Models\FinanceProduct;
use App\Modules\Finance\Application\Ports\Quotes\QuoteReferenceResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use LogicException;

final class EloquentQuoteReferenceResolver implements QuoteReferenceResolver
{
    public function assertOwnedPartner(?int $partnerId): void
    {
        if ($partnerId === null) {
            return;
        }

        FinancePartner::query()
            ->ownedBy($this->ownerId())
            ->whereKey($partnerId)
            ->firstOrFail(['id']);
    }

    public function assertOwnedProducts(array $productIds): void
    {
        $ids = array_values(array_unique($productIds));

        if ($ids === []) {
            return;
        }

        $found = FinanceProduct::query()
            ->ownedBy($this->ownerId())
            ->whereKey($ids)
            ->count();

        if ($found !== count($ids)) {
            throw (new ModelNotFoundException)->setModel(FinanceProduct::class, $ids);
        }
    }

    private function ownerId(): int
    {
        $ownerId = Auth::id();

        if (! is_numeric($ownerId) || (int) $ownerId < 1) {
            throw new LogicException('Quote reference resolution requires an authenticated owner.');
        }

        return (int) $ownerId;
    }
}
