<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\SharedFolderBlob;
use App\Models\SharedVault;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for shared folders/vaults the user OWNS under
 * zero-knowledge. The vault name, folder tree and item data all live sealed in
 * the SharedVaultStore manifest (ciphertext under VK_vault) and the only other
 * server-side state is the opaque content blobs + their ledger
 * (shared_folder_blobs, owner_id = folder owner). The export is therefore the
 * ciphertext blob inventory (ids/sizes — NO plaintext, NO keys); purge deletes
 * the owner's stored shared-folder bytes synchronously, then lets the vault +
 * members + store + ledger rows cascade.
 *
 * Without this contributor a purge relied on the shared_vaults FK cascade, which
 * drops the ledger rows but leaves the ciphertext bytes at shared-folders/{blob}
 * on disk until the daily shared-folders:sweep-orphans sweep reclaims them —
 * mirroring the gap FilesData/GalleryData already close for personal blobs. Only
 * vaults the user OWNS are erased; vaults where the user is merely a member are
 * left intact (their owner still needs them).
 */
final class SharedData implements UserDataContributor
{
    public function key(): string
    {
        return 'shared';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $vaults = SharedVault::query()
            ->where('owner_id', $user->getKey())
            ->withCount('members')
            ->orderBy('id')
            ->get(['id', 'kind'])
            ->map(fn (SharedVault $v): array => [
                'id' => $v->id,
                'kind' => $v->kind,
                'member_count' => (int) ($v->members_count ?? 0),
            ])
            ->all();

        $blobs = SharedFolderBlob::query()
            ->where('owner_id', $user->getKey())
            ->orderBy('blob')
            ->get(['blob', 'vault_id', 'size', 'created_at'])
            ->map(fn (SharedFolderBlob $b): array => [
                'blob' => $b->blob,
                'vault_id' => $b->vault_id,
                'size' => $b->size,
                'created_at' => $b->created_at,
            ])
            ->all();

        return [
            'vaults' => $vaults,
            'blobs' => $blobs,
        ];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();

        // Delete the disk bytes synchronously (the FK cascade only reclaims rows).
        SharedFolderBlob::query()
            ->where('owner_id', $user->getKey())
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk): void {
                foreach ($blobs as $blob) {
                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete('shared-folders/'.$blob->blob);
                    }
                }

                SharedFolderBlob::query()
                    ->whereIn('blob', $blobs->modelKeys())
                    ->delete();
            }, 'blob');

        // Remove the owner's vaults; members + store + any remaining ledger rows
        // cascade via their FKs. Ordering is irrelevant — everything is owner-scoped.
        SharedVault::query()->where('owner_id', $user->getKey())->delete();
    }
}
