<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SealedManifestStore;
use App\Models\GalleryBlob;
use App\Models\GalleryStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Opaque zero-knowledge gallery index store: the whole photo/album/people
 * structure the browser seals with the vault key. The server only ever stores
 * and returns ciphertext + a version counter — no photo bytes, names, EXIF, GPS
 * or embeddings. The sealed blob is size-padded (see vault.js sealManifest), so
 * this store alone reveals no counts. (Residual structural metadata — photo
 * count, media type, face count — is inferable only from the separate content-
 * blob ledger, see GalleryBlobController.) The show/save protocol is shared via
 * SealedManifestStore.
 */
class GalleryStoreController extends Controller
{
    /** @use SealedManifestStore<GalleryStore> */
    use SealedManifestStore;

    protected function manifestModel(): string
    {
        return GalleryStore::class;
    }

    /** Cap generously — this is the sealed index blob, not photo bytes (64 MiB). */
    protected function manifestMaxBytes(): int
    {
        return 67108864;
    }

    /**
     * Gallery blob ledger (record shards + photo/rendition blobs), scoped to the
     * caller — drives the shard-reference integrity guard on save.
     *
     * @return Builder<GalleryBlob>
     */
    protected function manifestBlobLedger(Request $request): ?Builder
    {
        return GalleryBlob::query()->where('user_id', (int) $this->requireUser($request)->id);
    }

    protected function manifestAuditModule(Request $request): ?string
    {
        return 'gallery';
    }
}
