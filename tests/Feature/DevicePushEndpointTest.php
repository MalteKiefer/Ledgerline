<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POST/DELETE /api/v1/device/push-endpoint register + clear the per-device
 * UnifiedPush endpoint on the calling device token (stored encrypted).
 */
class DevicePushEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/device/push-endpoint', ['endpoint' => 'https://push.example/t'])->assertUnauthorized();
    }

    public function test_registers_and_stores_the_endpoint_encrypted(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/v1/device/push-endpoint', ['endpoint' => 'https://push.example/topic/abc'], $this->bearer($user))
            ->assertOk()->assertJsonPath('ok', true);

        $token = $user->tokens()->firstOrFail();
        $this->assertSame('https://push.example/topic/abc', $token->push_endpoint); // decrypted via cast
        // At rest it is ciphertext, not the plaintext URL.
        $raw = (string) DB::table('personal_access_tokens')->where('id', $token->id)->value('push_endpoint');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString('push.example', $raw);
    }

    public function test_rejects_a_non_https_endpoint(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/v1/device/push-endpoint', ['endpoint' => 'http://push.example/t'], $this->bearer($user))
            ->assertStatus(422);
    }

    public function test_destroy_clears_the_endpoint(): void
    {
        $user = User::factory()->create();
        $headers = $this->bearer($user);
        $this->postJson('/api/v1/device/push-endpoint', ['endpoint' => 'https://push.example/t'], $headers)->assertOk();
        $this->deleteJson('/api/v1/device/push-endpoint', [], $headers)->assertOk();

        $this->assertNull($user->tokens()->firstOrFail()->push_endpoint);
    }

    public function test_device_list_exposes_push_host_and_clears_it_per_token(): void
    {
        $user = User::factory()->create();
        $headers = $this->bearer($user);
        $this->postJson('/api/v1/device/push-endpoint', ['endpoint' => 'https://ntfy.sh/topic/secret'], $headers)->assertOk();
        $tokenId = $user->tokens()->firstOrFail()->id;

        // The device list shows only scheme+host — never the secret topic path.
        $list = $this->getJson('/api/v1/devices', $headers)->assertOk()->json('devices');
        $this->assertSame('https://ntfy.sh', $list[0]['pushHost']);
        $this->assertStringNotContainsString('secret', json_encode($list) ?: '');

        // Owner clears the endpoint for that specific token.
        $this->deleteJson("/api/v1/devices/{$tokenId}/push", [], $headers)->assertOk();
        $this->assertNull($user->tokens()->firstOrFail()->push_endpoint);

        // Another user cannot clear it (owner-scoped: no-op, no error).
        $other = User::factory()->create();
        $this->deleteJson("/api/v1/devices/{$tokenId}/push", [], $this->bearer($other))->assertOk();
    }
}
