<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\FinancePartner;
use App\Modules\Finance\Application\Ports\Projects\ProjectRateSource;
use App\Modules\Finance\Domain\Shared\Money;

final class LegacyProjectRateSource implements ProjectRateSource
{
    public function frozenRate(int $ownerId, ?string $partnerReference, string $currency): ?Money
    {
        if ($partnerReference === null || preg_match('/\Alegacy-partner:(\d+)\z/D', $partnerReference, $m) !== 1) {
            return null;
        } $partner = FinancePartner::query()->withoutGlobalScopes()->where('user_id', $ownerId)->whereKey((int) $m[1])->first();
        if (! $partner || $partner->hourly_rate === null || strtoupper((string) $partner->currency) !== strtoupper($currency)) {
            return null;
        }

        return Money::fromDecimal((string) $partner->hourly_rate, (string) $partner->currency);
    }
}
