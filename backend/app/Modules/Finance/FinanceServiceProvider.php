<?php

declare(strict_types=1);

namespace App\Modules\Finance;

use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\DocumentRevisionRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectReferenceResolver;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteReferenceResolver;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteSettings;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectReferenceResolver;
use App\Modules\Finance\Infrastructure\Persistence\EloquentDocumentRevisionRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectOperationRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentQuoteOperationRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentQuoteReferenceResolver;
use App\Modules\Finance\Infrastructure\Persistence\EloquentQuoteRepository;
use App\Modules\Finance\Infrastructure\Settings\EloquentQuoteSettings;
use App\Modules\Finance\Infrastructure\Time\SystemClock;
use Illuminate\Support\ServiceProvider;

final class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            DocumentRevisionRepository::class,
            EloquentDocumentRevisionRepository::class,
        );
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->bind(
            QuoteOperationRepository::class,
            EloquentQuoteOperationRepository::class,
        );
        $this->app->bind(
            QuoteReferenceResolver::class,
            EloquentQuoteReferenceResolver::class,
        );
        $this->app->bind(QuoteRepository::class, EloquentQuoteRepository::class);
        $this->app->bind(QuoteSettings::class, EloquentQuoteSettings::class);
        $this->app->bind(ProjectRepository::class, EloquentProjectRepository::class);
        $this->app->bind(
            ProjectOperationRepository::class,
            EloquentProjectOperationRepository::class,
        );
        $this->app->bind(
            ProjectReferenceResolver::class,
            LegacyProjectReferenceResolver::class,
        );
    }

    public function boot(): void
    {
        require base_path('app/Modules/Finance/Http/Routes/api.php');
    }
}
