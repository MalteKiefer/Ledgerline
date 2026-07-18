<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use App\Models\SharedVaultStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * HTTP-level tests for the shared vault API:
 *   POST   /vaults                              → create vault
 *   GET    /vaults                              → list memberships
 *   GET    /vaults/{vault}/store                → show sealed manifest
 *   PUT    /vaults/{vault}/store                → save (optimistic lock)
 *   POST   /vaults/{vault}/resolve-recipient    → public-key lookup
 *   POST   /vaults/{vault}/members              → add member (pending)
 *   POST   /vaults/{vault}/members/{m}/accept   → accept invite
 *   PATCH  /vaults/{vault}/members/{m}          → update role
 *   DELETE /vaults/{vault}/members/{m}          → remove member
 *
 * All routes are web routes behind `auth` middleware — uses actingAs().
 */
class VaultApiTest extends TestCase
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
        string $role,
        string $status = 'active',
    ): SharedVaultMember {
        return SharedVaultMember::create([
            'vault_id'            => $vault->id,
            'user_id'             => $user->id,
            'role'                => $role,
            'wrapped_vault_key'   => 'WRAPPED',
            'recipient_fingerprint' => null,
            'status'              => $status,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /vaults — create vault
    // -------------------------------------------------------------------------

    public function test_create_vault_returns_201_with_id(): void
    {
        $owner = $this->signIn();

        $response = $this->postJson(route('vaults.store'), [
            'wrapped_vault_key' => 'WRAPPED-VK',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id']);
    }

    public function test_create_vault_stamps_owner_as_active_manager_member(): void
    {
        $owner = $this->signIn();

        $response = $this->postJson(route('vaults.store'), [
            'wrapped_vault_key' => 'WRAPPED-VK',
        ]);

        $vaultId = $response->json('id');

        $membership = SharedVaultMember::where('vault_id', $vaultId)
            ->where('user_id', $owner->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame('manager', $membership->role);
        $this->assertSame('active', $membership->status);
        $this->assertSame('WRAPPED-VK', $membership->wrapped_vault_key);
    }

    public function test_create_vault_creates_store_row_at_version_zero(): void
    {
        $this->signIn();

        $response = $this->postJson(route('vaults.store'), [
            'wrapped_vault_key' => 'WRAPPED-VK',
        ]);

        $vaultId = $response->json('id');
        $store = SharedVaultStore::find($vaultId);

        $this->assertNotNull($store);
        $this->assertSame(0, (int) $store->version);
        $this->assertNull($store->sealed_manifest);
    }

    public function test_create_vault_requires_authentication(): void
    {
        // Web routes redirect unauthenticated requests to login (302).
        $response = $this->postJson(route('vaults.store'), [
            'wrapped_vault_key' => 'WRAPPED-VK',
        ]);

        $response->assertRedirect();
    }

    public function test_create_vault_requires_wrapped_vault_key(): void
    {
        $this->signIn();

        // Web routes with missing fields redirect back with flashed errors (302)
        // rather than returning 422 JSON — the exception handler renders JSON
        // only for api/* paths. Assert the request is rejected (not 2xx).
        $response = $this->postJson(route('vaults.store'), []);

        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(500, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // GET /vaults — list memberships
    // -------------------------------------------------------------------------

    public function test_index_returns_memberships_with_wrapped_key(): void
    {
        $owner = $this->signIn();

        $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'WRAPPED-1']);
        $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'WRAPPED-2']);

        $response = $this->getJson(route('vaults.index'));

        $response->assertOk();
        $items = $response->json();
        $this->assertCount(2, $items);

        // Every item must have the expected shape.
        foreach ($items as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('vault_id', $item);
            $this->assertArrayHasKey('role', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('wrapped_vault_key', $item);
        }
    }

    public function test_index_requires_authentication(): void
    {
        // Web routes redirect unauthenticated requests to login (302).
        $response = $this->getJson(route('vaults.index'));

        $response->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // GET /vaults/{vault}/store — show sealed manifest
    // -------------------------------------------------------------------------

    public function test_store_show_returns_nulls_on_first_use(): void
    {
        $owner = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');
        SharedVaultStore::create(['vault_id' => $vault->id, 'version' => 0]);

        $response = $this->getJson(route('vaults.storeShow', $vault));

        $response->assertOk();
        $response->assertJson(['sealed_manifest' => null, 'version' => 0]);
        // Laravel may append 'private' and reorder; assert the no-store directive is present.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    public function test_store_show_returns_stored_manifest(): void
    {
        $owner = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');
        SharedVaultStore::create([
            'vault_id'        => $vault->id,
            'sealed_manifest' => 'some-ciphertext',
            'version'         => 3,
        ]);

        $response = $this->getJson(route('vaults.storeShow', $vault));

        $response->assertOk();
        $response->assertJson(['sealed_manifest' => 'some-ciphertext', 'version' => 3]);
    }

    public function test_store_show_denied_for_non_member(): void
    {
        $owner = User::factory()->create();
        $outsider = $this->signIn();
        $vault = $this->makeVault($owner);
        SharedVaultStore::create(['vault_id' => $vault->id, 'version' => 0]);

        $response = $this->getJson(route('vaults.storeShow', $vault));

        // denyAsNotFound → 404
        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // PUT /vaults/{vault}/store — optimistic-lock save
    // -------------------------------------------------------------------------

    public function test_store_save_succeeds_and_bumps_version(): void
    {
        $owner = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');
        SharedVaultStore::create(['vault_id' => $vault->id, 'version' => 0]);

        $response = $this->putJson(route('vaults.storeSave', $vault), [
            'sealed_manifest' => 'new-ciphertext',
            'expected_version' => 0,
        ]);

        $response->assertOk();
        $response->assertJson(['version' => 1]);

        $this->assertSame('new-ciphertext', SharedVaultStore::find($vault->id)->sealed_manifest);
    }

    public function test_store_save_returns_409_on_stale_version(): void
    {
        $owner = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');
        SharedVaultStore::create([
            'vault_id'        => $vault->id,
            'sealed_manifest' => 'current-ciphertext',
            'version'         => 5,
        ]);

        $response = $this->putJson(route('vaults.storeSave', $vault), [
            'sealed_manifest'  => 'stale-write',
            'expected_version' => 3,  // stale
        ]);

        $response->assertStatus(409);
        $data = $response->json();
        $this->assertArrayHasKey('version', $data);
        $this->assertSame(5, $data['version']);
        $this->assertArrayHasKey('sealed_manifest', $data);
        $this->assertSame('current-ciphertext', $data['sealed_manifest']);
    }

    public function test_store_save_denied_for_viewer(): void
    {
        $owner = User::factory()->create();
        $viewer = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $viewer, 'viewer');
        SharedVaultStore::create(['vault_id' => $vault->id, 'version' => 0]);

        $response = $this->putJson(route('vaults.storeSave', $vault), [
            'sealed_manifest'  => 'forbidden-write',
            'expected_version' => 0,
        ]);

        // Policy: viewer cannot update → denyAsNotFound → 404
        $response->assertNotFound();
    }

    public function test_store_save_denied_for_non_member(): void
    {
        $owner = User::factory()->create();
        $outsider = $this->signIn();
        $vault = $this->makeVault($owner);
        SharedVaultStore::create(['vault_id' => $vault->id, 'version' => 0]);

        $response = $this->putJson(route('vaults.storeSave', $vault), [
            'sealed_manifest'  => 'forbidden-write',
            'expected_version' => 0,
        ]);

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // POST /vaults/{vault}/resolve-recipient
    // -------------------------------------------------------------------------

    public function test_resolve_recipient_returns_key_for_keyed_user_by_email(): void
    {
        $manager = $this->signIn();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        $target = User::factory()->create(['email' => 'alice@example.com']);
        $target->forceFill([
            'x25519_public_key'         => 'alice-pubkey',
            'wrapped_x25519_secret_key' => 'wrapped-sk',
            'public_key_fingerprint'    => 'alice-fp',
        ])->save();

        $response = $this->postJson(route('vaults.resolveRecipient', $vault), [
            'identifier' => 'alice@example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'user_id'     => $target->id,
            'public_key'  => 'alice-pubkey',
            'fingerprint' => 'alice-fp',
        ]);
    }

    public function test_resolve_recipient_returns_key_for_keyed_user_by_oidc_sub(): void
    {
        $manager = $this->signIn();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        $target = User::factory()->create(['oidc_sub' => 'sub-abc123']);
        $target->forceFill([
            'x25519_public_key'         => 'target-pubkey',
            'wrapped_x25519_secret_key' => 'wrapped-sk',
            'public_key_fingerprint'    => 'target-fp',
        ])->save();

        $response = $this->postJson(route('vaults.resolveRecipient', $vault), [
            'identifier' => 'sub-abc123',
        ]);

        $response->assertOk();
        $response->assertJson([
            'user_id'     => $target->id,
            'public_key'  => 'target-pubkey',
            'fingerprint' => 'target-fp',
        ]);
    }

    public function test_resolve_recipient_returns_422_for_unknown_identifier(): void
    {
        $manager = $this->signIn();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        $response = $this->postJson(route('vaults.resolveRecipient', $vault), [
            'identifier' => 'nobody@example.com',
        ]);

        $response->assertUnprocessable();
    }

    public function test_resolve_recipient_returns_422_for_user_without_key(): void
    {
        $manager = $this->signIn();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        // Existing user but no key published yet.
        User::factory()->create(['email' => 'nokey@example.com']);

        $response = $this->postJson(route('vaults.resolveRecipient', $vault), [
            'identifier' => 'nokey@example.com',
        ]);

        $response->assertUnprocessable();
    }

    public function test_resolve_recipient_requires_manage_ability(): void
    {
        $owner = User::factory()->create();
        $viewer = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $viewer, 'viewer');

        $response = $this->postJson(route('vaults.resolveRecipient', $vault), [
            'identifier' => 'someone@example.com',
        ]);

        // denyAsNotFound
        $response->assertNotFound();
    }

    public function test_resolve_recipient_is_rate_limited(): void
    {
        $manager = $this->signIn();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        // Clear any existing hits.
        RateLimiter::clear('pubkey-lookup|'.$manager->id);

        // Hit the endpoint 30 times (the limit).
        for ($i = 0; $i < 30; $i++) {
            $this->postJson(route('vaults.resolveRecipient', $vault), [
                'identifier' => 'nobody@example.com',
            ]);
        }

        // 31st hit must be throttled.
        $response = $this->postJson(route('vaults.resolveRecipient', $vault), [
            'identifier' => 'nobody@example.com',
        ]);

        $response->assertStatus(429);
    }

    // -------------------------------------------------------------------------
    // POST /vaults/{vault}/members — add member (pending)
    // -------------------------------------------------------------------------

    public function test_manager_can_add_member_as_pending(): void
    {
        $manager = $this->signIn();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        $target = User::factory()->create();

        $response = $this->postJson(route('vaults.members.store', $vault), [
            'user_id'            => $target->id,
            'role'               => 'viewer',
            'wrapped_vault_key'  => 'WRAPPED-FOR-TARGET',
            'recipient_fingerprint' => 'fp-target',
        ]);

        $response->assertCreated();

        $member = SharedVaultMember::where('vault_id', $vault->id)
            ->where('user_id', $target->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertSame('pending', $member->status);
        $this->assertSame('viewer', $member->role);
    }

    public function test_non_manager_cannot_add_member(): void
    {
        $owner = User::factory()->create();
        $editor = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $editor, 'editor');

        $target = User::factory()->create();

        $response = $this->postJson(route('vaults.members.store', $vault), [
            'user_id'           => $target->id,
            'role'              => 'viewer',
            'wrapped_vault_key' => 'WRAPPED',
        ]);

        // CreateMemberRequest authorize() fails → 403
        $response->assertForbidden();
    }

    public function test_adding_already_existing_member_is_rejected_not_500(): void
    {
        $manager = $this->signIn();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        $target = User::factory()->create();
        // Pre-existing membership for target.
        $this->addMember($vault, $target, 'viewer');

        $response = $this->postJson(route('vaults.members.store', $vault), [
            'user_id'           => $target->id,
            'role'              => 'viewer',
            'wrapped_vault_key' => 'WRAPPED',
        ]);

        // Duplicate (vault_id, user_id) → validation rejects gracefully, not 500.
        // Web route: validation redirects back (302) or returns 422; never a server error.
        $this->assertLessThan(500, $response->getStatusCode());
        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST /vaults/{vault}/members/{member}/accept — accept invite
    // -------------------------------------------------------------------------

    public function test_target_user_can_accept_their_pending_invite(): void
    {
        $owner = User::factory()->create();
        $invitee = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');
        $membership = $this->addMember($vault, $invitee, 'viewer', 'pending');

        $response = $this->postJson(route('vaults.members.accept', [$vault, $membership]));

        $response->assertOk();
        $this->assertSame('active', $membership->fresh()->status);
    }

    public function test_other_user_cannot_accept_a_membership_invite(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $thirdParty = $this->signIn();  // signs in as third-party
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $owner, 'manager');
        $membership = $this->addMember($vault, $invitee, 'viewer', 'pending');

        $response = $this->postJson(route('vaults.members.accept', [$vault, $membership]));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // PATCH /vaults/{vault}/members/{member} — update role
    // -------------------------------------------------------------------------

    public function test_manager_can_update_member_role(): void
    {
        $manager = $this->signIn();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        $other = User::factory()->create();
        $membership = $this->addMember($vault, $other, 'viewer');

        $response = $this->patchJson(route('vaults.members.update', [$vault, $membership]), [
            'role' => 'editor',
        ]);

        $response->assertOk();
        $this->assertSame('editor', $membership->fresh()->role);
    }

    public function test_non_manager_cannot_update_member_role(): void
    {
        $owner = User::factory()->create();
        $editor = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $editor, 'editor');

        $other = User::factory()->create();
        $membership = $this->addMember($vault, $other, 'viewer');

        $response = $this->patchJson(route('vaults.members.update', [$vault, $membership]), [
            'role' => 'editor',
        ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // DELETE /vaults/{vault}/members/{member} — remove member
    // -------------------------------------------------------------------------

    public function test_manager_can_delete_member(): void
    {
        $manager = $this->signIn();
        $vault = $this->makeVault($manager);
        $this->addMember($vault, $manager, 'manager');

        $other = User::factory()->create();
        $membership = $this->addMember($vault, $other, 'viewer');

        $response = $this->deleteJson(route('vaults.members.destroy', [$vault, $membership]));

        $response->assertOk();
        $this->assertNull($membership->fresh());
    }

    public function test_non_manager_cannot_delete_member(): void
    {
        $owner = User::factory()->create();
        $editor = $this->signIn();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $editor, 'editor');

        $other = User::factory()->create();
        $membership = $this->addMember($vault, $other, 'viewer');

        $response = $this->deleteJson(route('vaults.members.destroy', [$vault, $membership]));

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Cross-vault member isolation: member from vault A not accessible in vault B
    // -------------------------------------------------------------------------

    public function test_member_route_returns_404_when_member_belongs_to_different_vault(): void
    {
        $manager = $this->signIn();

        $vaultA = $this->makeVault($manager);
        $this->addMember($vaultA, $manager, 'manager');

        $vaultB = $this->makeVault($manager);
        $this->addMember($vaultB, $manager, 'manager');

        $other = User::factory()->create();
        $membershipInA = $this->addMember($vaultA, $other, 'viewer');

        // Try to DELETE a member of vault A via vault B's route.
        $response = $this->deleteJson(route('vaults.members.destroy', [$vaultB, $membershipInA]));

        $response->assertNotFound();
    }
}
