<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

/**
 * Archives the finance blobs — legacy invoice PDFs (incl. per-version) and
 * attached receipts under "invoices/", plus the new finance-v2 module's
 * immutable invoice/quote revision PDFs under "finance/revisions/" — on the
 * files disk. These bytes live on disk, NOT in the database dump, so
 * without this source the receipts and invoice PDFs (GoBD-relevant
 * records) would be unbacked-up. Plaintext at rest, so the archive can
 * optionally be encrypted by the backup job like the DB dump.
 *
 * Both prefixes are retained here — the legacy path is not removed by
 * Task 16 or Task 17; only the final legacy-removal plan (out of this
 * plan's scope) retires it.
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

    protected function additionalPrefixes(): array
    {
        return ['finance/revisions'];
    }

    protected function name(): string
    {
        return 'invoices';
    }
}
