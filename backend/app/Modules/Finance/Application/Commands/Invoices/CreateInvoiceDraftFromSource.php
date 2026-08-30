<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Invoices;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\Ports\InvoiceRepository;

final readonly class CreateInvoiceDraftFromSource
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function handle(InvoiceDraftSource $source, IdempotencyKey $key): InvoiceView
    {
        return $this->invoices->createDraftFromSource($source, $key);
    }
}
