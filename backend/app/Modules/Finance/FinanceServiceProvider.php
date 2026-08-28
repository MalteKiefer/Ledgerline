<?php

declare(strict_types=1);

namespace App\Modules\Finance;

use Illuminate\Support\ServiceProvider;

final class FinanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        require base_path('app/Modules/Finance/Http/Routes/api.php');
    }
}
