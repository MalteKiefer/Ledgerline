<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SharedVault;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization policy for shared password-Tresore.
 *
 * Roles are enforced at the membership level. The `before()` hook returns null
 * so that no blanket bypass is granted — not even to admin/operator accounts.
 * Every ability is evaluated solely from active membership rows.
 */
class SharedVaultPolicy
{
    /**
     * Never grant or deny unconditionally — fall through to per-ability checks
     * for every user, including operators/admins.
     */
    public function before(User $user, string $ability): ?bool
    {
        return null;
    }

    /**
     * Any active member (viewer, editor, or manager) may view a vault.
     */
    public function view(User $user, SharedVault $vault): Response
    {
        return $this->roleAtLeast($user, $vault, ['viewer', 'editor', 'manager'])
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Editors and managers may update vault contents.
     */
    public function update(User $user, SharedVault $vault): Response
    {
        return $this->roleAtLeast($user, $vault, ['editor', 'manager'])
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Only managers may perform membership management actions.
     */
    public function manage(User $user, SharedVault $vault): Response
    {
        return $this->roleAtLeast($user, $vault, ['manager'])
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Return true when the user holds an active membership with one of the
     * given roles in the vault.
     *
     * @param  array<string>  $roles
     */
    private function roleAtLeast(User $user, SharedVault $vault, array $roles): bool
    {
        return $vault->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereIn('role', $roles)
            ->exists();
    }
}
