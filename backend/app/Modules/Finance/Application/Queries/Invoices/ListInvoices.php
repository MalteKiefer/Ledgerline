<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Invoices;

use App\Modules\Finance\Application\DTOs\Invoices\InvoicePage;
use App\Modules\Finance\Application\Ports\InvoiceRepository;

final readonly class ListInvoices
{
    public function __construct(private InvoiceRepository $invoices) {}

    /** @param array<string, mixed> $filters */
    public function handle(array $filters, int $page, int $perPage): InvoicePage
    {
        return $this->invoices->page($filters, $page, $perPage);
    }
}
