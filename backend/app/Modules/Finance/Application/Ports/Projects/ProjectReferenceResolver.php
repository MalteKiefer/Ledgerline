<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

interface ProjectReferenceResolver
{
    public function assertOwnedPartnerReference(int $ownerId, ?string $reference): void;

    public function assertOwnedPaymentMethodReference(int $ownerId, ?string $reference): void;

    public function assertOwnedCategoryReference(int $ownerId, ?string $reference): void;

    public function assertOwnedProductReference(int $ownerId, ?string $reference): void;
}
