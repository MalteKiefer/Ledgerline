<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use App\Models\SharedVaultStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sanctum bearer-token (device ability) tests for the shared vault API
 * exposed under /api/v1/vaults/*.
 *
 * These tests exercise the same controllers that the web routes use,
 * but authenticate via a Sanctum `device`-scoped token rather than a
 * browser session — confirming the controllers are guard-agnostic.
 *
 * Uses real tokens (createToken) rather than Sanctum::actingAs because the
 * UpdateTokenIp middleware needs a persisted PersonalAccessToken row to work
 * correctly (Sanctum::actingAs produces a Mockery mock that trips the middleware).
 */
class VaultApiTokenTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Return Authorization header for a device-scoped bearer token. */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device', ['device'])->plainTextToken];
    }

    /** Return Authorization header for a token with a custom (non-device) ability. */
    private function bearerWithAbility(User $user, string $ability): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('other', [$ability])->plainTextToken];
    }

    private function makeVault(User $owner): SharedVault
    {
        $vault = new SharedVault;
        $vault->owner_id = $owner->id;
        $vault->save();

        SharedVaultStore::create(['vault_id' => $vault->id, 'version' => 0]);

        return $vault;
    }

    private function addMember(
        SharedVault $vault,
        User $user,
        string $role,
        string $status = 'active',
    ): SharedVaultMember {
        return SharedVaultMember::create([
            'vault_id' => $vault->id,
            'user_id' => $user->id,
            'role' => $role,
            'wrapped_vault_key' => 'WRAPPED',
            'recipient_fingerprint' => null,
            'status' => $status,
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. PUT /api/v1/vaults/keys — publish identity keypair
    // -------------------------------------------------------------------------

    public function test_publish_keys_stores_identity_keypair(): void
    {
        $user = User::factory()->create();

        $response = $this->putJson('/api/v1/vaults/keys', [
            'public_key' => 'x25519-pubkey-abc',
            'wrapped_secret_key' => 'wrapped-sk-abc',
            'fingerprint' => 'fp-abc',
        ], $this->bearer($user));

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $user->refresh();
        $this->assertSame('x25519-pubkey-abc', $user->x25519_public_key);
        $this->assertSame('wrapped-sk-abc', $user->wrapped_x25519_secret_key);
        $this->assertSame('fp-abc', $user->public_key_fingerprint);
    }

    public function test_publish_keys_conflicts_when_different_key_already_stored(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'x25519_public_key' => 'original-key',
            'wrapped_x25519_secret_key' => 'original-sk',
            'public_key_fingerprint' => 'original-fp',
        ])->save();

        $response = $this->putJson('/api/v1/vaults/keys', [
            'public_key' => 'different-key',
            'wrapped_secret_key' => 'different-sk',
            'fingerprint' => 'different-fp',
        ], $this->bearer($user));

        $response->assertStatus(409);
        $response->assertJson(['error' => 'key_conflict']);
    }

    // -------------------------------------------------------------------------
    // 2. POST /api/v1/vaults — create vault
    // -------------------------------------------------------------------------

    public function test_create_vault_returns_vault_with_owner_member(): void
    {
        $owner = User::factory()->create();

        $response = $this->postJson('/api/v1/vaults', [
            'wrapped_vault_key' => 'WRAPPED-VK',
        ], $this->bearer($owner));

        $response->assertCreated();
        $response->assertJsonStructure(['id']);

        $vaultId = $response->json('id');

        $member = SharedVaultMember::where('vault_id', $vaultId)
            ->where('user_id', $owner->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertSame('manager', $member->role->value);
        $this->assertSame('active', $member->status);
        $this->assertSame('WRAPPED-VK', $member->wrapped_vault_key);
    }

    // -------------------------------------------------------------------------
    // 3. GET /api/v1/vaults — list memberships
    // -------------------------------------------------------------------------

    public function test_index_lists_vaults_for_member(): void
    {
        $owner = User::factory()->create();
        $h = $this->bearer($owner);

        $this->postJson('/api/v1/vaults', ['wrapped_vault_key' => 'WRAPPED-1'], $h)->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/vaults', ['wrapped_vault_key' => 'WRAPPED-2'], $h)->assertCreated();
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/vaults', $h);

        $response->assertOk();
        $items = $response->json();
        $this->assertIsArray($items);
        $this->assertCount(2, $items);

        foreach ($items as $item) {
            $this->assertArrayHasKey('vault_id', $item);
            $this->assertArrayHasKey('role', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('wrapped_vault_key', $item);
        }
    }

    // -------------------------------------------------------------------------
    // 4. PUT /api/v1/vaults/{vault}/store — optimistic-lock 409 on stale version
    // -------------------------------------------------------------------------

    public function test_store_optimistic_lock_conflict_returns_409(): void
    {
        $owner = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');

        SharedVaultStore::where('vault_id', $vault->id)->update([
            'sealed_manifest' => 'current-ciphertext',
            'version' => 5,
        ]);

        $response = $this->putJson("/api/v1/vaults/{$vault->id}/store", [
            'sealed_manifest' => 'stale-write',
            'expected_version' => 3, // stale
        ], $this->bearer($owner));

        $response->assertStatus(409);
        $data = $response->json();
        $this->assertSame(5, $data['version']);
        $this->assertSame('current-ciphertext', $data['sealed_manifest']);
    }

    // -------------------------------------------------------------------------
    // 5. POST /api/v1/vaults/{vault}/resolve-recipient — manage-gated
    // -------------------------------------------------------------------------

    public function test_resolve_recipient_requires_manage_permission(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');
        $this->addMember($vault, $viewer, 'viewer');

        $response = $this->postJson("/api/v1/vaults/{$vault->id}/resolve-recipient", [
            'identifier' => 'someone@example.com',
        ], $this->bearer($viewer));

        // Policy denyAsNotFound → 404
        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // 6. POST /api/v1/vaults/{vault}/resolve-recipient — uniform 422
    // -------------------------------------------------------------------------

    public function test_resolve_recipient_returns_uniform_422_for_unknown_email(): void
    {
        $manager = User::factory()->create();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        $response = $this->postJson("/api/v1/vaults/{$vault->id}/resolve-recipient", [
            'identifier' => 'nobody@example.com',
        ], $this->bearer($manager));

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // 7. Member lifecycle: create → pending → accept → active
    // -------------------------------------------------------------------------

    public function test_member_lifecycle_create_pending_accept_active(): void
    {
        $manager = User::factory()->create();
        $invitee = User::factory()->create();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        // Step 1: manager creates a pending member.
        $createResponse = $this->postJson("/api/v1/vaults/{$vault->id}/members", [
            'user_id' => $invitee->id,
            'role' => 'viewer',
            'wrapped_vault_key' => 'WRAPPED-FOR-INVITEE',
            'recipient_fingerprint' => 'fp-invitee',
        ], $this->bearer($manager));

        $createResponse->assertCreated();

        $membership = SharedVaultMember::where('vault_id', $vault->id)
            ->where('user_id', $invitee->id)
            ->firstOrFail();

        $this->assertSame('pending', $membership->status);
        $this->assertSame('viewer', $membership->role->value);

        // Step 2: invitee accepts their invitation.
        $this->app['auth']->forgetGuards();

        $acceptResponse = $this->postJson(
            "/api/v1/vaults/{$vault->id}/members/{$membership->id}/accept",
            [],
            $this->bearer($invitee)
        );

        $acceptResponse->assertOk();
        $this->assertSame('active', $membership->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // 8. Token WITHOUT the `device` ability is rejected (403)
    // -------------------------------------------------------------------------

    public function test_device_ability_required(): void
    {
        $user = User::factory()->create();
        // Mint a token with a different scope — no 'device' ability.
        $h = $this->bearerWithAbility($user, 'read-only');

        $response = $this->getJson('/api/v1/vaults', $h);

        $response->assertForbidden();
    }
}
