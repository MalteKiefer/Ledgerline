<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SPA-only cutover: the settings UI lives entirely in the Vue SPA, so the
 * /settings* page routes now return the public SPA shell (200) and the actual
 * admin authorization is enforced on the gated /api/v1/admin/* endpoints — not
 * the page route. These tests assert (a) the shell is served for the pages and
 * (b) the admin gate still fails closed on the API where it now lives.
 */
class GlobalSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    private function deviceToken(User $user): string
    {
        return $user->createToken('phone', ['device'])->plainTextToken;
    }

    public function test_non_admin_is_forbidden_from_the_admin_api(): void
    {
        $token = $this->deviceToken(User::factory()->create()); // role 'user'

        $this->getJson('/api/v1/admin/system', ['Authorization' => 'Bearer '.$token])
            ->assertForbidden();
    }

    public function test_admin_can_reach_the_admin_api(): void
    {
        $token = $this->deviceToken(User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/system', ['Authorization' => 'Bearer '.$token])
            ->assertOk();
    }

    public function test_settings_pages_serve_the_public_spa_shell(): void
    {
        // The page route carries no data and is intentionally public; the SPA
        // router guard + the gated API decide what a given user may actually see.
        $this->get('/settings')->assertOk()->assertSee('id="app"', false);
        $this->get('/settings/system')->assertOk()->assertSee('id="app"', false);

        // An authenticated non-admin also just gets the shell (no server-side 403
        // on the page; the API is the enforcement boundary).
        $this->actingAs(User::factory()->create());
        $this->get('/settings/system')->assertOk()->assertSee('id="app"', false);
    }
}
