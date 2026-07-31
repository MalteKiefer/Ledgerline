<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\PasswordBlob;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for the sharded passwords store (merge-safety spec §3b),
 * mirroring NotesData/FilesData. Export = ciphertext blob inventory (ids/sizes — no
 * plaintext); purge deletes the stored bytes + ledger rows (the passwords_store root
 * cascades on user delete). The old module_stores passwords row is handled by StoreData.
 */
final class PasswordsData implements UserDataContributor
{
    public function key(): string
    {
        return 'passwords_shards';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $blobs = PasswordBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->get(['blob', 'size', 'created_at'])
            ->map(fn (PasswordBlob $b): array => ['blob' => $b->blob, 'size' => $b->size, 'created_at' => $b->created_at])
            ->all();

        return ['blobs' => $blobs];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();

        PasswordBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk): void {
                foreach ($blobs as $blob) {
                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete('passwords/'.$blob->blob);
                    }
                }
                PasswordBlob::query()->whereIn('blob', $blobs->modelKeys())->delete();
            }, 'blob');
    }
}
