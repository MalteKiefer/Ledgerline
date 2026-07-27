<?php

declare(strict_types=1);

namespace Tests\Feature;

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
        $this->assertTrue(\App\Models\AppSettings::current()->allow_registration);
    }
}
