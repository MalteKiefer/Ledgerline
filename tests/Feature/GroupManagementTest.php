<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_manage_groups(): void
    {
        $this->actingAs(User::factory()->create())->get(route('settings.groups'))->assertForbidden();
    }

    public function test_admin_can_create_a_group(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('settings.groups.store'), [
            'name' => 'Family', 'files_quota_mb' => 5000, 'gallery_quota_mb' => 3000,
            'max_connected_devices' => 4, 'shareable' => '1',
        ])->assertRedirect();

        $group = Group::where('name', 'Family')->first();
        $this->assertNotNull($group);
        $this->assertSame(5000, $group->files_quota_mb);
        $this->assertTrue($group->shareable);
    }

    public function test_a_blank_limit_clears_that_dimension(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = Group::factory()->create(['files_quota_mb' => 5000]);

        $this->put(route('settings.groups.update', $group), ['name' => $group->name, 'files_quota_mb' => ''])
            ->assertRedirect();

        $this->assertNull($group->fresh()->files_quota_mb);
    }

    public function test_admin_can_delete_a_group(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = Group::factory()->create();

        $this->delete(route('settings.groups.destroy', $group))->assertRedirect();
        $this->assertNull(Group::find($group->id));
    }

    public function test_admin_can_assign_a_user_to_groups_via_the_user_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = Group::factory()->create();
        $target = User::factory()->create();

        $this->put(route('settings.users.update', $target), [
            'name' => $target->name, 'email' => $target->email, 'role' => 'user', 'groups' => [$group->id],
        ])->assertRedirect();

        $this->assertTrue($target->fresh()->memberGroups->contains($group->id));
    }

    public function test_admin_can_assign_members_from_the_group_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = Group::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->put(route('settings.groups.update', $group), [
            'name' => $group->name, 'members' => [$a->id, $b->id],
        ])->assertRedirect();

        $ids = $group->fresh()->members->pluck('id')->all();
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);

        // Re-saving with a smaller set removes the dropped member.
        $this->put(route('settings.groups.update', $group), ['name' => $group->name, 'members' => [$a->id]])->assertRedirect();
        $this->assertSame([$a->id], $group->fresh()->members->pluck('id')->all());
    }

    public function test_the_most_generous_group_limit_applies(): void
    {
        config(['files.quota_mb' => 100]);
        $user = User::factory()->create();
        $small = Group::factory()->create(['files_quota_mb' => 500]);
        $big = Group::factory()->create(['files_quota_mb' => 2000]);
        $user->memberGroups()->attach([$small->id, $big->id]);

        // Most generous group limit (2000) beats the workspace default (100).
        $this->assertSame(2000, $user->fresh()->effectiveFilesQuotaMb());
    }

    public function test_a_per_user_override_beats_the_group_limit(): void
    {
        config(['files.quota_mb' => 100]);
        $user = User::factory()->create(['files_quota_mb' => 300]);
        $group = Group::factory()->create(['files_quota_mb' => 2000]);
        $user->memberGroups()->attach($group->id);

        $this->assertSame(300, $user->fresh()->effectiveFilesQuotaMb());
    }

    public function test_the_group_device_cap_applies_when_no_override(): void
    {
        config(['devices.max' => 3]);
        $user = User::factory()->create();
        $group = Group::factory()->create(['max_connected_devices' => 8]);
        $user->memberGroups()->attach($group->id);

        $this->assertSame(8, $user->fresh()->effectiveMaxDevices());
    }
}
