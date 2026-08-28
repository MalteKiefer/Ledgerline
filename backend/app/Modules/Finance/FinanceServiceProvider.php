<?php

declare(strict_types=1);

namespace App\Modules\Finance;

use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentRevisionRepository;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\Projects\ProjectFinancialSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectFromQuoteTarget;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectRateSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectReferenceResolver;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectToInvoicePort;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteMailer;
use App\Modules\Finance\Application\Ports\Quotes\QuoteNumberAllocator;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteReferenceResolver;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteSettings;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyInvoiceDraftFromTimeAdapter;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectFinancialSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectRateSource;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectReferenceResolver;
use App\Modules\Finance\Infrastructure\Integrations\Quotes\FinanceQuoteProjectTarget;
use App\Modules\Finance\Infrastructure\Mail\CompanyMailTransport;
use App\Modules\Finance\Infrastructure\Mail\LaravelCompanyMailTransport;
use App\Modules\Finance\Infrastructure\Mail\LaravelQuoteMailer;
use App\Modules\Finance\Infrastructure\Pdf\BladeDocumentRenderer;
use App\Modules\Finance\Infrastructure\Pdf\FlysystemDocumentStorage;
use App\Modules\Finance\Infrastructure\Pdf\LocalAtomicDocumentObjectStore;
use App\Modules\Finance\Infrastructure\Pdf\S3AtomicDocumentObjectStore;
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
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentRenderer::class, BladeDocumentRenderer::class);
        $this->app->bind(DocumentStorage::class, static function (): DocumentStorage {
            $diskName = config('files.disk');
            if (! is_string($diskName) || $diskName === '') {
                throw new LogicException('The document storage disk is not configured.');
            }

            $diskConfig = config('filesystems.disks.'.$diskName);
            if (! is_array($diskConfig)) {
                throw new LogicException('The configured document storage disk does not exist.');
            }

            $driver = $diskConfig['driver'] ?? null;
            if ($driver === 'local') {
                $root = $diskConfig['root'] ?? null;
                if (! is_string($root) || $root === '') {
                    throw new LogicException('The local document storage root is not configured.');
                }

                return new FlysystemDocumentStorage(new LocalAtomicDocumentObjectStore(
                    $root,
                    storage_path('framework/finance-document-locks/'.hash('sha256', $root)),
                ));
            }

            if ($driver === 's3') {
                $disk = Storage::disk($diskName);
                $bucket = $diskConfig['bucket'] ?? null;
                $prefix = $diskConfig['root'] ?? '';
                if (! $disk instanceof AwsS3V3Adapter
                    || ! is_string($bucket)
                    || $bucket === ''
                    || ! is_string($prefix)) {
                    throw new LogicException('The S3 document storage disk is incomplete.');
                }

                return new FlysystemDocumentStorage(new S3AtomicDocumentObjectStore(
                    $disk->getClient(),
                    $bucket,
                    $prefix,
                ));
            }

            throw new LogicException('Document storage requires an atomic local or S3 disk.');
        });
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
        $this->app->bind(QuoteMailer::class, LaravelQuoteMailer::class);
        $this->app->bind(CompanyMailTransport::class, LaravelCompanyMailTransport::class);
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
        $this->app->bind(ProjectFromQuoteTarget::class, FinanceQuoteProjectTarget::class);
    }

    public function boot(): void
    {
        require base_path('app/Modules/Finance/Http/Routes/api.php');
    }
}
