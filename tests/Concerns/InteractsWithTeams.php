<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;

/**
 * Authentication helpers for feature tests. All authenticated users share a
 * single workspace, so signing in is just creating and acting as a user.
 */
trait InteractsWithTeams
{
    protected function signIn(?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->actingAs($user);

        return $user;
    }
}
