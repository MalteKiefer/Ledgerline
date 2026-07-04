<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_open_infra_settings_when_group_configured(): void
    {
        config()->set('services.pocketid.admin_group', 'admins');
        $this->actingAs(User::factory()->create(['groups' => ['users']]));

        $this->get(route('settings.mail.edit'))->assertForbidden();
        $this->get(route('settings.gallery.edit'))->assertForbidden();
        // Personal settings stay open.
        $this->get(route('settings.calendar.edit'))->assertOk();
    }

    public function test_admin_group_member_can_open_infra_settings(): void
    {
        config()->set('services.pocketid.admin_group', 'admins');
        $this->actingAs(User::factory()->create(['groups' => ['staff', 'admins']]));

        $this->get(route('settings.mail.edit'))->assertOk();
        $this->get(route('settings.gallery.edit'))->assertOk();
    }

    public function test_everyone_may_when_no_admin_group_configured(): void
    {
        config()->set('services.pocketid.admin_group', null);
        $this->actingAs(User::factory()->create(['groups' => []]));

        $this->get(route('settings.mail.edit'))->assertOk();
    }

    public function test_settings_index_hides_infra_cards_for_non_admins(): void
    {
        config()->set('services.pocketid.admin_group', 'admins');
        $this->actingAs(User::factory()->create(['groups' => []]));

        // Assert on card descriptions (section words like "Mail" also appear in nav).
        $this->get(route('settings'))->assertOk()
            ->assertSee(__('settings.calendar_desc'))
            ->assertDontSee(__('settings.mail_desc'));
    }
}
