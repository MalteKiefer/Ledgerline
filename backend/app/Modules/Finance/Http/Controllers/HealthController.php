<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Http\Resources\FinanceModuleResource;

final class HealthController
{
    public function __invoke(): FinanceModuleResource
    {
        return new FinanceModuleResource(null);
    }
}
