<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * WebAuthn passkey endpoints. The full attestation/assertion ceremony needs a
 * real authenticator (browser), so these cover the reachable server surface:
 * options generation + caching, auth gating, password step-up, owner-scope.
 *
 * Note: each test authenticates a SINGLE user via a real bearer token — the
 * Sanctum guard caches the first resolved user across sub-requests in one test,
 * so cross-user checks live in their own single-user test.
 */
class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('web', ['device'])->plainTextToken];
    }

    private function makeCredential(User $user, string $credentialId = 'c1', string $name = 'Key'): WebauthnCredential
    {
        $cred = new WebauthnCredential;
        $cred->forceFill([
            'user_id' => $user->id, 'credential_id' => $credentialId, 'source' => '{}', 'name' => $name,
        ])->save();

        return $cred;
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/user/passkeys')->assertUnauthorized();
    }

    public function test_index_is_owner_scoped(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->makeCredential($b, 'other', 'B key');
        $this->getJson('/api/v1/user/passkeys', $this->bearer($a))
            ->assertOk()->assertJsonPath('passkeys', [])->assertJsonPath('enabled', true);
    }

    public function test_register_options_are_generated_and_cached(): void
    {
        $user = User::factory()->create();
        $res = $this->postJson('/api/v1/user/passkeys/options', [], $this->bearer($user))->assertOk();
        $body = $res->json();
        $this->assertArrayHasKey('challenge', $body);
        $this->assertArrayHasKey('user', $body);
        $this->assertNotNull(Cache::get('webauthn:reg:'.$user->id));
    }

    public function test_register_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);
        $this->postJson('/api/v1/user/passkeys', [
            'credential' => ['id' => 'x'],
            'current_password' => 'wrong-password',
        ], $this->bearer($user))->assertStatus(422);
    }

    public function test_owner_can_rename_and_delete_own_passkey(): void
    {
        $owner = User::factory()->create();
        $cred = $this->makeCredential($owner);
        $h = $this->bearer($owner);

        $this->putJson("/api/v1/user/passkeys/{$cred->id}", ['name' => 'Laptop'], $h)->assertOk();
        $this->assertSame('Laptop', $cred->fresh()?->name);
        $this->deleteJson("/api/v1/user/passkeys/{$cred->id}", [], $h)->assertOk();
        $this->assertNull(WebauthnCredential::withoutGlobalScopes()->find($cred->id));
    }

    public function test_other_user_cannot_touch_a_passkey(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $cred = $this->makeCredential($owner);
        $h = $this->bearer($other);

        $this->putJson("/api/v1/user/passkeys/{$cred->id}", ['name' => 'hacked'], $h)->assertNotFound();
        $this->deleteJson("/api/v1/user/passkeys/{$cred->id}", [], $h)->assertNotFound();
        $this->assertNotNull(WebauthnCredential::withoutGlobalScopes()->find($cred->id));
    }

    public function test_login_options_are_public_and_return_a_handle(): void
    {
        $res = $this->postJson('/api/v1/auth/passkey/options')->assertOk();
        $this->assertNotEmpty($res->json('handle'));
        $this->assertArrayHasKey('challenge', $res->json('options'));
    }

    public function test_login_verify_rejects_an_unknown_credential(): void
    {
        $this->postJson('/api/v1/auth/passkey/verify', [
            'handle' => 'nope',
            'credential' => ['id' => 'x', 'response' => []],
        ])->assertStatus(422)->assertJsonPath('message', 'passkey_failed');
    }
}
