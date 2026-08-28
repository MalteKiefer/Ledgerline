<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Invoices;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use InvalidArgumentException;

final readonly class UpdateInvoiceDraft
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function handle(InvoiceId $id, int $expectedVersion, InvoiceDraftData $data): InvoiceView
    {
        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('Expected invoice version must not be negative.');
        }

        return $this->invoices->updateDraft($id, $data, $expectedVersion);
    }
}
