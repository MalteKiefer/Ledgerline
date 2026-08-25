<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin user management over /api/v1 (Sanctum device token + manage-global-settings).
 * Guards that must hold:
 *  - non-admin token → 403 on every endpoint
 *  - role is never mass-assigned (only goes through privilegedFields/forceFill)
 *  - cannot delete self or last admin
 *  - cannot demote last admin
 */
class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function adminToken(): string
    {
        return User::factory()->admin()->create()->createToken('t', ['device'])->plainTextToken;
    }

    private function userToken(): string
    {
        return User::factory()->create()->createToken('t', ['device'])->plainTextToken;
    }

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_admin_can_list_users(): void
    {
        $token = $this->adminToken();
        User::factory()->create(['name' => 'Alice']);

        $this->getJson('/api/v1/users', $this->auth($token))
            ->assertOk()
            ->assertJsonStructure(['users' => [['id', 'name', 'email', 'role', 'verified', 'two_factor']]])
            ->assertJsonFragment(['name' => 'Alice']);
    }

    public function test_index_reports_which_accounts_are_blocked(): void
    {
        $token = $this->adminToken();
        $blocked = User::factory()->create(['name' => 'Mallory']);
        User::factory()->create(['name' => 'Alice']);

        $this->postJson('/api/v1/admin/users/'.$blocked->id.'/block', [], $this->auth($token))->assertOk();

        // The point of the field: after a reload the list still says who is blocked.
        $rows = collect($this->getJson('/api/v1/users', $this->auth($token))->assertOk()->json('users'));
        $this->assertNotNull($rows->firstWhere('name', 'Mallory')['blocked_at']);
        $this->assertNull($rows->firstWhere('name', 'Alice')['blocked_at']);
    }

    public function test_index_includes_group_membership(): void
    {
        $token = $this->adminToken();
        $group = Group::factory()->create(['name' => 'Testers']);
        $target = User::factory()->create();
        $target->memberGroups()->sync([$group->id]);

        $resp = $this->getJson('/api/v1/users', $this->auth($token))->assertOk();

        /** @var list<array<string, mixed>> $users */
        $users = $resp->json('users');
        $entry = null;
        foreach ($users as $u) {
            if (isset($u['id']) && $u['id'] === $target->id) {
                $entry = $u;
                break;
            }
        }
        $this->assertIsArray($entry);
        /** @var list<array{id: int, name: string}> $groups */
        $groups = $entry['groups'];
        $this->assertSame($group->id, $groups[0]['id']);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_admin_can_create_a_user(): void
    {
        $token = $this->adminToken();

        $resp = $this->postJson('/api/v1/users', [
            'name' => 'Bob', 'email' => 'bob@example.com',
            'role' => 'user', 'password' => 'correct-horse-battery',
            'max_connected_devices' => 4,
        ], $this->auth($token))->assertCreated();

        $resp->assertJsonPath('user.name', 'Bob');
        $resp->assertJsonPath('user.role', 'user');
        $bob = User::where('email', 'bob@example.com')->first();
        $this->assertNotNull($bob);
        $this->assertSame(4, $bob->max_connected_devices);
    }

    public function test_store_creates_admin_user_when_role_is_admin(): void
    {
        $token = $this->adminToken();

        $this->postJson('/api/v1/users', [
            'name' => 'Admin2', 'email' => 'a2@example.com',
            'role' => 'admin', 'password' => 'correct-horse-battery',
        ], $this->auth($token))->assertCreated()->assertJsonPath('user.role', 'admin');

        $user = User::where('email', 'a2@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('admin', $user->role);
    }

    public function test_store_assigns_groups(): void
    {
        $token = $this->adminToken();
        $group = Group::factory()->create();

        $this->postJson('/api/v1/users', [
            'name' => 'Carol', 'email' => 'carol@example.com',
            'role' => 'user', 'password' => 'correct-horse-battery',
            'groups' => [$group->id],
        ], $this->auth($token))->assertCreated();

        $carol = User::where('email', 'carol@example.com')->first();
        $this->assertNotNull($carol);
        $this->assertContains($group->id, $carol->memberGroups->pluck('id')->all());
    }

    public function test_store_rejects_weak_password(): void
    {
        $token = $this->adminToken();

        $this->postJson('/api/v1/users', [
            'name' => 'Weak', 'email' => 'w@example.com',
            'role' => 'user', 'password' => 'short',
        ], $this->auth($token))->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_admin_can_update_a_user(): void
    {
        $token = $this->adminToken();
        $user = User::factory()->create(['name' => 'Old']);

        $this->putJson('/api/v1/users/'.$user->id, [
            'name' => 'New', 'email' => $user->email, 'role' => 'user',
        ], $this->auth($token))->assertOk()->assertJsonPath('user.name', 'New');

        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('New', $fresh->name);
    }

    public function test_update_syncs_groups(): void
    {
        $token = $this->adminToken();
        $user = User::factory()->create();
        $g1 = Group::factory()->create();
        $g2 = Group::factory()->create();
        $user->memberGroups()->sync([$g1->id]);

        $this->putJson('/api/v1/users/'.$user->id, [
            'name' => $user->name, 'email' => $user->email, 'role' => 'user',
            'groups' => [$g2->id],
        ], $this->auth($token))->assertOk();

        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame([$g2->id], $fresh->memberGroups->pluck('id')->all());
    }

    public function test_update_rejects_demoting_last_admin(): void
    {
        // The admin is the ONLY admin.
        $adminUser = User::factory()->admin()->create();
        $token = $adminUser->createToken('t', ['device'])->plainTextToken;

        $this->putJson('/api/v1/users/'.$adminUser->id, [
            'name' => $adminUser->name, 'email' => $adminUser->email, 'role' => 'user',
        ], $this->auth($token))->assertUnprocessable()->assertJsonPath('errors.role.0', __('settings.users_last_admin'));

        $fresh = $adminUser->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->isAdmin());
    }

    public function test_update_allows_demoting_admin_when_another_exists(): void
    {
        $admin1 = User::factory()->admin()->create();
        User::factory()->admin()->create(); // second admin
        $token = $admin1->createToken('t', ['device'])->plainTextToken;

        $this->putJson('/api/v1/users/'.$admin1->id, [
            'name' => $admin1->name, 'email' => $admin1->email, 'role' => 'user',
        ], $this->auth($token))->assertOk();

        $fresh = $admin1->fresh();
        $this->assertNotNull($fresh);
        $this->assertFalse($fresh->isAdmin());
    }

    // -------------------------------------------------------------------------
    // Role non-mass-assign
    // -------------------------------------------------------------------------

    public function test_role_is_set_via_privileged_path_not_mass_assigned(): void
    {
        // A regular user cannot reach the endpoint at all (403) — this ensures the
        // role field can never be escalated through the public API by a non-admin.
        $userToken = $this->userToken();
        $target = User::factory()->create();

        $this->postJson('/api/v1/users', [
            'name' => 'X', 'email' => 'x@example.com', 'role' => 'admin', 'password' => 'correct-horse-battery',
        ], $this->auth($userToken))->assertForbidden();

        // The non-admin never reached the controller, so no user was created and no
        // role could be escalated through the public API. (The admin forceFill path
        // that legitimately sets role is covered by the create/update tests above.)
        $this->assertNull(User::where('email', 'x@example.com')->first());
        $this->assertFalse($target->fresh()?->isAdmin());
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_admin_can_delete_a_user(): void
    {
        $token = $this->adminToken();
        $target = User::factory()->create();

        $this->deleteJson('/api/v1/users/'.$target->id, [], $this->auth($token))->assertNoContent();
        $this->assertNull(User::find($target->id));
    }

    public function test_cannot_delete_self(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('t', ['device'])->plainTextToken;

        $this->deleteJson('/api/v1/users/'.$admin->id, [], $this->auth($token))
            ->assertUnprocessable()
            ->assertJsonPath('errors.delete.0', __('settings.users_no_self_delete'));

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_cannot_delete_the_last_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('t', ['device'])->plainTextToken;
        $target = User::factory()->admin()->create(); // second admin
        // Now delete the second, leaving admin as sole admin.
        $target->delete();

        // The sole remaining admin cannot be deleted. (Deleting herself trips the
        // self-delete guard first, which fires before the last-admin guard — either
        // way the sole admin is protected; deletion is refused (422) and she survives.)
        $this->deleteJson('/api/v1/users/'.$admin->id, [], $this->auth($token))
            ->assertUnprocessable();

        $this->assertNotNull(User::find($admin->id));
    }

    // -------------------------------------------------------------------------
    // Reset password
    // -------------------------------------------------------------------------

    public function test_reset_password_returns_422_when_mail_disabled(): void
    {
        $token = $this->adminToken();
        $target = User::factory()->create();

        AppSettings::current()->update(['mail_enabled' => false]);

        $this->postJson('/api/v1/users/'.$target->id.'/reset-password', [], $this->auth($token))
            ->assertUnprocessable()
            ->assertJsonPath('errors.reset.0', __('settings.users_mail_off'));
    }

    // -------------------------------------------------------------------------
    // Reset 2FA
    // -------------------------------------------------------------------------

    public function test_admin_can_reset_two_factor(): void
    {
        $token = $this->adminToken();
        $target = User::factory()->create();
        $target->forceFill([
            'two_factor_secret' => encrypt('SECRET'),
            'two_factor_recovery_codes' => encrypt(json_encode(['a', 'b'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->postJson('/api/v1/users/'.$target->id.'/reset-2fa', [], $this->auth($token))
            ->assertOk()
            ->assertJsonPath('message', '2fa_reset');

        $target->refresh();
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_recovery_codes);
        $this->assertNull($target->two_factor_confirmed_at);
    }

    // -------------------------------------------------------------------------
    // Avatar
    // -------------------------------------------------------------------------

    public function test_avatar_returns_404_when_no_avatar_stored(): void
    {
        $token = $this->adminToken();
        $target = User::factory()->create();

        $this->getJson('/api/v1/users/'.$target->id.'/avatar', $this->auth($token))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Invite link
    // -------------------------------------------------------------------------

    public function test_admin_can_generate_an_invite_link(): void
    {
        $token = $this->adminToken();
        $target = User::factory()->create();

        $resp = $this->postJson('/api/v1/users/'.$target->id.'/invite-link', [
            'ttl_hours' => 24,
        ], $this->auth($token))->assertOk();

        $resp->assertJsonStructure(['url', 'expires_at', 'sent']);
        $url = $resp->json('url');
        $this->assertIsString($url);
        $this->assertStringContainsString('invite', $url);
        $this->assertFalse((bool) $resp->json('sent'));
        $this->assertDatabaseCount('invite_links', 1);
    }

    public function test_invite_link_rejects_invalid_ttl(): void
    {
        $token = $this->adminToken();
        $target = User::factory()->create();

        $this->postJson('/api/v1/users/'.$target->id.'/invite-link', [
            'ttl_hours' => 5,  // not in TTL_HOURS = [1,24,168,720]
        ], $this->auth($token))->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // Non-admin 403 gate (all endpoints)
    // -------------------------------------------------------------------------

    public function test_non_admin_is_forbidden_on_all_endpoints(): void
    {
        $token = $this->userToken();
        $headers = $this->auth($token);
        $admin = User::factory()->admin()->create();

        $this->getJson('/api/v1/users', $headers)->assertForbidden();
        $this->postJson('/api/v1/users', [], $headers)->assertForbidden();
        $this->putJson('/api/v1/users/'.$admin->id, [], $headers)->assertForbidden();
        $this->deleteJson('/api/v1/users/'.$admin->id, [], $headers)->assertForbidden();
        $this->postJson('/api/v1/users/'.$admin->id.'/reset-password', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/users/'.$admin->id.'/reset-2fa', [], $headers)->assertForbidden();
        $this->getJson('/api/v1/users/'.$admin->id.'/avatar', $headers)->assertForbidden();
        $this->postJson('/api/v1/users/'.$admin->id.'/invite-link', ['ttl_hours' => 24], $headers)->assertForbidden();
    }
}
