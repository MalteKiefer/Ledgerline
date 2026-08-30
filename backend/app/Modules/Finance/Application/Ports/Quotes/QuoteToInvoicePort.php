<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;

interface QuoteToInvoicePort
{
    /** @param array<array-key, mixed> $immutableSnapshot */
    public function createDraft(
        int $ownerId,
        QuoteRevisionRef $source,
        array $immutableSnapshot,
    ): InvoiceDraftTarget;
}
