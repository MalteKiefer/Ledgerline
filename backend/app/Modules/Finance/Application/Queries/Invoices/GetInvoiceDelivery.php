<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Invoices;

use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDeliveryView;
use App\Modules\Finance\Application\Ports\InvoiceRepository;

final readonly class GetInvoiceDelivery
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function handle(DeliveryId $id): InvoiceDeliveryView
    {
        return $this->invoices->deliveryView($id);
    }
}
