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
        $user = User::factory()->create(['role' => 'user', 'modules' => ['notes', 'todos']]);
        $this->assertSame(['notes', 'todos'], $user->allowedModules());
        $this->assertFalse($user->canModule('finance'));
    }

    public function test_group_union_applies_and_a_null_group_grants_all(): void
    {
        $g1 = Group::create(['name' => 'A', 'modules' => ['notes']]);
        $g2 = Group::create(['name' => 'B', 'modules' => ['finance']]);
        $user = User::factory()->create(['role' => 'user']);
        $user->memberGroups()->sync([$g1->id, $g2->id]);
        $this->assertEqualsCanonicalizing(['notes', 'finance'], $user->fresh()->allowedModules());

        // A group without a restriction (null) grants everything.
        $open = Group::create(['name' => 'C', 'modules' => null]);
        $user->memberGroups()->sync([$g1->id, $open->id]);
        $this->assertSame(array_keys(config('modules.list')), $user->fresh()->allowedModules());
    }

    public function test_web_route_is_blocked_for_a_disabled_module(): void
    {
        $user = User::factory()->create(['role' => 'user', 'modules' => ['notes']]);
        $this->actingAs($user)->get('/finance')->assertForbidden();
        $this->actingAs($user)->get('/notes')->assertOk();
    }

    // (The generic /store/{module} gate test was dropped: after the pivot no
    // user-facing toggle module is served by the generic sealed store anymore —
    // only invoices + sharing remain, neither a toggle-keyed page module. The
    // EnsureModule gate is still covered by test_web_route_is_blocked_for_a_disabled_module.)

    public function test_me_exposes_allowed_modules(): void
    {
        $user = User::factory()->create(['role' => 'user', 'modules' => ['notes', 'health']]);
        $token = $user->createToken('t', ['device'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.modules', ['notes', 'health']);
    }
}
