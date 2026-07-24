<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\AuditLog;
use App\Models\DevicePairing;
use App\Models\User;
use App\Services\Auth\Pairing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Every path that destroys a device token must leave exactly one audit entry
 * with a reason, and cap eviction must be LRU (never evict the most-recently-used
 * device). Covers the previously-silent killers: cap, idle, wipe-finalize, expiry.
 */
class DeviceAuditTest extends TestCase
{
    use RefreshDatabase;

    private function approvedPairing(User $user, string $name): DevicePairing
    {
        return DevicePairing::create([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $name.'-code'),
            'device_name' => $name,
            'status' => DevicePairing::APPROVED,
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public function test_repairing_the_same_install_supersedes_the_previous_token(): void
    {
        $user = User::factory()->create();
        // A previous pairing of the SAME physical device (same install_id).
        $old = $user->createToken('iPhone', ['device'])->accessToken;
        $old->forceFill(['install_id' => 'install-abc'])->save();

        $this->approvedPairing($user, 'iPhone2');
        app(Pairing::class)->collect('iPhone2-code', '1.2.3.4', ['install_id' => 'install-abc']);

        // The old row is replaced, not stacked: exactly one token, the new one.
        $this->assertNull(PersonalAccessToken::find($old->id), 'previous same-device token superseded');
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('install-abc', $user->tokens()->first()->install_id);

        $sup = AuditLog::where('action', 'device.superseded')->get();
        $this->assertCount(1, $sup);
        $this->assertSame($old->id, $sup->first()->meta['token_id']);
        $this->assertSame('reinstall', $sup->first()->meta['reason']);
        $this->assertArrayNotHasKey('token', $sup->first()->meta);
    }

    public function test_a_different_install_id_is_never_superseded(): void
    {
        $user = User::factory()->create();
        $a = $user->createToken('iPhone', ['device'])->accessToken;
        $a->forceFill(['install_id' => 'install-a'])->save();

        $this->approvedPairing($user, 'iPad');
        app(Pairing::class)->collect('iPad-code', '1.2.3.4', ['install_id' => 'install-b']);

        $this->assertNotNull(PersonalAccessToken::find($a->id), 'a genuinely different device is untouched');
        $this->assertSame(2, $user->tokens()->count());
        $this->assertSame(0, AuditLog::where('action', 'device.superseded')->count());
    }

    public function test_cap_eviction_is_lru_and_audited(): void
    {
        AppSettings::current()->update(['max_connected_devices' => 2]);
        $user = User::factory()->create();

        // Two tokens: an OLD one that is actively used, and a NEWER one gone idle.
        $old = $user->createToken('old-active', ['device'])->accessToken;
        $old->forceFill(['last_used_at' => now()->subMinute()])->save();
        $new = $user->createToken('new-idle', ['device'])->accessToken;
        $new->forceFill(['last_used_at' => now()->subDays(10)])->save();

        // Pairing a third device (cap=2) must evict the least-recently-used (new-idle),
        // NOT the oldest-by-id (old-active) — the old orderBy('id') bug.
        $pairing = $this->approvedPairing($user, 'third');
        app(Pairing::class)->collect('third-code', '1.2.3.4');

        $this->assertNotNull(PersonalAccessToken::find($old->id), 'actively-used device survived');
        $this->assertNull(PersonalAccessToken::find($new->id), 'least-recently-used device evicted');

        $evicted = AuditLog::where('action', 'device.evicted')->get();
        $this->assertCount(1, $evicted);
        $this->assertSame('cap', $evicted->first()->meta['reason']);
        $this->assertSame($new->id, $evicted->first()->meta['token_id']);
        // No secret leaked into meta.
        $this->assertArrayNotHasKey('token', $evicted->first()->meta);
    }

    public function test_pairing_sets_an_explicit_expiry(): void
    {
        $user = User::factory()->create();
        $pairing = $this->approvedPairing($user, 'phone');
        app(Pairing::class)->collect('phone-code', '1.2.3.4');

        $token = $user->tokens()->latest('id')->first();
        $this->assertNotNull($token->expires_at, 'expires_at is set explicitly at pairing');
    }

    public function test_idle_prune_is_audited(): void
    {
        config(['devices.idle_days' => 30]);
        $user = User::factory()->create();
        $token = $user->createToken('stale', ['device'])->accessToken;
        $token->forceFill(['last_used_at' => now()->subDays(60)])->save();

        $this->artisan('devices:prune-tokens')->assertSuccessful();

        $this->assertNull(PersonalAccessToken::find($token->id));
        $log = AuditLog::where('action', 'device.idle_pruned')->first();
        $this->assertNotNull($log);
        $this->assertSame($token->id, $log->meta['token_id']);
        $this->assertSame(30, $log->meta['idle_days']);
    }

    public function test_expired_token_is_pruned_and_audited(): void
    {
        config(['devices.idle_days' => 0]); // isolate the expiry sweep
        $user = User::factory()->create();
        $token = $user->createToken('gone', ['device'])->accessToken;
        $token->forceFill(['expires_at' => now()->subDay()])->save();

        $this->artisan('devices:prune-tokens')->assertSuccessful();

        $this->assertNull(PersonalAccessToken::find($token->id));
        $this->assertNotNull(AuditLog::where('action', 'device.expired')->where('meta->token_id', $token->id)->first());
    }

    public function test_wipe_finalize_is_audited(): void
    {
        config(['devices.idle_days' => 0, 'devices.wipe_grace_minutes' => 15]);
        $user = User::factory()->create();
        $token = $user->createToken('wiped', ['device'])->accessToken;
        $token->forceFill(['wipe_requested_at' => now()->subHour()])->save();

        $this->artisan('devices:prune-tokens')->assertSuccessful();

        $this->assertNull(PersonalAccessToken::find($token->id));
        $log = AuditLog::where('action', 'device.wipe_finalized')->first();
        $this->assertNotNull($log);
        $this->assertSame(15, $log->meta['grace_minutes']);
    }
}
