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
        $this->assertSame(['distance' => 'km', 'elevation' => 'm', 'weight' => 'kg', 'temp' => 'c', 'glucose' => 'mgdl', 'time_format' => '24h'], $prefs);
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
