<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExploreBlob;

/**
 * Zero-knowledge Explore blob store. Explore records — tracks, couplings,
 * tolerances — live inside the user's sealed `explore` module store; the server
 * never sees any of it. This controller only handles the OPTIONAL OPAQUE RAW
 * TRACK BLOBS at "explore/{blob}" plus the ownership ledger (explore_blobs) for
 * quota + access control — all of which lives in the shared BlobStoreController
 * (owner-scoped raw/delete, immutable ciphertext caching).
 *
 * @extends BlobStoreController<ExploreBlob>
 */
class ExploreBlobController extends BlobStoreController
{
    /** @return class-string<ExploreBlob> */
    protected function blobModel(): string
    {
        return ExploreBlob::class;
    }

    protected function module(): string
    {
        return 'explore';
    }
}
