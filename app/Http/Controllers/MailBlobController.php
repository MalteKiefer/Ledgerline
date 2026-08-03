<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailBlob;

/**
 * Zero-knowledge mail archive blob store. Message metadata lives in
 * `mail_messages`; this controller only handles the OPAQUE sealed RFC822
 * bytes at "mail/{blob}" plus the ownership ledger (mail_blobs) for
 * owner-scoped access — all of which lives in the shared BlobStoreController
 * (owner-scoped raw, immutable ciphertext caching). Phase 1 has no client
 * upload path here: the ingestor writes blobs directly via Support\BlobStore,
 * so only the read (`raw`) route is registered.
 *
 * @extends BlobStoreController<MailBlob>
 */
class MailBlobController extends BlobStoreController
{
    /** @return class-string<MailBlob> */
    protected function blobModel(): string
    {
        return MailBlob::class;
    }

    protected function module(): string
    {
        return 'mail';
    }
}
