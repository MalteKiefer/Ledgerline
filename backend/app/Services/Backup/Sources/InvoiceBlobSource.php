<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

/**
 * Archives the finance blobs — invoice PDFs (incl. per-version) and attached
 * receipts — stored under the "invoices/" prefix on the files disk. These bytes
 * live on disk, NOT in the database dump, so without this source the receipts and
 * invoice PDFs (GoBD-relevant records) would be unbacked-up. Plaintext at rest,
 * so the archive can optionally be encrypted by the backup job like the DB dump.
 *
 * A plain archive source: the finance blob set is small
 * and there is no single-column blob ledger to drive an incremental delta, so it
 * is tarred whole on each run.
 */
final class InvoiceBlobSource extends DiskArchiveSource
{
    protected function prefix(): string
    {
        return 'invoices';
    }

    protected function name(): string
    {
        return 'invoices';
    }
}
