<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\DevicePairing;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch 1/3/4 native-client parity: vault provisioning + rotation, per-user
 * settings (file version cap), theme in /me, and the
 * owner-side device-pairing flow — all previously web-only.
 */
class VaultLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('t', ['device'])->plainTextToken];
    }

    /** @return array<string,mixed> */
    private function vaultBody(): array
    {
        return [
            'salt' => str_repeat('a', 24),
            'kdf_ops' => 4,
            'kdf_mem' => 268435456,
            'wrapped_vault_key' => str_repeat('b', 80),
            'wrap_nonce' => str_repeat('c', 32),
            'wrapped_vault_key_recovery' => str_repeat('d', 80),
            'recovery_nonce' => str_repeat('e', 32),
        ];
    }

    public function test_provision_then_rotate_the_vault(): void
    {
        $user = User::factory()->create();
        $h = $this->bearer($user);

        $this->getJson('/api/v1/vault', $h)->assertOk()->assertJsonPath('configured', false);

        $this->postJson('/api/v1/vault', $this->vaultBody(), $h)
            ->assertCreated()->assertJsonPath('configured', true);

        // Second provision is refused (that is a rotate).
        $this->postJson('/api/v1/vault', $this->vaultBody(), $h)->assertStatus(409);

        $version = $this->getJson('/api/v1/vault', $h)->assertOk()->json('version');

        // Rotate with the current version → new version.
        $this->putJson('/api/v1/vault', $this->vaultBody() + ['expected_version' => $version], $h)
            ->assertOk()->assertJsonPath('version', $version + 1);

        // Stale rotate → 409.
        $this->putJson('/api/v1/vault', $this->vaultBody() + ['expected_version' => $version], $h)
            ->assertStatus(409)->assertJsonPath('error', 'version_conflict');
    }

    public function test_vault_requires_a_token(): void
    {
        $this->postJson('/api/v1/vault', $this->vaultBody())->assertUnauthorized();
    }

    public function test_settings_read_and_update(): void
    {
        $user = User::factory()->create();
        $h = $this->bearer($user);

        $this->getJson('/api/v1/settings', $h)->assertOk()
            ->assertJsonPath('file_max_versions', 10);

        $this->putJson('/api/v1/settings', [
            'file_max_versions' => 25,
        ], $h)->assertOk()
            ->assertJsonPath('file_max_versions', 25);

        $this->putJson('/api/v1/settings', ['file_max_versions' => 0], $h)
            ->assertStatus(422);

        $this->assertSame(25, UserSetting::for($user->id)->file_max_versions);
    }

    public function test_me_returns_theme(): void
    {
        $user = User::factory()->create();
        UserSetting::for($user->id)->update(['theme' => 'dark']);

        $this->getJson('/api/v1/me', $this->bearer($user))
            ->assertOk()->assertJsonPath('user.theme', 'dark');
    }

    public function test_owner_pairing_flow(): void
    {
        $user = User::factory()->create();
        $h = $this->bearer($user);

        $id = $this->postJson('/api/v1/device-pairings', [], $h)
            ->assertOk()->assertJsonStructure(['id', 'qr', 'expires_at'])->json('id');

        $this->getJson('/api/v1/device-pairings/'.$id, $h)->assertOk()->assertJsonStructure(['status']);
        // (Owner-scoping — a foreign user gets 404 — is covered by
        // test_pairing_is_owner_scoped_on_reject via the shared authorizeOwner guard.)
    }

    public function test_pairing_is_owner_scoped_on_reject(): void
    {
        $user = User::factory()->create();
        $pairing = DevicePairing::factory()->create(['user_id' => $user->id]);
        $other = User::factory()->create();

        $this->postJson('/api/v1/device-pairings/'.$pairing->id.'/reject', [], $this->bearer($other))
            ->assertNotFound();
    }
}
