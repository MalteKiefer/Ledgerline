<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Invoices;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\Ports\InvoiceRepository;

final readonly class GetInvoice
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function handle(InvoiceId $id): InvoiceView
    {
        return $this->invoices->get($id);
    }
}
