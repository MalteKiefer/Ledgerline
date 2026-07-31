<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InvoiceBlob;

/**
 * Reclaim stored invoices shard bytes on disk (invoices/{blob}) that have no ownership
 * ledger row — leaked/aborted uploads the client's reconcile cannot see. Daily.
 */
class SweepOrphanInvoiceBlobs extends SweepOrphanBlobs
{
    protected $signature = 'invoices:sweep-orphans';

    protected $description = 'Reclaim stored invoices shard bytes on disk that have no ownership ledger row';

    protected function prefix(): string
    {
        return 'invoices';
    }

    protected function blobModel(): string
    {
        return InvoiceBlob::class;
    }

    protected function configNs(): string
    {
        return 'invoices';
    }
}
