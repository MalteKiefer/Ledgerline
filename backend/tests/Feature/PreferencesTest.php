<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_24h_and_system_date_format(): void
    {
        $user = $this->signIn();
        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertSame([
            'time_format' => '24h', 'timezone' => null, 'date_format' => 'system',
            'notifications' => [],
        ], $prefs);
    }

    public function test_timezone_and_date_format_are_settable(): void
    {
        $user = $this->signIn();

        $this->post(route('preferences.update'), ['timezone' => 'Asia/Tokyo', 'date_format' => 'dmy_dot'])->assertRedirect();
        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertSame('Asia/Tokyo', $prefs['timezone']);
        $this->assertSame('dmy_dot', $prefs['date_format']);

        // A blank timezone clears the override (follow the browser).
        $this->post(route('preferences.update'), ['timezone' => ''])->assertRedirect();
        $this->assertNull(UserSetting::for($user->id)->displayPrefs()['timezone']);

        // A bogus zone is rejected.
        $this->post(route('preferences.update'), ['timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');
    }

    public function test_a_single_preference_can_be_updated(): void
    {
        $user = $this->signIn();

        $this->post(route('preferences.update'), ['time_format' => '12h'])->assertRedirect();

        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertSame('12h', $prefs['time_format']);
        // Untouched fields keep their defaults.
        $this->assertSame('system', $prefs['date_format']);
    }

    public function test_per_category_push_prefs_merge(): void
    {
        $user = $this->signIn();

        $this->post(route('preferences.update'), ['notifications' => ['task' => ['push' => false]]])->assertRedirect();
        $setting = UserSetting::for($user->id);
        $this->assertFalse($setting->pushEnabled('task'));
        $this->assertTrue($setting->pushEnabled('event')); // default for an unset category

        // Setting another category leaves the first untouched (merge, not replace).
        $this->post(route('preferences.update'), ['notifications' => ['event' => ['push' => false]]])->assertRedirect();
        $setting = UserSetting::for($user->id);
        $this->assertFalse($setting->pushEnabled('task'));
        $this->assertFalse($setting->pushEnabled('event'));
    }

    public function test_invalid_value_is_rejected(): void
    {
        $this->signIn();
        $this->post(route('preferences.update'), ['time_format' => 'lightyears'])->assertSessionHasErrors('time_format');
    }

    public function test_me_endpoint_carries_preferences(): void
    {
        $user = $this->signIn();
        UserSetting::for($user->id)->update(['time_format' => '12h']);
        $token = $user->createToken('t', ['device'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('user.preferences.time_format', '12h');
    }

    /**
     * Every preference the endpoint accepts is actually stored. A 200 with the
     * preferences in it is not evidence that a column moved — only reading it
     * back is. A new preference without a persist branch fails here.
     */
    public function test_every_settable_preference_survives_a_write(): void
    {
        $user = $this->signIn();

        /** @var list<array{payload: array<string, mixed>, key: string, stored: mixed}> $cases */
        $cases = [
            ['payload' => ['time_format' => '12h'], 'key' => 'time_format', 'stored' => '12h'],
            ['payload' => ['timezone' => 'Europe/Berlin'], 'key' => 'timezone', 'stored' => 'Europe/Berlin'],
            ['payload' => ['date_format' => 'ymd'], 'key' => 'date_format', 'stored' => 'ymd'],
        ];

        foreach ($cases as $case) {
            $this->post(route('preferences.update'), $case['payload'])->assertRedirect();

            $this->assertSame(
                $case['stored'],
                UserSetting::for($user->id)->displayPrefs()[$case['key']],
                "Preference '{$case['key']}' was accepted but not stored.",
            );
        }
    }

    public function test_api_twin_accepts_the_same_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->postJson(route('api.preferences.update'), ['time_format' => '12h', 'date_format' => 'dmy'])
            ->assertOk();

        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertSame('12h', $prefs['time_format']);
        $this->assertSame('dmy', $prefs['date_format']);
    }
}
