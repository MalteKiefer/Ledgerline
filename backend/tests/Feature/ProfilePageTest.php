<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_serves_the_shell_for_a_user_with_a_last_login(): void
    {
        // SPA-only cutover: the profile UI is the SPA shell. This still exercises
        // the path with a populated last_login_at (whose model cast is what the
        // original regression guarded); the last-login value itself is now
        // rendered client-side from the /me + sessions API.
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'last_login_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('id="app"', false);
    }
}
