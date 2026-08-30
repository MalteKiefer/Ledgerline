<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

use App\Modules\Finance\Domain\Shared\Money;

interface ProjectRateSource
{
    public function frozenRate(int $ownerId, ?string $partnerReference, string $currency): ?Money;
}
