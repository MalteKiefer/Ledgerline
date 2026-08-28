<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\RateLimitServiceProvider;
use App\Modules\Finance\FinanceServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    RateLimitServiceProvider::class,
    FinanceServiceProvider::class,
];
