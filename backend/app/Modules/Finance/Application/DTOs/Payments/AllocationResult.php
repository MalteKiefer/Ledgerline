<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Payments;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;

final readonly class AllocationResult
{
    /**
     * @param  list<AllocationId>  $allocationIds
     * @param  list<InvoiceView>  $invoices
     */
    public function __construct(
        public int $batchId,
        public array $allocationIds,
        public PaymentView $payment,
        public array $invoices,
    ) {}
}
