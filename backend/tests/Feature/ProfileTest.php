<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SPA-only cutover: the profile UI lives entirely in the Vue SPA, so every
 * /profile* path now returns the SPA shell (200) and the SPA router + the gated
 * /api enforce auth and render the account details client-side. These tests
 * assert the shell is served; the account data itself is covered by the /me API.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_get_the_spa_shell_and_the_router_guards_client_side(): void
    {
        // The page shell is public; the SPA redirects to /login when /api/v1/me
        // is 401. Server-side auth now lives on the API, not the page route.
        $this->get('/profile')->assertOk()->assertSee('id="app"', false);
    }

    public function test_profile_paths_serve_the_spa_shell(): void
    {
        $user = User::factory()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('id="app"', false);
        $this->actingAs($user)->get('/profile/account')->assertOk()->assertSee('id="app"', false);
    }
}
