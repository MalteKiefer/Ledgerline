<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\FinanceCategory;
use App\Models\FinancePartner;
use App\Models\FinanceProduct;
use App\Models\PaymentMethod;
use App\Modules\Finance\Application\Ports\Projects\ProjectReferenceResolver;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class LegacyProjectReferenceResolver implements ProjectReferenceResolver
{
    public function assertOwnedPartnerReference(int $ownerId, ?string $reference): void
    {
        $this->assertOwned($ownerId, $reference, 'legacy-partner', FinancePartner::class);
    }

    public function assertOwnedPaymentMethodReference(int $ownerId, ?string $reference): void
    {
        $this->assertOwned($ownerId, $reference, 'legacy-payment-method', PaymentMethod::class);
    }

    public function assertOwnedCategoryReference(int $ownerId, ?string $reference): void
    {
        $this->assertOwned($ownerId, $reference, 'legacy-category', FinanceCategory::class);
    }

    public function assertOwnedProductReference(int $ownerId, ?string $reference): void
    {
        $this->assertOwned($ownerId, $reference, 'legacy-product', FinanceProduct::class);
    }

    /** @param class-string<Model> $model */
    private function assertOwned(int $ownerId, ?string $reference, string $prefix, string $model): void
    {
        if ($reference === null) {
            return;
        }
        if ($ownerId < 1 || preg_match('/\A'.preg_quote($prefix, '/').':([1-9][0-9]*)\z/D', $reference, $matches) !== 1) {
            throw new InvalidArgumentException('Project reference is invalid.');
        }

        $model::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $ownerId)
            ->whereKey((int) $matches[1])
            ->firstOrFail(['id']);
    }
}
