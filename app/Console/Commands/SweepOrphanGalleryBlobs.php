<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GalleryBlob;

/**
 * Reclaim stored gallery bytes on disk (gallery/{blob}) that have no ownership
 * ledger row — leaked/aborted uploads the client's reconcile cannot see, and any
 * bytes orphaned by an interrupted account erasure. Scheduled daily.
 */
class SweepOrphanGalleryBlobs extends SweepOrphanBlobs
{
    protected $signature = 'gallery:sweep-orphans';

    protected $description = 'Reclaim stored gallery bytes on disk that have no ownership ledger row (leaked/aborted uploads)';

    protected function prefix(): string
    {
        return 'gallery';
    }

    protected function blobModel(): string
    {
        return GalleryBlob::class;
    }

    protected function configNs(): string
    {
        return 'gallery';
    }
}
