<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use App\Models\SharedVaultStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The highest-impact sharing/key events must leave an audit trail, and that
 * trail must NEVER contain secret material (wrapped keys, sealed manifests,
 * share tokens, ciphertext). These tests pin both properties.
 */
class VaultShareAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeVault(User $owner): SharedVault
    {
        $vault = new SharedVault;
        $vault->owner_id = $owner->id;
        $vault->save();

        SharedVaultStore::create([
            'vault_id' => $vault->id,
            'sealed_manifest' => 'SEALED',
            'version' => 1,
        ]);

        return $vault;
    }

    private function addMember(SharedVault $vault, User $user, string $role, string $status = 'active'): SharedVaultMember
    {
        return SharedVaultMember::create([
            'vault_id' => $vault->id,
            'user_id' => $user->id,
            'role' => $role,
            'wrapped_vault_key' => 'WRAPPED-SECRET-'.$user->id,
            'recipient_fingerprint' => 'FP-'.$user->id,
            'status' => $status,
        ]);
    }

    /** No secret material may ever appear in the serialized audit meta. */
    private function assertNoSecretsInMeta(AuditLog $entry): void
    {
        $blob = json_encode($entry->meta ?? []);
        $this->assertIsString($blob);
        foreach (['WRAPPED', 'SEALED', 'REWRAPPED', 'wrapped_vault_key', 'sealed_manifest'] as $needle) {
            $this->assertStringNotContainsString($needle, $blob, "audit meta leaked '{$needle}'");
        }
    }

    public function test_creating_a_shared_vault_writes_an_audit_row(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson(route('vaults.store'), [
                'wrapped_vault_key' => 'WRAPPED-SECRET-MATERIAL',
                'kind' => 'folder',
            ])
            ->assertCreated();

        $entry = AuditLog::where('action', 'vault.shared.created')->firstOrFail();
        $this->assertSame($owner->id, $entry->user_id);
        $this->assertSame(['kind' => 'folder'], $entry->meta);
        $this->assertNoSecretsInMeta($entry);
    }

    public function test_rotate_writes_a_revocation_audit_row(): void
    {
        $manager = User::factory()->create();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $this->addMember($vault, $memberA, 'viewer');
        $mB = $this->addMember($vault, $memberB, 'viewer');

        $this->actingAs($manager)
            ->postJson(route('vaults.rotate', $vault), [
                'sealed_manifest' => 'NEW-SEALED-MANIFEST',
                'expected_version' => 1,
                'members' => [
                    ['user_id' => $manager->id, 'wrapped_vault_key' => 'REWRAPPED-MGR'],
                    ['user_id' => $memberA->id, 'wrapped_vault_key' => 'REWRAPPED-A'],
                ],
                'remove_member_id' => $mB->id,
            ])
            ->assertOk();

        $entry = AuditLog::where('action', 'vault.member.rotated')->firstOrFail();
        $this->assertSame($manager->id, $entry->user_id);
        $this->assertSame($mB->id, $entry->meta['removed_member_id']);
        // manager + memberA remain (memberB rotated out).
        $this->assertSame(2, $entry->meta['remaining_members']);
        $this->assertNoSecretsInMeta($entry);
    }

    public function test_failed_rotate_writes_no_audit_row(): void
    {
        $manager = User::factory()->create();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');
        $other = User::factory()->create();
        $otherM = $this->addMember($vault, $other, 'viewer');

        // Stale version → 409, action must NOT be logged.
        $this->actingAs($manager)
            ->postJson(route('vaults.rotate', $vault), [
                'sealed_manifest' => 'NEW',
                'expected_version' => 999,
                'members' => [['user_id' => $manager->id, 'wrapped_vault_key' => 'W']],
                'remove_member_id' => $otherM->id,
            ])
            ->assertStatus(409);

        $this->assertSame(0, AuditLog::where('action', 'vault.member.rotated')->count());
    }
}
