<?php

declare(strict_types=1);

namespace App\Modules\Finance;

use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\DocumentRevisionRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectFinancialSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectRateSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectReferenceResolver;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectToInvoicePort;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteNumberAllocator;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteReferenceResolver;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteSettings;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyInvoiceDraftFromTimeAdapter;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectFinancialSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectRateSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectReferenceResolver;
use App\Modules\Finance\Infrastructure\Persistence\DatabaseQuoteNumberAllocator;
use App\Modules\Finance\Infrastructure\Persistence\EloquentDocumentRevisionRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectOperationRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectRepository;
use App\Modules\Finance\Infrastructure\Persistence\EloquentProjectWorkRepository;
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
        $this->app->bind(QuoteNumberAllocator::class, DatabaseQuoteNumberAllocator::class);
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
        $this->app->bind(ProjectWorkRepository::class, EloquentProjectWorkRepository::class);
        $this->app->bind(ProjectRateSource::class, LegacyProjectRateSource::class);
        $this->app->bind(ProjectFinancialSource::class, LegacyProjectFinancialSource::class);
        $this->app->bind(ProjectToInvoicePort::class, LegacyInvoiceDraftFromTimeAdapter::class);
    }

    public function boot(): void
    {
        require base_path('app/Modules/Finance/Http/Routes/api.php');
    }
}
