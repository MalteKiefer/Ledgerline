<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SharedFolderBlob;

/**
 * Reclaim shared-folder bytes on disk (shared-folders/{blob}) that have no
 * ownership ledger row — leaked/aborted uploads the client reconcile cannot
 * see. Scheduled daily. Mirrors files:sweep-orphans.
 */
class SweepOrphanSharedFolderBlobs extends SweepOrphanBlobs
{
    protected $signature = 'shared-folders:sweep-orphans';

    protected $description = 'Reclaim shared-folder bytes on disk that have no ownership ledger row';

    protected function prefix(): string
    {
        return 'shared-folders';
    }

    protected function blobModel(): string
    {
        return SharedFolderBlob::class;
    }

    protected function configNs(): string
    {
        return 'shared-folders';
    }
}
