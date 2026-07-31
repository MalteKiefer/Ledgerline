<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NoteBlob;

/**
 * Zero-knowledge notes shard blob store (store merge-safety spec §3b). Handles the
 * OPAQUE record-shard blobs at "notes/{blob}" plus the ownership ledger (notes_blobs)
 * for the integrity guard + reconcile — all in the shared BlobStoreController. The
 * server never sees a note's contents or which manifest row references a shard.
 *
 * @extends BlobStoreController<NoteBlob>
 */
class NoteBlobController extends BlobStoreController
{
    /** @return class-string<NoteBlob> */
    protected function blobModel(): string
    {
        return NoteBlob::class;
    }

    protected function module(): string
    {
        return 'notes';
    }
}
