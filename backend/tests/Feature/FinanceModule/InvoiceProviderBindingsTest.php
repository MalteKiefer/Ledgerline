<?php

declare(strict_types=1);

namespace Tests\Feature\FinanceModule;

use App\Modules\Finance\Application\Commands\Invoices\FinalizeInvoice;
use App\Modules\Finance\Application\Ports\IdempotencyStore;
use App\Modules\Finance\Application\Ports\InventoryMovementPort;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Application\Ports\InvoiceNumberAllocator;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Infrastructure\Inventory\LegacyStockLedgerAdapter;
use App\Modules\Finance\Infrastructure\Mail\CompanyInvoiceMailer;
use App\Modules\Finance\Infrastructure\Pdf\AtomicDocumentObjectStore;
use App\Modules\Finance\Infrastructure\Pdf\LocalAtomicDocumentObjectStore;
use App\Modules\Finance\Infrastructure\Persistence\EloquentIdempotencyStore;
use App\Modules\Finance\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Modules\Finance\Infrastructure\Persistence\LockedInvoiceNumberAllocator;
use App\Modules\Finance\Infrastructure\Persistence\OrphanDocumentReconciler;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class InvoiceProviderBindingsTest extends TestCase
{
    public function test_production_container_resolves_invoice_finalization_and_orphan_reconciliation(): void
    {
        $root = storage_path('framework/testing/invoice-provider-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($root);
        $disk = 'invoice-container-pdfs';
        config()->set('files.disk', $disk);
        config()->set('filesystems.disks.'.$disk, [
            'driver' => 'local',
            'root' => $root,
            'throw' => true,
        ]);

        try {
            $this->assertInstanceOf(EloquentIdempotencyStore::class, app(IdempotencyStore::class));
            $this->assertInstanceOf(EloquentInvoiceRepository::class, app(InvoiceRepository::class));
            $this->assertInstanceOf(LockedInvoiceNumberAllocator::class, app(InvoiceNumberAllocator::class));
            $this->assertInstanceOf(LegacyStockLedgerAdapter::class, app(InventoryMovementPort::class));
            $this->assertInstanceOf(CompanyInvoiceMailer::class, app(InvoiceMailer::class));
            $this->assertInstanceOf(LocalAtomicDocumentObjectStore::class, app(AtomicDocumentObjectStore::class));
            $this->assertInstanceOf(OrphanDocumentReconciler::class, app(OrphanDocumentReconciler::class));
            $this->assertInstanceOf(FinalizeInvoice::class, app(FinalizeInvoice::class));
        } finally {
            if (str_starts_with($root, storage_path('framework/testing/invoice-provider-'))) {
                File::deleteDirectory($root);
            }
        }
    }
}
