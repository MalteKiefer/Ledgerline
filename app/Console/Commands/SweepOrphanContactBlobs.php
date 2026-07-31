<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ContactBlob;

/**
 * Reclaim stored contact avatar bytes on disk (contacts/{blob}) that have no
 * ownership ledger row — leaked/aborted uploads the client's reconcile cannot
 * see, and any bytes orphaned by an interrupted account erasure. Scheduled daily.
 */
class SweepOrphanContactBlobs extends SweepOrphanBlobs
{
    protected $signature = 'contacts:sweep-orphans';

    protected $description = 'Reclaim stored contact avatar bytes on disk that have no ownership ledger row (leaked/aborted uploads)';

    protected function prefix(): string
    {
        return 'contacts';
    }

    protected function blobModel(): string
    {
        return ContactBlob::class;
    }

    protected function configNs(): string
    {
        return 'contacts';
    }
}
