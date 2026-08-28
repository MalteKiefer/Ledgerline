<?php

declare(strict_types=1);

namespace App\Modules\Finance;

use App\Modules\Finance\Application\Ports\DocumentRevisionRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentDocumentRevisionRepository;
use Illuminate\Support\ServiceProvider;

final class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            DocumentRevisionRepository::class,
            EloquentDocumentRevisionRepository::class,
        );
    }

    public function boot(): void
    {
        require base_path('app/Modules/Finance/Http/Routes/api.php');
    }
}
