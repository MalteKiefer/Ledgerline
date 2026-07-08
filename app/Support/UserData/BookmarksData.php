<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\User;

/**
 * Bookmarks module contribution to per-user GDPR export and account erasure.
 * Tags are a JSON column on each bookmark, so there is no separate tag table
 * to carry.
 */
class BookmarksData implements UserDataContributor
{
    public function key(): string
    {
        return 'bookmarks';
    }

    public function export(User $user): array
    {
        $folders = BookmarkFolder::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get(['id', 'name', 'created_at', 'updated_at'])
            ->toArray();

        $bookmarks = Bookmark::withoutGlobalScopes()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->orderBy('id')
            // Zero-knowledge: url/title/description/tags are null; the sealed blob
            // is exported as ciphertext (decryptable only with the vault key).
            ->get([
                'id',
                'bookmark_folder_id',
                'enc_bookmark',
                'is_encrypted',
                'favorite',
                'created_at',
                'updated_at',
                'deleted_at',
            ])
            ->toArray();

        return [
            'folders' => $folders,
            'bookmarks' => $bookmarks,
        ];
    }

    public function purge(User $user): void
    {
        Bookmark::withoutGlobalScopes()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->forceDelete();

        BookmarkFolder::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->delete();
    }
}
