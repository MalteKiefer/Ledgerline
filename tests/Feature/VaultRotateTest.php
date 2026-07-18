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
 * HTTP-level tests for:
 *   GET    /vaults/{vault}/members           → list members with public key
 *   POST   /vaults/{vault}/rotate            → atomic key rotation + member removal
 *   DELETE /vaults/{vault}                   → vault deletion (cascades members + store)
 */
class VaultRotateTest extends TestCase
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

        SharedVaultStore::create([
            'vault_id'        => $vault->id,
            'sealed_manifest' => 'SEALED',
            'version'         => 1,
        ]);

        return $vault;
    }

    private function addMember(
        SharedVault $vault,
        User $user,
        string $role,
        string $status = 'active',
    ): SharedVaultMember {
        return SharedVaultMember::create([
            'vault_id'              => $vault->id,
            'user_id'               => $user->id,
            'role'                  => $role,
            'wrapped_vault_key'     => 'WRAPPED',
            'recipient_fingerprint' => 'FP-' . $user->id,
            'status'                => $status,
        ]);
    }

    private function rotatePayload(int $removeId, array $memberIds, int $version = 1): array
    {
        return [
            'sealed_manifest'  => 'NEW-SEALED',
            'expected_version' => $version,
            'members'          => array_map(fn (int $id) => [
                'user_id'          => $id,
                'wrapped_vault_key' => 'REWRAPPED-' . $id,
            ], $memberIds),
            'remove_member_id' => $removeId,
        ];
    }

    // -------------------------------------------------------------------------
    // GET /vaults/{vault}/members
    // -------------------------------------------------------------------------

    public function test_members_index_requires_manager_role(): void
    {
        $owner = User::factory()->create();
        $vault = $this->makeVault($owner);

        // viewer
        $viewer = User::factory()->create();
        $this->addMember($vault, $viewer, 'viewer');
        $this->actingAs($viewer)
            ->getJson(route('vaults.members.index', $vault))
            ->assertNotFound();

        // editor
        $editor = User::factory()->create();
        $this->addMember($vault, $editor, 'editor');
        $this->actingAs($editor)
            ->getJson(route('vaults.members.index', $vault))
            ->assertNotFound();

        // non-member
        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->getJson(route('vaults.members.index', $vault))
            ->assertNotFound();
    }

    public function test_members_index_returns_members_with_public_key(): void
    {
        $manager = User::factory()->create(['x25519_public_key' => 'MGR-PUB-KEY']);
        $member  = User::factory()->create(['x25519_public_key' => 'MBR-PUB-KEY']);
        $vault   = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');
        $memberRow = $this->addMember($vault, $member, 'viewer');

        $response = $this->actingAs($manager)
            ->getJson(route('vaults.members.index', $vault));

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(2, $data);

        $mgrEntry = collect($data)->firstWhere('user_id', $manager->id);
        $this->assertNotNull($mgrEntry);
        $this->assertSame('MGR-PUB-KEY', $mgrEntry['public_key']);
        $this->assertArrayHasKey('role', $mgrEntry);
        $this->assertArrayHasKey('status', $mgrEntry);
        $this->assertArrayHasKey('recipient_fingerprint', $mgrEntry);
        // Must NOT expose wrapped vault key
        $this->assertArrayNotHasKey('wrapped_vault_key', $mgrEntry);
    }

    public function test_members_index_does_not_return_wrapped_vault_key(): void
    {
        $manager = User::factory()->create();
        $vault   = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        $response = $this->actingAs($manager)
            ->getJson(route('vaults.members.index', $vault));

        $response->assertOk();
        foreach ($response->json() as $entry) {
            $this->assertArrayNotHasKey('wrapped_vault_key', $entry);
        }
    }

    // -------------------------------------------------------------------------
    // POST /vaults/{vault}/rotate — authorization
    // -------------------------------------------------------------------------

    public function test_rotate_requires_manager_role(): void
    {
        $owner  = User::factory()->create();
        $vault  = $this->makeVault($owner);
        $ownerM = $this->addMember($vault, $owner, 'manager');

        // viewer
        $viewer = User::factory()->create();
        $viewerM = $this->addMember($vault, $viewer, 'viewer');
        $this->actingAs($viewer)
            ->postJson(route('vaults.rotate', $vault), $this->rotatePayload($viewerM->id, [$owner->id]))
            ->assertNotFound();

        // editor
        $editor  = User::factory()->create();
        $editorM = $this->addMember($vault, $editor, 'editor');
        $this->actingAs($editor)
            ->postJson(route('vaults.rotate', $vault), $this->rotatePayload($editorM->id, [$owner->id]))
            ->assertNotFound();

        // non-member
        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->postJson(route('vaults.rotate', $vault), $this->rotatePayload($viewerM->id, [$owner->id]))
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // POST /vaults/{vault}/rotate — version conflict (409)
    // -------------------------------------------------------------------------

    public function test_rotate_returns_409_on_stale_expected_version(): void
    {
        $manager = User::factory()->create();
        $vault   = $this->makeVault($manager);
        $mgr     = $this->addMember($vault, $manager, 'manager');
        $other   = User::factory()->create();
        $otherM  = $this->addMember($vault, $other, 'viewer');

        $response = $this->actingAs($manager)
            ->postJson(route('vaults.rotate', $vault), $this->rotatePayload(
                $otherM->id,
                [$manager->id],
                version: 999, // wrong version
            ));

        $response->assertStatus(409);
        $response->assertJsonStructure(['version', 'sealed_manifest']);
        $this->assertSame(1, $response->json('version')); // current version
    }

    // -------------------------------------------------------------------------
    // POST /vaults/{vault}/rotate — happy path
    // -------------------------------------------------------------------------

    public function test_rotate_removes_member_updates_wrapped_keys_and_bumps_version(): void
    {
        $manager = User::factory()->create();
        $vault   = $this->makeVault($manager);
        $mgr     = $this->addMember($vault, $manager, 'manager');
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $mA      = $this->addMember($vault, $memberA, 'viewer');
        $mB      = $this->addMember($vault, $memberB, 'viewer');

        // Remove memberB, re-wrap for manager and memberA
        $response = $this->actingAs($manager)
            ->postJson(route('vaults.rotate', $vault), [
                'sealed_manifest'  => 'NEW-SEALED-MANIFEST',
                'expected_version' => 1,
                'members'          => [
                    ['user_id' => $manager->id, 'wrapped_vault_key' => 'NEW-MGR-WRAP'],
                    ['user_id' => $memberA->id, 'wrapped_vault_key' => 'NEW-A-WRAP'],
                ],
                'remove_member_id' => $mB->id,
            ]);

        $response->assertOk();
        $response->assertJson(['version' => 2]);

        // memberB row deleted
        $this->assertNull(SharedVaultMember::find($mB->id));

        // Remaining members have updated wrapped keys
        $this->assertSame('NEW-MGR-WRAP', SharedVaultMember::find($mgr->id)?->wrapped_vault_key);
        $this->assertSame('NEW-A-WRAP', SharedVaultMember::find($mA->id)?->wrapped_vault_key);

        // Manifest and version updated
        $store = SharedVaultStore::where('vault_id', $vault->id)->first();
        $this->assertSame('NEW-SEALED-MANIFEST', $store->sealed_manifest);
        $this->assertSame(2, (int) $store->version);
    }

    // -------------------------------------------------------------------------
    // POST /vaults/{vault}/rotate — validation: unknown/inactive member in list
    // -------------------------------------------------------------------------

    public function test_rotate_rejects_unknown_user_in_members_list(): void
    {
        $manager = User::factory()->create();
        $vault   = $this->makeVault($manager);
        $mgr     = $this->addMember($vault, $manager, 'manager');
        $victim  = User::factory()->create();
        $victimM = $this->addMember($vault, $victim, 'viewer');

        $stranger = User::factory()->create(); // not a member

        $response = $this->actingAs($manager)
            ->postJson(route('vaults.rotate', $vault), [
                'sealed_manifest'  => 'SEALED',
                'expected_version' => 1,
                'members'          => [
                    ['user_id' => $manager->id, 'wrapped_vault_key' => 'WRAP'],
                    ['user_id' => $stranger->id, 'wrapped_vault_key' => 'WRAP'], // not a member
                ],
                'remove_member_id' => $victimM->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_rotate_rejects_pending_member_in_members_list(): void
    {
        $manager = User::factory()->create();
        $vault   = $this->makeVault($manager);
        $mgr     = $this->addMember($vault, $manager, 'manager');
        $victim  = User::factory()->create();
        $victimM = $this->addMember($vault, $victim, 'viewer');
        $pending = User::factory()->create();
        $this->addMember($vault, $pending, 'viewer', 'pending');

        $response = $this->actingAs($manager)
            ->postJson(route('vaults.rotate', $vault), [
                'sealed_manifest'  => 'SEALED',
                'expected_version' => 1,
                'members'          => [
                    ['user_id' => $manager->id, 'wrapped_vault_key' => 'WRAP'],
                    ['user_id' => $pending->id, 'wrapped_vault_key' => 'WRAP'], // pending, not active
                ],
                'remove_member_id' => $victimM->id,
            ]);

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // POST /vaults/{vault}/rotate — remove_member_id not belonging to vault
    // -------------------------------------------------------------------------

    public function test_rotate_rejects_remove_member_id_from_different_vault(): void
    {
        $manager = User::factory()->create();
        $vault   = $this->makeVault($manager);
        $mgr     = $this->addMember($vault, $manager, 'manager');

        // Create a member row on a completely different vault
        $otherOwner = User::factory()->create();
        $otherVault = $this->makeVault($otherOwner);
        $otherMember = User::factory()->create();
        $foreignRow  = $this->addMember($otherVault, $otherMember, 'viewer');

        $response = $this->actingAs($manager)
            ->postJson(route('vaults.rotate', $vault), [
                'sealed_manifest'  => 'SEALED',
                'expected_version' => 1,
                'members'          => [
                    ['user_id' => $manager->id, 'wrapped_vault_key' => 'WRAP'],
                ],
                'remove_member_id' => $foreignRow->id, // belongs to another vault
            ]);

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // DELETE /vaults/{vault}
    // -------------------------------------------------------------------------

    public function test_delete_vault_requires_manager_role(): void
    {
        $owner  = User::factory()->create();
        $vault  = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');

        $viewer = User::factory()->create();
        $this->addMember($vault, $viewer, 'viewer');
        $this->actingAs($viewer)
            ->deleteJson(route('vaults.destroy', $vault))
            ->assertNotFound();

        $editor = User::factory()->create();
        $this->addMember($vault, $editor, 'editor');
        $this->actingAs($editor)
            ->deleteJson(route('vaults.destroy', $vault))
            ->assertNotFound();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->deleteJson(route('vaults.destroy', $vault))
            ->assertNotFound();
    }

    public function test_delete_vault_cascades_members_and_store(): void
    {
        $manager = User::factory()->create();
        $vault   = $this->makeVault($manager);
        $mgr     = $this->addMember($vault, $manager, 'manager');
        $other   = User::factory()->create();
        $otherM  = $this->addMember($vault, $other, 'viewer');

        $vaultId = $vault->id;

        $response = $this->actingAs($manager)
            ->deleteJson(route('vaults.destroy', $vault));

        $response->assertNoContent();

        $this->assertNull(SharedVault::find($vaultId));
        $this->assertNull(SharedVaultStore::find($vaultId));
        $this->assertNull(SharedVaultMember::find($mgr->id));
        $this->assertNull(SharedVaultMember::find($otherM->id));
    }
}
