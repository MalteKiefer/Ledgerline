<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

/**
 * Authorization policy for branch offices.
 *
 * Every authenticated user of this internal ERP may manage all branches.
 * Gating each action here keeps a single place to tighten access later.
 */
class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Branch $branch): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Branch $branch): bool
    {
        return true;
    }

    public function delete(User $user, Branch $branch): bool
    {
        return true;
    }

    public function restore(User $user, Branch $branch): bool
    {
        return true;
    }

    public function forceDelete(User $user, Branch $branch): bool
    {
        return true;
    }
}
