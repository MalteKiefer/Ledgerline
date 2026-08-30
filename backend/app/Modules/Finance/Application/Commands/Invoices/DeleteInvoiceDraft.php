<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Invoices;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use InvalidArgumentException;

final readonly class DeleteInvoiceDraft
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function handle(InvoiceId $id, int $expectedVersion): void
    {
        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('Expected invoice version must not be negative.');
        }

        $this->invoices->deleteDraft($id, $expectedVersion);
    }
}
