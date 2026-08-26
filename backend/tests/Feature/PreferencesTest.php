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

    public function test_defaults_are_metric_and_24h(): void
    {
        $user = $this->signIn();
        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertSame([
            'distance' => 'km', 'elevation' => 'm', 'weight' => 'kg', 'temp' => 'c', 'glucose' => 'mgdl',
            'time_format' => '24h', 'timezone' => null, 'date_format' => 'system',
            'mail_load_remote' => false, 'notifications' => [], 'mail_columns' => null, 'mail_signature' => null,
            // Default is the setting that sends nothing: the address book only.
            'mail_avatars' => 'contacts',
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

        $this->post(route('preferences.update'), ['distance' => 'mi', 'time_format' => '12h'])->assertRedirect();

        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertSame('mi', $prefs['distance']);
        $this->assertSame('12h', $prefs['time_format']);
        // Untouched fields keep their defaults.
        $this->assertSame('kg', $prefs['weight']);
    }

    public function test_mail_signature_and_remote_default_are_settable(): void
    {
        $user = $this->signIn();

        $this->post(route('preferences.update'), [
            'mail_load_remote' => true, 'mail_signature' => "Kind regards\nMalte",
        ])->assertRedirect();

        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertTrue($prefs['mail_load_remote']);
        $this->assertSame("Kind regards\nMalte", $prefs['mail_signature']);

        // A blank signature clears it back to null.
        $this->post(route('preferences.update'), ['mail_signature' => '   '])->assertRedirect();
        $this->assertNull(UserSetting::for($user->id)->displayPrefs()['mail_signature']);
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
        $this->post(route('preferences.update'), ['distance' => 'lightyears'])->assertSessionHasErrors('distance');
    }

    // SPA-only cutover: preferences are no longer injected as an <meta ll-prefs>
    // tag in a server-rendered page — the SPA reads them from GET /api/v1/me
    // (asserted by test_me_endpoint_carries_preferences below).

    public function test_me_endpoint_carries_preferences(): void
    {
        $user = $this->signIn();
        UserSetting::for($user->id)->update(['unit_weight' => 'lb']);
        $token = $user->createToken('t', ['device'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('user.preferences.weight', 'lb');
    }

    public function test_the_unit_selects_use_the_documented_field_names(): void
    {
        // The SPA once posted unit_distance while the API takes distance, so the
        // five unit selects neither showed the stored value nor saved a new one.
        // This pins the documented names on both halves.
        $user = User::factory()->create();
        $this->actingAs($user)
            ->postJson(route('api.preferences.update'), [
                'distance' => 'mi', 'elevation' => 'ft', 'weight' => 'lb', 'temp' => 'f', 'glucose' => 'mmoll',
            ])
            ->assertOk();

        $prefs = UserSetting::for($user->id)->displayPrefs();
        $this->assertSame('mi', $prefs['distance']);
        $this->assertSame('ft', $prefs['elevation']);
        $this->assertSame('lb', $prefs['weight']);
        $this->assertSame('f', $prefs['temp']);
        $this->assertSame('mmoll', $prefs['glucose']);
    }

    /**
     * Every preference the endpoint accepts is actually stored.
     *
     * `mail_avatars` was validated, echoed back in the response and never
     * written: its branch assigned to a variable nothing persists, so the client
     * was told "saved" and got the old value back on the next load. A 200 with
     * the preferences in it is not evidence that a column moved — only reading
     * it back is. A new preference without a persist branch fails here.
     */
    public function test_every_settable_preference_survives_a_write(): void
    {
        $user = $this->signIn();

        /** @var list<array{payload: array<string, mixed>, key: string, stored: mixed}> $cases */
        $cases = [
            ['payload' => ['distance' => 'mi'], 'key' => 'distance', 'stored' => 'mi'],
            ['payload' => ['elevation' => 'ft'], 'key' => 'elevation', 'stored' => 'ft'],
            ['payload' => ['weight' => 'lb'], 'key' => 'weight', 'stored' => 'lb'],
            ['payload' => ['temp' => 'f'], 'key' => 'temp', 'stored' => 'f'],
            ['payload' => ['glucose' => 'mmoll'], 'key' => 'glucose', 'stored' => 'mmoll'],
            ['payload' => ['time_format' => '12h'], 'key' => 'time_format', 'stored' => '12h'],
            ['payload' => ['timezone' => 'Europe/Berlin'], 'key' => 'timezone', 'stored' => 'Europe/Berlin'],
            ['payload' => ['date_format' => 'ymd'], 'key' => 'date_format', 'stored' => 'ymd'],
            ['payload' => ['mail_load_remote' => true], 'key' => 'mail_load_remote', 'stored' => true],
            ['payload' => ['mail_signature' => 'Gruss'], 'key' => 'mail_signature', 'stored' => 'Gruss'],
            ['payload' => ['mail_avatars' => 'domain'], 'key' => 'mail_avatars', 'stored' => 'domain'],
            ['payload' => ['mail_columns' => ['date', 'from']], 'key' => 'mail_columns', 'stored' => ['date', 'from']],
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

    public function test_mail_columns_keep_their_order_and_reject_the_unknown(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Not chosen yet: null, so the client falls back to its default set
        // instead of a frozen copy of today's columns.
        $this->assertNull(UserSetting::for($user->id)->displayPrefs()['mail_columns']);

        $this->postJson(route('api.preferences.update'), ['mail_columns' => ['date', 'from', 'attachment']])->assertOk();
        $this->assertSame(['date', 'from', 'attachment'], UserSetting::for($user->id)->displayPrefs()['mail_columns']);

        // Duplicates collapse, unknown keys are dropped, order survives.
        $this->postJson(route('api.preferences.update'), ['mail_columns' => ['from', 'from', 'size']])->assertOk();
        $this->assertSame(['from', 'size'], UserSetting::for($user->id)->displayPrefs()['mail_columns']);

        $this->postJson(route('api.preferences.update'), ['mail_columns' => ['from', 'nonsense']])->assertStatus(422);

        // An empty selection means "default set", never "show nothing".
        $this->postJson(route('api.preferences.update'), ['mail_columns' => []])->assertOk();
        $this->assertNull(UserSetting::for($user->id)->displayPrefs()['mail_columns']);
    }
}
