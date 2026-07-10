<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GalleryBlob;
use Carbon\Carbon;

/**
 * Zero-knowledge gallery blob store. The whole gallery structure — photo/album/
 * people organisation, names, metadata, EXIF, faces, derived renditions and the
 * reference graph — lives inside the user's sealed gallery index (the opaque
 * store, see GalleryStoreController); the server never sees any of it. This
 * controller only handles the OPAQUE CONTENT BLOBS at "gallery/{blob}" plus the
 * ownership ledger (gallery_blobs) for quota + access control — all of which
 * lives in the shared BlobStoreController.
 *
 * Residual side-channel (accepted): the ledger keeps per-blob owner, stored size
 * and created_at. Sizes are length-hidden by client-side Padmé padding (app.js
 * padBlob) and created_at is snapped to the hour (stampedAt below), so exact
 * lengths and the per-photo upload burst are blurred — but the blob COUNT itself
 * is still visible, from which photo count and rough per-photo face count remain
 * inferable. No content, name or location leaks.
 */
class GalleryBlobController extends BlobStoreController
{
    protected function blobModel(): string
    {
        return GalleryBlob::class;
    }

    protected function module(): string
    {
        return 'gallery';
    }

    /**
     * Snap the ledger timestamp to the hour so the per-photo blob cluster
     * (original/thumb/medium/meta/crops uploaded within seconds) can't be grouped
     * by upload time.
     */
    protected function stampedAt(): Carbon
    {
        return now()->startOfHour();
    }
}
