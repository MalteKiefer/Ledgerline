<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\File;
use App\Models\User;

/**
 * Authorization policy for files.
 *
 * Isolation is guaranteed by the team global scope (a file from another team
 * cannot be resolved at all); within a team every member may manage files.
 */
class FilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, File $file): bool
    {
        return $user->belongsToTeam($file->team_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, File $file): bool
    {
        return $user->belongsToTeam($file->team_id);
    }
}
