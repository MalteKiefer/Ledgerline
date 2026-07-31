<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NoteBlob;

/**
 * Reclaim stored notes shard bytes on disk (notes/{blob}) that have no ownership
 * ledger row — leaked/aborted uploads the client's reconcile cannot see. Daily.
 */
class SweepOrphanNoteBlobs extends SweepOrphanBlobs
{
    protected $signature = 'notes:sweep-orphans';

    protected $description = 'Reclaim stored notes shard bytes on disk that have no ownership ledger row';

    protected function prefix(): string
    {
        return 'notes';
    }

    protected function blobModel(): string
    {
        return NoteBlob::class;
    }

    protected function configNs(): string
    {
        return 'notes';
    }
}
