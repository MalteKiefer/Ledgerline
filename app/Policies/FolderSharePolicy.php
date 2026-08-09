<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FolderShare;
use App\Models\User;

/**
 * Access control for plaintext cross-user shares (pivot) — a folder subtree OR a
 * single file. Fail-closed: before() returns null (no admin/owner super-bypass —
 * every ability is decided on its own merits). Abilities are decided on the SHARE
 * (owner vs member/role); the per-target containment — a folder share authorizes
 * its whole subtree, a file share authorizes EXACTLY its one file — is enforced by
 * SharedWithMeController::resolveMemberFile (a mismatch → 404). Callers translate
 * a denial to 404 when they must hide the share's existence (a non-member), and to
 * 403 for a member authenticated on the share but lacking the mutation role (a
 * viewer trying to write, or any member trying to delete a lone shared file).
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
