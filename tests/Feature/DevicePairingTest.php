<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DevicePairing;
use App\Models\User;
use App\Services\Auth\Pairing;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $collect = $this->getJson('/api/v1/auth/pair?code='.urlencode($code));
        $collect->assertOk()->assertJson(['status' => 'approved'])->assertJsonStructure(['token', 'user' => ['id']]);
        $token = $collect->json('token');
        $this->assertSame($user->id, $collect->json('user.id'));

        // The bearer authenticates the API.
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJson(['user' => ['id' => $user->id]])->assertJsonStructure(['usage' => ['files', 'gallery']]);

        // The pairing is spent — a second collect fails.
        $this->getJson('/api/v1/auth/pair?code='.urlencode($code))->assertStatus(410);
    }

    public function test_collect_is_pending_until_approved(): void
    {
        $user = User::factory()->create();
        [, $code] = $this->pending($user);
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'Tab']);

        $this->getJson('/api/v1/auth/pair?code='.urlencode($code))
            ->assertOk()->assertJson(['status' => 'pending']);
    }

    public function test_rejected_pairing_cannot_collect(): void
    {
        $user = User::factory()->create();
        [$pairing, $code] = $this->pending($user);
        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'Tab']);
        $this->actingAs($user)->postJson("/device-pairings/{$pairing->id}/reject")->assertOk();

        $this->getJson('/api/v1/auth/pair?code='.urlencode($code))->assertStatus(410);
    }

    public function test_expired_code_is_gone(): void
    {
        $user = User::factory()->create();
        $pairing = DevicePairing::factory()->expired()->for($user)->create();
        // Reconstruct is impossible (only the hash is stored), so drive by a known code.
        $code = 'known-expired-code-value-000000000000000000';
        $pairing->update(['code_hash' => hash('sha256', $code)]);

        $this->postJson('/api/v1/auth/pair', ['code' => $code, 'device_name' => 'Tab'])->assertStatus(410);
        $this->getJson('/api/v1/auth/pair?code='.urlencode($code))->assertStatus(410);
    }

    public function test_unknown_code_is_gone(): void
    {
        $this->getJson('/api/v1/auth/pair?code=nope')->assertStatus(410);
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
