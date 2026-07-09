<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\FileBlob;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for the files module under zero-knowledge. The file
 * tree (names, folders, tags, notes, versions) lives sealed inside the opaque
 * store and is exported/erased by StoreData; the only server-side files state is
 * the opaque content blobs + their ownership ledger (file_blobs). The export is
 * therefore just the ciphertext blob inventory (ids/sizes — no plaintext), and
 * purge deletes the user's stored bytes and ledger rows so no orphans remain.
 */
final class FilesData implements UserDataContributor
{
    public function key(): string
    {
        return 'files';
    }

    public function export(User $user): array
    {
        $blobs = FileBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->get(['blob', 'size', 'created_at'])
            ->map(fn (FileBlob $b): array => [
                'blob' => $b->blob,
                'size' => $b->size,
                'created_at' => $b->created_at,
            ])
            ->all();

        return ['blobs' => $blobs];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();

        FileBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk): void {
                foreach ($blobs as $blob) {
                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete('files/'.$blob->blob);
                        $disk->delete('thumbs/'.$blob->blob.'.jpg');
                    }
                }

                FileBlob::query()
                    ->whereIn('blob', $blobs->modelKeys())
                    ->delete();
            }, 'blob');
    }
}
