<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\AuditLog;
use App\Models\DevicePairing;
use App\Models\User;
use App\Services\Auth\Pairing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * settings.security_changed diff audit + install_id client correlation.
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        config(['services.pocketid.admin_group' => null]); // no group configured = everyone admin
        $user = User::factory()->create();
        AppSettings::current()->update(['max_connected_devices' => 3, 'vault_remember_days' => 7, 'vault_public_idle_minutes' => 10]);

        return $user;
    }

    public function test_security_change_audits_the_exact_diff(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->put(route('settings.security.update'), [
            'vault_remember_days' => 7,             // unchanged
            'vault_public_idle_minutes' => 10,      // unchanged
            'max_connected_devices' => 5,           // changed 3 → 5
        ])->assertRedirect();

        $log = AuditLog::where('action', 'settings.security_changed')->first();
        $this->assertNotNull($log);
        $this->assertSame(['max_connected_devices'], array_keys($log->meta['changes']));
        $this->assertSame(3, $log->meta['changes']['max_connected_devices']['from']);
        $this->assertSame(5, $log->meta['changes']['max_connected_devices']['to']);
    }

    public function test_no_change_writes_no_security_changed_entry(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->put(route('settings.security.update'), [
            'vault_remember_days' => 7,
            'vault_public_idle_minutes' => 10,
            'max_connected_devices' => 3,
        ])->assertRedirect();

        $this->assertSame(0, AuditLog::where('action', 'settings.security_changed')->count());
    }

    public function test_pairing_stores_install_id_and_it_appears_in_device_audit(): void
    {
        AppSettings::current()->update(['max_connected_devices' => 1]);
        $user = User::factory()->create();

        // First device carries an install_id.
        DevicePairing::create([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', 'a-code'),
            'device_name' => 'phone-a',
            'status' => DevicePairing::APPROVED,
            'expires_at' => now()->addMinutes(10),
        ]);
        app(Pairing::class)->collect('a-code', '1.1.1.1', ['install_id' => 'inst-xyz', 'app_version' => '1.2.3']);

        $token = $user->tokens()->first();
        $this->assertSame('inst-xyz', $token->install_id);
        $this->assertSame('1.2.3', $token->app_version);

        // Pairing a second device (cap=1) evicts the first — the eviction audit
        // must carry that device's install_id for client correlation.
        DevicePairing::create([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', 'b-code'),
            'device_name' => 'phone-b',
            'status' => DevicePairing::APPROVED,
            'expires_at' => now()->addMinutes(10),
        ]);
        app(Pairing::class)->collect('b-code', '2.2.2.2');

        $evicted = AuditLog::where('action', 'device.evicted')->first();
        $this->assertNotNull($evicted);
        $this->assertSame('inst-xyz', $evicted->meta['install_id']);
    }
}
