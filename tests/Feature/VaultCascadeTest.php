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
 * Verifies that deleting a User cascades correctly through the vault-sharing
 * tables via the FK cascadeOnDelete constraints introduced by the Phase 2a
 * migrations.
 *
 * Coverage not already in VaultSchemaTest (which covers vault-level cascade)
 * or VaultApiTest (which covers HTTP access control):
 *
 *   1. Deleting a non-owner member removes only their shared_vault_members row.
 *   2. Deleting the vault owner removes the shared_vaults row (owner_id FK cascade),
 *      which in turn removes the shared_vault_stores and all shared_vault_members
 *      rows via the vault-level FK cascades.
 */
class VaultCascadeTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeVault(User $owner): SharedVault
    {
        $vault = new SharedVault;
        $vault->owner_id = $owner->id;
        $vault->save();

        return $vault;
    }

    private function addMember(
        SharedVault $vault,
        User $user,
        string $role = 'viewer',
        string $status = 'active',
    ): SharedVaultMember {
        return SharedVaultMember::create([
            'vault_id' => $vault->id,
            'user_id' => $user->id,
            'role' => $role,
            'wrapped_vault_key' => 'ct-placeholder',
            'recipient_fingerprint' => null,
            'status' => $status,
        ]);
    }

    private function addStore(SharedVault $vault, string $manifest = 'sealed-blob'): SharedVaultStore
    {
        return SharedVaultStore::create([
            'vault_id' => $vault->id,
            'sealed_manifest' => $manifest,
            'version' => 1,
        ]);
    }

    // -------------------------------------------------------------------------
    // Cascade 1: deleting a non-owner member removes only their membership row
    // -------------------------------------------------------------------------

    public function test_deleting_non_owner_member_removes_their_membership_row(): void
    {
        $owner = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');

        $member = User::factory()->create();
        $membership = $this->addMember($vault, $member);

        $this->assertDatabaseHas('shared_vault_members', [
            'vault_id' => $vault->id,
            'user_id' => $member->id,
        ]);

        // Deleting the member user must cascade to their membership row.
        $member->delete();

        $this->assertDatabaseMissing('shared_vault_members', [
            'vault_id' => $vault->id,
            'user_id' => $member->id,
        ]);

        // Vault itself and owner's membership must still exist.
        $this->assertNotNull(SharedVault::find($vault->id));
        $this->assertDatabaseHas('shared_vault_members', [
            'vault_id' => $vault->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_deleting_member_does_not_affect_other_members_or_vault(): void
    {
        $owner = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');
        $this->addStore($vault);

        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $this->addMember($vault, $memberA);
        $this->addMember($vault, $memberB);

        $memberA->delete();

        // memberA gone, everything else intact.
        $this->assertDatabaseMissing('shared_vault_members', ['user_id' => $memberA->id]);
        $this->assertDatabaseHas('shared_vault_members', ['user_id' => $memberB->id]);
        $this->assertNotNull(SharedVault::find($vault->id));
        $this->assertNotNull(SharedVaultStore::find($vault->id));
    }

    // -------------------------------------------------------------------------
    // Cascade 2: deleting the vault owner cascades to shared_vaults, which
    // cascades further to shared_vault_stores and shared_vault_members
    // -------------------------------------------------------------------------

    public function test_deleting_vault_owner_removes_their_vault_and_all_related_rows(): void
    {
        $owner = User::factory()->create();
        $member = $this->signIn();

        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');
        $this->addMember($vault, $member);
        $this->addStore($vault);

        $vaultId = $vault->id;

        // Confirm rows exist before deletion.
        $this->assertNotNull(SharedVault::find($vaultId));
        $this->assertNotNull(SharedVaultStore::find($vaultId));
        $this->assertSame(2, SharedVaultMember::where('vault_id', $vaultId)->count());

        // Deleting the owner cascades through owner_id FK on shared_vaults.
        $owner->delete();

        // shared_vaults row gone.
        $this->assertNull(SharedVault::find($vaultId));

        // shared_vault_stores row gone (vault_id FK cascade from shared_vaults).
        $this->assertNull(SharedVaultStore::find($vaultId));

        // All member rows gone (vault_id FK cascade from shared_vaults).
        $this->assertSame(0, SharedVaultMember::where('vault_id', $vaultId)->count());
    }

    public function test_deleting_owner_of_one_vault_does_not_affect_another_vault(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = $this->signIn();

        $vaultA = $this->makeVault($ownerA);
        $this->addMember($vaultA, $ownerA, 'manager');
        $this->addStore($vaultA);

        $vaultB = $this->makeVault($ownerB);
        $this->addMember($vaultB, $ownerB, 'manager');
        $this->addStore($vaultB);

        $vaultAId = $vaultA->id;
        $vaultBId = $vaultB->id;

        $ownerA->delete();

        // Vault A and its data are gone.
        $this->assertNull(SharedVault::find($vaultAId));
        $this->assertNull(SharedVaultStore::find($vaultAId));
        $this->assertSame(0, SharedVaultMember::where('vault_id', $vaultAId)->count());

        // Vault B is completely unaffected.
        $this->assertNotNull(SharedVault::find($vaultBId));
        $this->assertNotNull(SharedVaultStore::find($vaultBId));
        $this->assertSame(1, SharedVaultMember::where('vault_id', $vaultBId)->count());
    }
}
