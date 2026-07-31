<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use App\Models\SharedVaultStore;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the DB schema and Eloquent models introduced by the vault-sharing
 * feature (Task 1): shared_vaults, shared_vault_members, shared_vault_stores,
 * and the three x25519 key columns on users.
 */
class VaultSchemaTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Vault creation + owner stamping
    // -------------------------------------------------------------------------

    public function test_vault_gets_uuid_primary_key_on_create(): void
    {
        $owner = $this->signIn();

        $vault = SharedVault::create([]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $vault->id,
        );
    }

    public function test_vault_owner_is_stamped_from_authenticated_user(): void
    {
        $owner = $this->signIn();

        $vault = SharedVault::create([]);

        $this->assertSame($owner->id, (int) $vault->owner_id);
    }

    public function test_vault_has_members_relation(): void
    {
        $owner = $this->signIn();
        $vault = SharedVault::create([]);

        $this->assertSame(0, $vault->members()->count());
    }

    public function test_vault_has_store_relation(): void
    {
        $owner = $this->signIn();
        $vault = SharedVault::create([]);

        $this->assertNull($vault->store);
    }

    // -------------------------------------------------------------------------
    // VaultMember
    // -------------------------------------------------------------------------

    public function test_vault_member_can_be_created(): void
    {
        $owner = $this->signIn();
        $vault = SharedVault::create([]);
        $member = User::factory()->create();

        $row = SharedVaultMember::create([
            'vault_id' => $vault->id,
            'user_id' => $member->id,
            'role' => 'viewer',
            'wrapped_vault_key' => 'ciphertext-placeholder',
            'recipient_fingerprint' => 'fp-abc',
            'status' => 'pending',
        ]);

        $this->assertNotNull($row->id);
        $this->assertSame('viewer', $row->role->value);
    }

    public function test_duplicate_vault_user_pair_raises_unique_violation(): void
    {
        $owner = $this->signIn();
        $vault = SharedVault::create([]);
        $member = User::factory()->create();

        SharedVaultMember::create([
            'vault_id' => $vault->id,
            'user_id' => $member->id,
            'role' => 'viewer',
            'wrapped_vault_key' => 'ct',
            'status' => 'pending',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        SharedVaultMember::create([
            'vault_id' => $vault->id,
            'user_id' => $member->id,
            'role' => 'editor',
            'wrapped_vault_key' => 'ct2',
            'status' => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // SharedVaultStore
    // -------------------------------------------------------------------------

    public function test_shared_vault_store_can_be_created(): void
    {
        $owner = $this->signIn();
        $vault = SharedVault::create([]);

        SharedVaultStore::create([
            'vault_id' => $vault->id,
            'sealed_manifest' => 'encrypted-blob',
            'version' => 1,
        ]);

        $this->assertSame('encrypted-blob', $vault->fresh()->store?->sealed_manifest);
    }

    // -------------------------------------------------------------------------
    // Cascade delete: deleting a vault removes members + store
    // -------------------------------------------------------------------------

    public function test_cascade_delete_removes_members_and_store(): void
    {
        $owner = $this->signIn();
        $vault = SharedVault::create([]);
        $member = User::factory()->create();

        SharedVaultMember::create([
            'vault_id' => $vault->id,
            'user_id' => $member->id,
            'role' => 'editor',
            'wrapped_vault_key' => 'ct',
            'status' => 'active',
        ]);
        SharedVaultStore::create([
            'vault_id' => $vault->id,
            'sealed_manifest' => 'blob',
        ]);

        $vaultId = $vault->id;
        $vault->delete();

        $this->assertSame(0, SharedVaultMember::where('vault_id', $vaultId)->count());
        $this->assertNull(SharedVaultStore::find($vaultId));
    }

    // -------------------------------------------------------------------------
    // User x25519 key columns
    // -------------------------------------------------------------------------

    public function test_users_table_has_x25519_columns(): void
    {
        $user = User::factory()->create([
            'x25519_public_key' => null,
            'wrapped_x25519_secret_key' => null,
            'public_key_fingerprint' => null,
        ]);

        $fresh = $user->fresh();
        $this->assertNull($fresh->x25519_public_key);
        $this->assertNull($fresh->wrapped_x25519_secret_key);
        $this->assertNull($fresh->public_key_fingerprint);
    }

    public function test_x25519_columns_can_be_set_via_force_fill(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'x25519_public_key' => 'pubkey-hex',
            'wrapped_x25519_secret_key' => 'wrapped-sk',
            'public_key_fingerprint' => 'fp-123',
        ])->save();

        $fresh = $user->fresh();
        $this->assertSame('pubkey-hex', $fresh->x25519_public_key);
        $this->assertSame('wrapped-sk', $fresh->wrapped_x25519_secret_key);
        $this->assertSame('fp-123', $fresh->public_key_fingerprint);
    }

    public function test_user_has_vault_memberships_relation(): void
    {
        $owner = $this->signIn();
        $vault = SharedVault::create([]);

        SharedVaultMember::create([
            'vault_id' => $vault->id,
            'user_id' => $owner->id,
            'role' => 'manager',
            'wrapped_vault_key' => 'ct',
            'status' => 'active',
        ]);

        $this->assertSame(1, $owner->vaultMemberships()->count());
    }
}
