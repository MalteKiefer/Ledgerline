<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\User;
use App\Services\Auth\Pairing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_the_device_cap(): void
    {
        $this->signInAdmin(); // single-user install = admin

        $this->get(route('settings.security.edit'))->assertOk();

        $this->put(route('settings.security.update'), [
            'max_connected_devices' => 8,
        ])->assertRedirect();

        $this->assertSame(8, AppSettings::current()->max_connected_devices);
    }

    public function test_it_validates_the_ranges(): void
    {
        $this->signInAdmin();
        $this->put(route('settings.security.update'), [
            'max_connected_devices' => 0,
        ])->assertSessionHasErrors(['max_connected_devices']);
    }

    public function test_pairing_respects_the_admin_device_cap_over_config(): void
    {
        // config default is low; admin raises the cap via AppSettings.
        config(['devices.max' => 3]);
        AppSettings::current()->update(['max_connected_devices' => 5]);

        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $user->createToken('device '.$i, ['device']);
        }
        $this->assertSame(5, $user->tokens()->count());

        // Pair a 6th device — cap is 5, so exactly one (the oldest) is revoked.
        $svc = app(Pairing::class);
        ['pairing' => $p, 'code' => $code] = $svc->create($user);
        $svc->claim($code, 'New phone');
        $svc->approve($p->fresh());
        $svc->collect($code);

        $this->assertSame(5, $user->tokens()->count());
        $this->assertFalse($user->tokens()->where('name', 'device 0')->exists());
        $this->assertTrue($user->tokens()->where('name', 'New phone')->exists());
    }
}
