<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PasswordBlob;

/**
 * Reclaim stored passwords shard bytes on disk (passwords/{blob}) with no ownership
 * ledger row — leaked/aborted uploads the client's reconcile cannot see. Daily.
 */
class SweepOrphanPasswordBlobs extends SweepOrphanBlobs
{
    protected $signature = 'passwords:sweep-orphans';

    protected $description = 'Reclaim stored passwords shard bytes on disk that have no ownership ledger row';

    protected function prefix(): string
    {
        return 'passwords';
    }

    protected function blobModel(): string
    {
        return PasswordBlob::class;
    }

    protected function configNs(): string
    {
        return 'passwords';
    }
}
