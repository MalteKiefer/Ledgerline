<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ContactBlob;
use Carbon\Carbon;

/**
 * Zero-knowledge contacts blob store. Contact records — names, numbers, emails,
 * addresses, notes, groups — live inside the user's sealed /store workspace
 * manifest; the server never sees any of it. This controller only handles the
 * OPAQUE avatar CONTENT BLOBS at "contacts/{blob}" plus the ownership ledger
 * (contact_blobs) for quota + access control — all of which lives in the shared
 * BlobStoreController (owner-scoped raw/delete, immutable ciphertext caching).
 */
class ContactBlobController extends BlobStoreController
{
    protected function blobModel(): string
    {
        return ContactBlob::class;
    }

    protected function module(): string
    {
        return 'contacts';
    }

    /** Snap the ledger timestamp to the hour so upload times don't cluster. */
    protected function stampedAt(): Carbon
    {
        return now()->startOfHour();
    }
}
