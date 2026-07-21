<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\User;
use App\Models\VaultStore;

/**
 * Per-user data contributor for the zero-knowledge store. The user's entire
 * workspace (notes, and any other module folded into the manifest) lives as a
 * single sealed blob in vault_store, one row per user. The server only ever
 * holds ciphertext, so the export is the ciphertext itself — decryptable solely
 * with the user's own passphrase. That is the correct, complete ZK export.
 */
final class StoreData implements UserDataContributor
{
    public function key(): string
    {
        return 'store';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'ciphertext' => VaultStore::query()->where('user_id', $user->id)->value('ciphertext'),
        ];
    }

    public function purge(User $user): void
    {
        VaultStore::query()->where('user_id', $user->id)->delete();
    }
}
