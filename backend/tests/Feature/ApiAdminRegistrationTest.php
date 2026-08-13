<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin GET/PUT /api/v1/admin/registration — read + set the workspace
 * self-registration toggle. Gated by the admin role on top of the device token.
 */
class ApiAdminRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/registration')->assertUnauthorized();
        $this->putJson('/api/v1/admin/registration', ['allow_registration' => true])->assertUnauthorized();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create(); // role 'user'

        $this->getJson('/api/v1/admin/registration', $this->bearer($user))->assertForbidden();
        $this->putJson('/api/v1/admin/registration', ['allow_registration' => true], $this->bearer($user))->assertForbidden();
    }

    public function test_admin_reads_and_toggles_registration(): void
    {
        AppSettings::current()->update(['allow_registration' => false]);
        $admin = $this->admin();

        $this->getJson('/api/v1/admin/registration', $this->bearer($admin))
            ->assertOk()->assertJson(['allow_registration' => false]);

        $this->putJson('/api/v1/admin/registration', ['allow_registration' => true], $this->bearer($admin))
            ->assertOk()->assertJson(['allow_registration' => true]);

        $this->assertTrue((bool) AppSettings::current()->fresh()?->allow_registration);
    }

    public function test_admin_toggle_validates_the_flag(): void
    {
        $this->putJson('/api/v1/admin/registration', ['allow_registration' => 'maybe'], $this->bearer($this->admin()))
            ->assertStatus(422);
    }
}
