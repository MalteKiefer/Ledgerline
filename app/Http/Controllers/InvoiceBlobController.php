<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InvoiceBlob;

/**
 * Zero-knowledge invoices shard blob store (store merge-safety spec §3b). Handles the
 * OPAQUE record-shard blobs at "invoices/{blob}" plus the ownership ledger (invoices_blobs)
 * for the integrity guard + reconcile — all in the shared BlobStoreController. The
 * server never sees an invoice's contents or which manifest row references a shard.
 *
 * @extends BlobStoreController<InvoiceBlob>
 */
class InvoiceBlobController extends BlobStoreController
{
    /** @return class-string<InvoiceBlob> */
    protected function blobModel(): string
    {
        return InvoiceBlob::class;
    }

    protected function module(): string
    {
        return 'invoices';
    }
}
