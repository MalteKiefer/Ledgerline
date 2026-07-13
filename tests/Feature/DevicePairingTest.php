<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DevicePairing;
use App\Models\User;
use App\Services\Auth\Pairing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class DevicePairingTest extends TestCase
{
    use RefreshDatabase;

    /** Create a pairing via the service and return [pairing, rawCode]. */
    private function pending(User $user): array
    {
        $r = app(Pairing::class)->create($user);

        return [$r['pairing'], $r['code']];
    }

    public function test_web_store_returns_a_qr_and_creates_a_pending_pairing(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/device-pairings');

        $res->assertOk()->assertJsonStructure(['id', 'qr', 'expires_at']);
        $this->assertStringStartsWith('data:image/svg+xml', $res->json('qr'));
        $this->assertDatabaseHas('device_pairings', [
            'id' => $res->json('id'), 'user_id' => $user->id, 'status' => DevicePairing::PENDING_SCAN,
        ]);
    }

    public function test_cli_store_returns_a_plain_code_with_a_60s_ttl(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/device-pairings/cli');

        $res->assertOk()->assertJsonStructure(['id', 'code', 'expires_at']);
        $res->assertJsonMissing(['qr' => true]);
        // Copy/paste codes are the raw 43-char secret, not a QR data URI.
        $this->assertSame(43, strlen((string) $res->json('code')));
        $this->assertStringStartsNotWith('data:', (string) $res->json('code'));

        $pairing = DevicePairing::findOrFail($res->json('id'));
        $this->assertSame(DevicePairing::PENDING_SCAN, $pairing->status);
        // Bounded to the tighter CLI window (allow a second of clock slack).
        $this->assertEqualsWithDelta(
            Pairing::CLI_TTL_SECONDS,
            now()->diffInSeconds($pairing->expires_at, false),
            1.5,
        );
    }

    public function test_cli_pairing_uses_the_same_flow_and_yields_a_bearer(): void
    {
        $user = User::factory()->create();

        $start = $this->actingAs($user)->postJson('/device-pairings/cli')->assertOk();
        $code = $start->json('code');
        $id = $start->json('id');

        // The CLI claims the pasted code just like the app does.
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'ledgerline-cli@host'])
            ->assertOk()->assertJson(['status' => 'pending']);
        $this->actingAs($user)->postJson("/device-pairings/{$id}/approve")->assertOk();

        $collect = $this->postJson('/api/v1/auth/pair/collect', ['code' => $code])->assertOk();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$collect->json('token')])
            ->assertOk()->assertJson(['user' => ['id' => $user->id]]);
    }

    public function test_cli_store_requires_authentication(): void
    {
        // Web (session) route — a guest is redirected to login, never served a code.
        $this->post('/device-pairings/cli')->assertRedirect();
    }

    public function test_full_pairing_flow_yields_a_working_bearer_token(): void
    {
        $user = User::factory()->create();
        [$pairing, $code] = $this->pending($user);

        // App claims the scanned code.
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'Pixel 8'])
            ->assertOk()->assertJson(['status' => 'pending']);
        $this->assertDatabaseHas('device_pairings', [
            'id' => $pairing->id, 'status' => DevicePairing::PENDING_APPROVAL, 'device_name' => 'Pixel 8',
        ]);

        // Web sees the claiming device and approves it.
        $this->actingAs($user)->getJson("/device-pairings/{$pairing->id}")
            ->assertOk()->assertJson(['status' => DevicePairing::PENDING_APPROVAL, 'device_name' => 'Pixel 8']);
        $this->actingAs($user)->postJson("/device-pairings/{$pairing->id}/approve")
            ->assertOk()->assertJson(['status' => DevicePairing::APPROVED]);

        // App collects the token exactly once.
        $collect = $this->postJson('/api/v1/auth/pair/collect', ['code' => $code]);
        $collect->assertOk()->assertJson(['status' => 'approved'])->assertJsonStructure(['token', 'user' => ['id']]);
        $token = $collect->json('token');
        $this->assertSame($user->id, $collect->json('user.id'));

        // The bearer authenticates the API.
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJson(['user' => ['id' => $user->id]])->assertJsonStructure(['usage' => ['files', 'gallery']]);

        // The pairing is spent — a second collect fails.
        $this->postJson('/api/v1/auth/pair/collect', ['code' => $code])->assertStatus(410);
    }

    public function test_collect_is_pending_until_approved(): void
    {
        $user = User::factory()->create();
        [, $code] = $this->pending($user);
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'Tab']);

        $this->postJson('/api/v1/auth/pair/collect', ['code' => $code])
            ->assertOk()->assertJson(['status' => 'pending']);
    }

    public function test_rejected_pairing_cannot_collect(): void
    {
        $user = User::factory()->create();
        [$pairing, $code] = $this->pending($user);
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'Tab']);
        $this->actingAs($user)->postJson("/device-pairings/{$pairing->id}/reject")->assertOk();

        $this->postJson('/api/v1/auth/pair/collect', ['code' => $code])->assertStatus(410);
    }

    public function test_expired_code_is_gone(): void
    {
        $user = User::factory()->create();
        $pairing = DevicePairing::factory()->expired()->for($user)->create();
        // Reconstruct is impossible (only the hash is stored), so drive by a known code.
        $code = 'known-expired-code-value-000000000000000000';
        $pairing->update(['code_hash' => hash('sha256', $code)]);

        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'Tab'])->assertStatus(410);
        $this->postJson('/api/v1/auth/pair/collect', ['code' => $code])->assertStatus(410);
    }

    public function test_unknown_code_is_gone(): void
    {
        $this->postJson('/api/v1/auth/pair/collect', ['code' => 'nope'])->assertStatus(410);
        $this->postJson('/api/v1/auth/pair', ['code' => 'nope', 'device_name' => 'x'])->assertStatus(410);
    }

    public function test_claiming_an_already_claimed_code_fails(): void
    {
        $user = User::factory()->create();
        [, $code] = $this->pending($user);
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'First'])->assertOk();
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'Second'])->assertStatus(410);
    }

    public function test_only_the_owner_may_approve_or_reject(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [$pairing, $code] = $this->pending($owner);
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'Tab']);

        $this->actingAs($other)->getJson("/device-pairings/{$pairing->id}")->assertStatus(404);
        $this->actingAs($other)->postJson("/device-pairings/{$pairing->id}/approve")->assertStatus(404);
        $this->actingAs($other)->postJson("/device-pairings/{$pairing->id}/reject")->assertStatus(404);
    }

    public function test_api_requires_a_bearer(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
        $this->deleteJson('/api/v1/auth/session')->assertStatus(401);
    }

    public function test_a_wiped_token_is_revoked_after_the_grace_window(): void
    {
        config(['devices.wipe_grace_minutes' => 15]);
        $user = User::factory()->create();
        $plain = $user->createToken('dev', ['device'])->plainTextToken;
        $row = $user->tokens()->first();

        // Flagged but still inside the grace window → the client can still reach
        // /me (to fetch the flag and self-erase).
        $row->forceFill(['wipe_requested_at' => now()->subMinutes(5)])->save();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$plain])
            ->assertOk()->assertJsonPath('wipe', true);

        // Past the grace window → hard revoked on next contact.
        $row->forceFill(['wipe_requested_at' => now()->subMinutes(30)])->save();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$plain])->assertStatus(401);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_the_prune_command_revokes_idle_and_wiped_tokens(): void
    {
        config(['devices.idle_days' => 90, 'devices.wipe_grace_minutes' => 15]);
        $user = User::factory()->create();
        $user->createToken('idle', ['device']);
        $user->createToken('wiped', ['device']);
        $user->createToken('fresh', ['device']);
        $user->tokens()->where('name', 'idle')->first()->forceFill(['last_used_at' => now()->subDays(120)])->save();
        $user->tokens()->where('name', 'wiped')->first()->forceFill(['wipe_requested_at' => now()->subMinutes(30)])->save();

        $this->artisan('devices:prune-tokens')->assertSuccessful();

        $this->assertSame(['fresh'], $user->tokens()->pluck('name')->all());
    }

    public function test_destroy_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('dev')->plainTextToken;

        $this->deleteJson('/api/v1/auth/session', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        // The token row is gone (revoked). Reset the auth manager so the next
        // request re-resolves the guard instead of reusing the memoised user
        // (a single-process test artifact; each real request is fresh).
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])->assertStatus(401);
    }

    public function test_pairing_enforces_the_device_cap(): void
    {
        config(['devices.max' => 2]);
        $user = User::factory()->create();
        $user->createToken('Old phone');
        $user->createToken('Tablet');

        // Pair a third device.
        [$pairing, $code] = $this->pending($user);
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'New phone']);
        app(Pairing::class)->approve($pairing->fresh());
        $this->postJson('/api/v1/auth/pair/collect', ['code' => $code])->assertOk()->assertJson(['status' => 'approved']);

        // Still at the cap, and the oldest ("Old phone") was evicted.
        $names = $user->tokens()->pluck('name');
        $this->assertCount(2, $names);
        $this->assertFalse($names->contains('Old phone'));
        $this->assertTrue($names->contains('New phone'));
    }

    public function test_collect_records_the_device_ip_and_the_list_shows_it(): void
    {
        $user = User::factory()->create();
        [$pairing, $code] = $this->pending($user);
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'Pixel']);
        app(Pairing::class)->approve($pairing->fresh());
        $this->postJson('/api/v1/auth/pair/collect', ['code' => $code])->assertOk();

        $token = PersonalAccessToken::query()->first();
        $this->assertNotNull($token->ip); // 127.0.0.1 in tests — recorded at collect

        $this->app['auth']->forgetGuards();
        $list = $this->actingAs($user)->getJson('/devices')->assertOk();
        $this->assertSame('Pixel', $list->json('devices.0.name'));
        $this->assertStringContainsString((string) $token->ip, $list->json('devices.0.meta'));
    }

    public function test_heartbeat_records_sync_state_and_reports_no_wipe(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('CLI')->plainTextToken;

        $this->postJson('/api/v1/device/heartbeat', ['state' => 'syncing', 'detail' => 'gallery 10/50'],
            ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJson(['wipe' => false]);

        $row = PersonalAccessToken::query()->first();
        $this->assertSame('syncing', $row->sync_state);
        $this->assertSame('gallery 10/50', $row->sync_detail);
        $this->assertNotNull($row->sync_reported_at);
    }

    public function test_wipe_flag_is_delivered_via_heartbeat_and_me(): void
    {
        $user = User::factory()->create();
        $pat = $user->createToken('CLI');
        $token = $pat->plainTextToken;
        $id = $pat->accessToken->getKey();

        // Owner flags the device for a remote wipe (web, session).
        $this->actingAs($user)->postJson("/devices/{$id}/wipe")->assertOk();
        $this->assertNotNull(PersonalAccessToken::query()->find($id)->wipe_requested_at);

        $this->app['auth']->forgetGuards();
        // The client learns of the wipe from both heartbeat and /me.
        $this->postJson('/api/v1/device/heartbeat', ['state' => 'idle'], ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJson(['wipe' => true]);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJson(['wipe' => true]);
    }

    public function test_wipe_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $id = $owner->createToken('CLI')->accessToken->getKey();

        $this->actingAs($other)->postJson("/devices/{$id}/wipe")->assertOk();
        $this->assertNull(PersonalAccessToken::query()->find($id)->wipe_requested_at);
    }

    public function test_devices_list_reports_sync_and_wipe_state(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('CLI')->plainTextToken;
        $this->postJson('/api/v1/device/heartbeat', ['state' => 'syncing', 'detail' => 'files'],
            ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->app['auth']->forgetGuards();
        $list = $this->actingAs($user)->getJson('/devices')->assertOk();
        $this->assertTrue($list->json('devices.0.syncing'));
        $this->assertSame('files', $list->json('devices.0.syncDetail'));
        $this->assertFalse($list->json('devices.0.wipeRequested'));
    }

    public function test_a_paired_device_can_be_revoked_from_the_web(): void
    {
        $user = User::factory()->create();
        $id = $user->createToken('Phone')->accessToken->getKey();

        $this->actingAs($user)->deleteJson("/devices/{$id}")->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_device_revoke_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $id = $owner->createToken('Phone')->accessToken->getKey();

        $this->actingAs($other)->deleteJson("/devices/{$id}")->assertOk();
        // Not the caller's token — it survives.
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_prune_drops_expired_and_consumed(): void
    {
        $user = User::factory()->create();
        DevicePairing::factory()->expired()->for($user)->create();
        DevicePairing::factory()->for($user)->create(['status' => DevicePairing::CONSUMED]);
        DevicePairing::factory()->for($user)->create(); // fresh, kept

        $deleted = app(Pairing::class)->prune();

        $this->assertSame(2, $deleted);
        $this->assertSame(1, DevicePairing::count());
    }
}
