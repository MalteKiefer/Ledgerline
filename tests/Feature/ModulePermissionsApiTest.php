<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The module:<key> gate must fire on the /api/v1 routes too — not only web.
 * A user whose account has a module disabled must get 403 over the api, while
 * an enabled module (and admins) pass. Complements ModulePermissionsTest which
 * only proves the web route + /me exposure.
 */
final class ModulePermissionsApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device', ['device'])->plainTextToken];
    }

    public function test_disabled_module_is_403_on_api_but_enabled_is_ok(): void
    {
        $user = User::factory()->create(['role' => 'user', 'modules' => ['notes']]);

        $this->getJson(route('api.finance.data'), $this->bearer($user))->assertForbidden();
        $this->getJson(route('api.health.entries'), $this->bearer($user))->assertForbidden();
        $this->getJson(route('api.explore.data'), $this->bearer($user))->assertForbidden();

        $this->getJson(route('api.notes.index'), $this->bearer($user))->assertOk();
    }

    public function test_admin_bypasses_the_api_module_gate(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'modules' => ['notes']]);
        $this->getJson(route('api.finance.data'), $this->bearer($admin))->assertOk();
    }

    public function test_a_write_to_a_disabled_module_is_403_on_api(): void
    {
        $user = User::factory()->create(['role' => 'user', 'modules' => ['notes']]);

        $this->postJson(route('api.finance.partners.store'), ['name' => 'x'], $this->bearer($user))
            ->assertForbidden();
    }
}
