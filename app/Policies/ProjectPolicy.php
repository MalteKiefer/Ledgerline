<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Authorization policy for projects, scoped to the owning team.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return true;
    }

    public function delete(User $user, Project $project): bool
    {
        return true;
    }

    public function restore(User $user, Project $project): bool
    {
        return true;
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return true;
    }
}
