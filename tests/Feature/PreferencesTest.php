<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_metric_and_24h(): void
    {
        $user = $this->signIn();
        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertSame(['distance' => 'km', 'elevation' => 'm', 'weight' => 'kg', 'temp' => 'c', 'glucose' => 'mgdl', 'time_format' => '24h', 'mail_remote' => false, 'mail_scripts' => false, 'cal_week_numbers' => false, 'cal_week_start' => 'mon', 'cal_default_view' => 'month', 'cal_day_start' => 8, 'cal_day_end' => 17], $prefs);
    }

    public function test_mail_display_prefs_can_be_toggled(): void
    {
        $user = $this->signIn();

        $this->post(route('preferences.update'), ['mail_remote' => '1', 'mail_scripts' => '1'])->assertRedirect();

        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertTrue($prefs['mail_remote']);
        $this->assertTrue($prefs['mail_scripts']);
    }

    public function test_calendar_prefs_can_be_updated(): void
    {
        $user = $this->signIn();

        $this->post(route('preferences.update'), [
            'cal_week_numbers' => '1', 'cal_week_start' => 'sun',
            'cal_default_view' => 'week', 'cal_day_start' => '9', 'cal_day_end' => '18',
        ])->assertRedirect();

        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertTrue($prefs['cal_week_numbers']);
        $this->assertSame('sun', $prefs['cal_week_start']);
        $this->assertSame('week', $prefs['cal_default_view']);
        $this->assertSame(9, $prefs['cal_day_start']);
        $this->assertSame(18, $prefs['cal_day_end']);

        $this->post(route('preferences.update'), ['cal_default_view' => 'nope'])->assertSessionHasErrors('cal_default_view');
    }

    public function test_a_single_preference_can_be_updated(): void
    {
        $user = $this->signIn();

        $this->post(route('preferences.update'), ['distance' => 'mi', 'time_format' => '12h'])->assertRedirect();

        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertSame('mi', $prefs['distance']);
        $this->assertSame('12h', $prefs['time_format']);
        // Untouched fields keep their defaults.
        $this->assertSame('kg', $prefs['weight']);
    }

    public function test_invalid_value_is_rejected(): void
    {
        $this->signIn();
        $this->post(route('preferences.update'), ['distance' => 'lightyears'])->assertSessionHasErrors('distance');
    }

    public function test_prefs_are_injected_into_the_page(): void
    {
        $user = $this->signIn();
        UserSetting::for($user->id)->update(['unit_elevation' => 'ft']);
        $this->get(route('dashboard'))->assertOk()->assertSee('name="ll-prefs"', false)->assertSee('&quot;elevation&quot;:&quot;ft&quot;', false);
    }

    public function test_me_endpoint_carries_preferences(): void
    {
        $user = $this->signIn();
        UserSetting::for($user->id)->update(['unit_weight' => 'lb']);
        $token = $user->createToken('t', ['device'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('user.preferences.weight', 'lb');
    }
}
