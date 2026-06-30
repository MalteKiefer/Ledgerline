<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Team;
use App\Models\User;

/**
 * Test helpers for the team-based ownership model.
 *
 * signIn() creates a user in a team and activates it, so any owned records
 * created afterwards inherit that team via the BelongsToTeam creating hook.
 */
trait InteractsWithTeams
{
    protected ?Team $team = null;

    /**
     * Sign in as a member of a team (created if not given) and activate it.
     */
    protected function signIn(?Team $team = null): User
    {
        $this->team = $team ?? Team::factory()->create();

        $user = User::factory()->create();
        $user->teams()->attach($this->team);
        session(['active_team_id' => $this->team->id]);
        $this->actingAs($user);

        return $user;
    }

    /**
     * The current test team (created lazily).
     */
    protected function team(): Team
    {
        return $this->team ??= Team::factory()->create();
    }
}
