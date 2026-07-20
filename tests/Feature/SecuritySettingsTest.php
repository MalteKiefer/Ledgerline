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

    public function test_admin_can_set_the_vault_lock_policy(): void
    {
        $this->signIn(); // single-user install = admin

        $this->get(route('settings.security.edit'))->assertOk();

        $this->put(route('settings.security.update'), [
            'vault_remember_days' => 14,
            'vault_public_idle_minutes' => 5,
            'max_connected_devices' => 8,
        ])->assertRedirect();

        $s = AppSettings::current();
        $this->assertSame(14, $s->vault_remember_days);
        $this->assertSame(5, $s->vault_public_idle_minutes);
        $this->assertSame(8, $s->max_connected_devices);
    }

    public function test_it_validates_the_ranges(): void
    {
        $this->signIn();
        $this->put(route('settings.security.update'), [
            'vault_remember_days' => 0,
            'vault_public_idle_minutes' => 99999,
            'max_connected_devices' => 0,
        ])->assertSessionHasErrors(['vault_remember_days', 'vault_public_idle_minutes', 'max_connected_devices']);
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
