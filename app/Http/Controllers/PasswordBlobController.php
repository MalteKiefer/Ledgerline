<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PasswordBlob;

/**
 * Zero-knowledge passwords shard blob store (merge-safety spec §3b). Opaque
 * record-shard blobs at "passwords/{blob}" + the ownership ledger (passwords_blobs).
 * The server never sees a secret's contents. Mirrors NoteBlobController.
 *
 * @extends BlobStoreController<PasswordBlob>
 */
class PasswordBlobController extends BlobStoreController
{
    /** @return class-string<PasswordBlob> */
    protected function blobModel(): string
    {
        return PasswordBlob::class;
    }

    protected function module(): string
    {
        return 'passwords';
    }
}
