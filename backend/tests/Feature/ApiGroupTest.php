<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin group management over /api/v1 (Sanctum device token + manage-global-settings).
 */
class ApiGroupTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('phone', ['device'])->plainTextToken;
    }

    private function admin(): string
    {
        return $this->token(User::factory()->admin()->create());
    }

    public function test_admin_can_list_groups(): void
    {
        Group::factory()->create(['name' => 'Family']);

        $this->getJson('/api/v1/groups', ['Authorization' => 'Bearer '.$this->admin()])
            ->assertOk()
            ->assertJsonStructure(['groups' => [['id', 'name', 'max_connected_devices', 'shareable', 'members']]])
            ->assertJsonPath('groups.0.name', 'Family');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $token = $this->token(User::factory()->create());
        $this->getJson('/api/v1/groups', ['Authorization' => 'Bearer '.$token])->assertForbidden();
        $this->postJson('/api/v1/groups', ['name' => 'X'], ['Authorization' => 'Bearer '.$token])->assertForbidden();
    }

    public function test_admin_can_create_a_group_with_members(): void
    {
        $token = $this->admin();
        $a = User::factory()->create();

        $res = $this->postJson('/api/v1/groups', [
            'name' => 'Team', 'max_connected_devices' => 6, 'shareable' => true, 'members' => [$a->id],
        ], ['Authorization' => 'Bearer '.$token])->assertCreated();

        $res->assertJsonPath('group.name', 'Team');
        $res->assertJsonPath('group.shareable', true);
        $res->assertJsonPath('group.members.0.id', $a->id);
        $this->assertSame(6, Group::where('name', 'Team')->first()->max_connected_devices);
    }

    public function test_admin_can_update_and_sync_members(): void
    {
        $token = $this->admin();
        $group = Group::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $group->members()->sync([$a->id]);

        $this->putJson('/api/v1/groups/'.$group->id, [
            'name' => $group->name, 'members' => [$b->id],
        ], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->assertSame([$b->id], $group->fresh()->members->pluck('id')->all());
    }

    public function test_admin_can_delete_a_group(): void
    {
        $token = $this->admin();
        $group = Group::factory()->create();

        $this->deleteJson('/api/v1/groups/'.$group->id, [], ['Authorization' => 'Bearer '.$token])->assertNoContent();
        $this->assertNull(Group::find($group->id));
    }

    public function test_a_duplicate_name_is_rejected(): void
    {
        $token = $this->admin();
        Group::factory()->create(['name' => 'Dup']);

        $this->postJson('/api/v1/groups', ['name' => 'Dup'], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422);
    }
}
