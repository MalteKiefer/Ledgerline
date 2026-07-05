<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_defaults_are_saved_per_user(): void
    {
        $this->signIn();

        $this->put(route('settings.reminders.update'), ['channels' => ['desktop', 'mail', 'bogus']])
            ->assertRedirect(route('settings.reminders.edit'));

        // Unknown channels dropped, kept in canonical order.
        $this->assertSame(['desktop', 'mail'], UserSetting::for(auth()->id())->reminder_channels);
    }

    public function test_reminder_page_is_personal_not_admin_gated(): void
    {
        config()->set('services.pocketid.admin_group', 'admins');
        $this->actingAs(User::factory()->create(['groups' => []]));

        $this->get(route('settings.reminders.edit'))->assertOk();
    }

    public function test_paperless_is_per_user_and_not_admin_gated(): void
    {
        config()->set('services.pocketid.admin_group', 'admins');
        $this->actingAs(User::factory()->create(['groups' => []]));

        $this->get(route('settings.paperless.edit'))->assertOk();
        $this->put(route('settings.paperless.update'), [
            'paperless_enabled' => '1',
            'paperless_url' => 'https://p.example.com',
            'paperless_token' => 'tok',
        ])->assertRedirect();

        $this->assertSame('https://p.example.com', UserSetting::for(auth()->id())->paperless_url);
    }
}
