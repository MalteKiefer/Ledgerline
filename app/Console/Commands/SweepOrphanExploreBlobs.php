<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExploreBlob;

/**
 * Reclaim stored Explore track bytes on disk (explore/{blob}) that have no
 * ownership ledger row — leaked/aborted uploads the client's reconcile cannot
 * see, and any bytes orphaned by an interrupted account erasure. Scheduled daily.
 */
class SweepOrphanExploreBlobs extends SweepOrphanBlobs
{
    protected $signature = 'explore:sweep-orphans';

    protected $description = 'Reclaim stored Explore track bytes on disk that have no ownership ledger row (leaked/aborted uploads)';

    protected function prefix(): string
    {
        return 'explore';
    }

    protected function blobModel(): string
    {
        return ExploreBlob::class;
    }

    protected function configNs(): string
    {
        return 'explore';
    }
}
