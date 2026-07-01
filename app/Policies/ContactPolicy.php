<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

/**
 * Authorization policy for contact persons, scoped to the owning team.
 */
class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->belongsToTeam($contact->team_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->belongsToTeam($contact->team_id);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->belongsToTeam($contact->team_id);
    }

    public function restore(User $user, Contact $contact): bool
    {
        return $user->belongsToTeam($contact->team_id);
    }

    public function forceDelete(User $user, Contact $contact): bool
    {
        return $user->belongsToTeam($contact->team_id);
    }
}
