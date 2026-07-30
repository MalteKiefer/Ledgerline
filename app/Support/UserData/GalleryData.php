<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\GalleryPhoto;
use App\Models\User;
use App\Support\BlobStore;

/**
 * Per-user data contributor for the plaintext-relational Gallery core (pivot).
 * Photos/videos live as rows in `gallery_photos` (albums cascade with the user
 * delete via their FK); the original bytes plus the server-generated renditions
 * (thumb/medium/motion) live plaintext on the file disk at each row's *_path
 * columns. Export is a plaintext inventory of the user's photos; purge deletes
 * every photo's disk bytes (all renditions) and rows — including trashed ones,
 * which still occupy disk — so no orphaned bytes remain.
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
        $photos = GalleryPhoto::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get(['id', 'kind', 'mime', 'size', 'width', 'height', 'taken_at', 'created_at'])
            ->map(fn (GalleryPhoto $p): array => [
                'id' => $p->id,
                'kind' => $p->kind,
                'mime' => $p->mime,
                'size' => $p->size,
                'width' => $p->width,
                'height' => $p->height,
                'taken_at' => $p->taken_at,
                'created_at' => $p->created_at,
            ])
            ->all();

        return ['photos' => $photos];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();

        GalleryPhoto::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->chunkById(500, function ($photos) use ($disk): void {
                foreach ($photos as $photo) {
                    foreach ($photo->storagePaths() as $path) {
                        $disk->delete($path);
                    }
                }

                GalleryPhoto::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $photos->modelKeys())
                    ->forceDelete();
            });
    }
}
