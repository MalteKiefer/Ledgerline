<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileBlob;

/**
 * Reclaim stored file bytes on disk (files/{blob}) that have no ownership ledger
 * row — leaked/aborted uploads the client's reconcile cannot see. Scheduled daily.
 */
class SweepOrphanFileBlobs extends SweepOrphanBlobs
{
    protected $signature = 'files:sweep-orphans';

    protected $description = 'Reclaim stored file bytes on disk that have no ownership ledger row (leaked/aborted uploads)';

    protected function prefix(): string
    {
        return 'files';
    }

    protected function blobModel(): string
    {
        return FileBlob::class;
    }

    protected function configNs(): string
    {
        return 'files';
    }
}
