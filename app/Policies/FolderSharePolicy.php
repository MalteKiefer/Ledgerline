<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FolderShare;
use App\Models\User;

/**
 * Access control for plaintext cross-user folder shares (pivot). Fail-closed:
 * before() returns null (no admin/owner super-bypass — every ability is decided
 * on its own merits). Callers translate a denial to 404 when they must hide the
 * share's existence (a non-member), and to 403 for a member who is authenticated
 * on the share but lacks the mutation role (a viewer trying to write).
 */
class FolderSharePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return null;
    }

    /** Owner-only administration: change roles, remove members, delete the share. */
    public function manage(User $user, FolderShare $share): bool
    {
        return $share->owner_id === $user->id;
    }

    /** Read/browse: the owner or any granted member (viewer or editor). */
    public function view(User $user, FolderShare $share): bool
    {
        if ($share->owner_id === $user->id) {
            return true;
        }

        return $share->members()->where('user_id', $user->id)->exists();
    }

    /** Mutate (upload / rename / delete) within the subtree: the owner or an editor member. */
    public function contribute(User $user, FolderShare $share): bool
    {
        if ($share->owner_id === $user->id) {
            return true;
        }

        return $share->members()
            ->where('user_id', $user->id)
            ->where('role', 'editor')
            ->exists();
    }
}
