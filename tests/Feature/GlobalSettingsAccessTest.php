<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_open_infra_settings(): void
    {
        $this->actingAs(User::factory()->create()); // role 'user'

        $this->get(route('settings.system.edit'))->assertForbidden();
        // Personal settings stay open.
        $this->get(route('settings.paperless.edit'))->assertOk();
    }

    public function test_admin_can_open_infra_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('settings.system.edit'))->assertOk();
    }

    public function test_settings_index_redirects_non_admins_to_profile(): void
    {
        $this->actingAs(User::factory()->create());

        // Settings is admin-only now; personal preferences live under the profile.
        $this->get(route('settings'))->assertRedirect(route('profile'));
    }

    public function test_settings_index_shows_only_admin_sections_for_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('settings'))->assertOk()
            ->assertSee(__('settings.admin_heading'))
            ->assertSee(__('settings.backup_section'))
            ->assertDontSee(__('settings.personal_heading'));
    }
}
