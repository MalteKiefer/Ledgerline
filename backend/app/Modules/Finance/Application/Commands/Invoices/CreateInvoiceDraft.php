<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Invoices;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\Ports\InvoiceRepository;

final readonly class CreateInvoiceDraft
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function handle(InvoiceDraftData $data): InvoiceView
    {
        return $this->invoices->get($this->invoices->createDraft($data));
    }
}
