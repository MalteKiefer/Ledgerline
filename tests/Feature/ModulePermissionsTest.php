<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ModulePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_grants_all_modules(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->assertSame(array_keys(config('modules.list')), $user->allowedModules());
        $this->assertTrue($user->canModule('finance'));
    }

    public function test_admin_always_gets_all(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'modules' => ['notes']]);
        $this->assertTrue($admin->canModule('finance'));
    }

    public function test_per_user_allow_list_wins(): void
    {
        // allowedModules() intersects the stored list with the registered modules,
        // so a list that omits finance (or names only unknown modules) yields none.
        $user = User::factory()->create(['role' => 'user', 'modules' => ['reports']]);
        $this->assertSame([], $user->allowedModules());
        $this->assertFalse($user->canModule('finance'));
    }

    public function test_group_union_applies_and_a_null_group_grants_all(): void
    {
        $g1 = Group::create(['name' => 'A', 'modules' => ['reports']]); // unknown → filtered out
        $g2 = Group::create(['name' => 'B', 'modules' => ['finance']]);
        $user = User::factory()->create(['role' => 'user']);
        $user->memberGroups()->sync([$g1->id, $g2->id]);
        $this->assertSame(['finance'], $user->fresh()->allowedModules());

        // A group without a restriction (null) grants everything registered.
        $open = Group::create(['name' => 'C', 'modules' => null]);
        $user->memberGroups()->sync([$g1->id, $open->id]);
        $this->assertSame(array_keys(config('modules.list')), $user->fresh()->allowedModules());
    }

    public function test_web_route_is_blocked_for_a_disabled_module(): void
    {
        // A user whose module allow-list excludes finance is denied the page.
        $blocked = User::factory()->create(['role' => 'user', 'modules' => ['reports']]);
        $this->actingAs($blocked)->get('/finance')->assertForbidden();

        // A user who has finance enabled can open it.
        $allowed = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $this->actingAs($allowed)->get('/finance')->assertOk();
    }

    public function test_me_exposes_allowed_modules(): void
    {
        $user = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);
        $token = $user->createToken('t', ['device'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.modules', ['finance']);
    }
}
