<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\GalleryBlob;
use App\Models\GalleryStore;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for the gallery module under zero-knowledge. The
 * whole gallery structure (photo list, albums, people, metadata, EXIF, faces)
 * lives sealed inside the gallery index (gallery_store) and the only other
 * server-side state is the opaque content blobs + their ownership ledger
 * (gallery_blobs). The export is therefore the sealed index ciphertext plus the
 * ciphertext blob inventory (ids/sizes — no plaintext); purge deletes the user's
 * stored bytes, thumbnails, ledger rows and sealed index so no orphans remain.
 *
 * Without this contributor a purge relied on the gallery_blobs / gallery_store FK
 * cascade, which drops the ledger rows but leaves the ciphertext bytes and thumbs
 * on disk forever — unreclaimable, since the orphan sweep never scanned them.
 */
final class GalleryData implements UserDataContributor
{
    public function key(): string
    {
        return 'gallery';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $blobs = GalleryBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->get(['blob', 'size', 'created_at'])
            ->map(fn (GalleryBlob $b): array => [
                'blob' => $b->blob,
                'size' => $b->size,
                'created_at' => $b->created_at,
            ])
            ->all();

        return [
            'index' => GalleryStore::query()->where('user_id', $user->getKey())->value('ciphertext'),
            'blobs' => $blobs,
        ];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();

        GalleryBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk): void {
                foreach ($blobs as $blob) {
                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete('gallery/'.$blob->blob);
                        $disk->delete('thumbs/'.$blob->blob.'.jpg');
                    }
                }

                GalleryBlob::query()
                    ->whereIn('blob', $blobs->modelKeys())
                    ->delete();
            }, 'blob');

        GalleryStore::query()->where('user_id', $user->getKey())->delete();
    }
}
