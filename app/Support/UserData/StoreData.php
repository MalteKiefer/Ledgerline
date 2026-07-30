<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\ModuleStore;
use App\Models\User;

/**
 * Per-user data contributor for the zero-knowledge sealed stores. Store v3 splits
 * the workspace into one opaque sealed row per module (notes/todos/bookmarks/
 * contacts/invoices/passwords/health/sharing in module_stores). The server only
 * ever holds ciphertext, so the export is the ciphertext itself — decryptable
 * solely with the user's own vault key. Files are now plaintext-relational and
 * exported/erased by FilesData; gallery blobs by GalleryData.
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
        $modules = ModuleStore::query()
            ->where('user_id', $user->id)
            ->pluck('ciphertext', 'module')
            ->all();

        return [
            'modules' => $modules,
        ];
    }

    public function purge(User $user): void
    {
        ModuleStore::query()->where('user_id', $user->id)->delete();
    }
}
