<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_settings_page_loads(): void
    {
        $this->signIn();
        $this->get(route('settings.calendar.edit'))->assertOk()->assertSee(__('settings.calendar_heading'));
    }

    public function test_it_saves_valid_calendar_settings(): void
    {
        $user = $this->signIn();

        $this->put(route('settings.calendar.update'), [
            'calendar_week_start' => 'sunday',
            'calendar_week_numbers' => '1',
            'calendar_default_event_minutes' => 30,
        ])->assertRedirect(route('settings.calendar.edit'))->assertSessionHas('status');

        $settings = UserSetting::for($user->id);
        $this->assertSame('sunday', $settings->calendar_week_start);
        $this->assertTrue($settings->calendar_week_numbers);
        $this->assertSame(30, $settings->calendar_default_event_minutes);
    }

    public function test_an_unchecked_week_numbers_box_saves_as_false(): void
    {
        $user = $this->signIn();
        UserSetting::for($user->id)->update(['calendar_week_numbers' => true]);

        $this->put(route('settings.calendar.update'), [
            'calendar_week_start' => 'monday',
            'calendar_default_event_minutes' => 60,
        ])->assertRedirect(route('settings.calendar.edit'));

        $this->assertFalse(UserSetting::for($user->id)->calendar_week_numbers);
    }

    public function test_calendar_settings_are_per_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->put(route('settings.calendar.update'), [
            'calendar_week_start' => 'sunday', 'calendar_default_event_minutes' => 45, 'calendar_week_numbers' => '1',
        ])->assertRedirect(route('settings.calendar.edit'));

        // Bob is unaffected — his settings are his own defaults.
        $this->assertSame('sunday', UserSetting::for($alice->id)->calendar_week_start);
        $this->assertSame('monday', UserSetting::for($bob->id)->calendar_week_start);
        $this->assertSame(60, UserSetting::for($bob->id)->calendar_default_event_minutes);
    }

    public function test_it_rejects_an_invalid_week_start_and_duration(): void
    {
        $this->signIn();

        $this->put(route('settings.calendar.update'), [
            'calendar_week_start' => 'friday',
            'calendar_default_event_minutes' => 4000,
        ])->assertSessionHasErrors(['calendar_week_start', 'calendar_default_event_minutes']);
    }
}
