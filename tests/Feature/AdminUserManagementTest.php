<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_user_management(): void
    {
        $this->actingAs(User::factory()->create())->get(route('settings.users'))->assertForbidden();
    }

    public function test_admin_can_create_a_user_with_role_and_limits(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('settings.users.store'), [
            'name' => 'Bob', 'email' => 'bob@example.com', 'role' => 'user',
            'password' => 'super-secret-123', 'files_quota_mb' => 500, 'max_connected_devices' => 2,
        ])->assertRedirect();

        $bob = User::where('email', 'bob@example.com')->first();
        $this->assertNotNull($bob);
        $this->assertSame('user', $bob->role);
        $this->assertSame(500, $bob->files_quota_mb);
        $this->assertSame(2, $bob->max_connected_devices);
        $this->assertTrue(str_starts_with((string) $bob->password, '$'));
    }

    public function test_role_is_never_taken_from_arbitrary_input_on_a_non_admin(): void
    {
        // A plain user cannot even reach the endpoint (gate) — privilege escalation blocked.
        $this->actingAs(User::factory()->create())
            ->post(route('settings.users.store'), ['name' => 'X', 'email' => 'x@e.com', 'role' => 'admin', 'password' => 'super-secret-123'])
            ->assertForbidden();
        $this->assertNull(User::where('email', 'x@e.com')->first());
    }

    public function test_cannot_delete_the_last_admin_or_self(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // last admin (also self)
        $this->delete(route('settings.users.destroy', $admin))->assertSessionHasErrors();
        $this->assertNotNull(User::find($admin->id));

        // a second admin exists → still cannot delete SELF here
        $other = User::factory()->admin()->create();
        $this->delete(route('settings.users.destroy', $admin))->assertSessionHasErrors('delete');
        $this->assertNotNull(User::find($admin->id));

        // but can delete the other admin
        $this->delete(route('settings.users.destroy', $other))->assertRedirect();
        $this->assertNull(User::find($other->id));
    }

    public function test_cannot_demote_the_last_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->put(route('settings.users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'user'])
            ->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_registration_toggle(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('settings.registration'), ['allow_registration' => '1'])->assertRedirect();
        $this->assertTrue(AppSettings::current()->allow_registration);
    }

    public function test_admin_can_reset_a_users_two_factor(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $target = User::factory()->create();
        $target->forceFill([
            'two_factor_secret' => encrypt('SECRET'),
            'two_factor_recovery_codes' => encrypt(json_encode(['a', 'b'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post(route('settings.users.reset2fa', $target))->assertRedirect();

        $target->refresh();
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_recovery_codes);
        $this->assertNull($target->two_factor_confirmed_at);
    }

    public function test_two_factor_reset_requires_admin(): void
    {
        $this->actingAs(User::factory()->create());
        $target = User::factory()->create();
        $this->post(route('settings.users.reset2fa', $target))->assertForbidden();
    }

    public function test_the_index_lists_users(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->create(['name' => 'Heavy User']);

        $this->get(route('settings.users'))->assertOk()->assertSee('Heavy User');
    }

    public function test_the_avatar_route_is_admin_only(): void
    {
        $target = User::factory()->create();
        $this->actingAs(User::factory()->create())->get(route('settings.users.avatar', $target))->assertForbidden();
        // No avatar stored → admin gets 404 (not a 403).
        $this->actingAs(User::factory()->admin()->create())->get(route('settings.users.avatar', $target))->assertNotFound();
    }
}
