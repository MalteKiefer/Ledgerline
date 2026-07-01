<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

/**
 * Authorization policy for branch offices, scoped to the owning team.
 */
class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->belongsToTeam($branch->team_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->belongsToTeam($branch->team_id);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->belongsToTeam($branch->team_id);
    }

    public function restore(User $user, Branch $branch): bool
    {
        return $user->belongsToTeam($branch->team_id);
    }

    public function forceDelete(User $user, Branch $branch): bool
    {
        return $user->belongsToTeam($branch->team_id);
    }
}
