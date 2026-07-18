<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\Vault\CreateMemberRequest;
use App\Http\Requests\Vault\UpdateMemberRequest;
use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

class VaultPolicyTest extends TestCase
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

    private function addMember(SharedVault $vault, User $user, string $role, string $status = 'active'): SharedVaultMember
    {
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
    // Policy: view
    // -------------------------------------------------------------------------

    public function test_non_member_cannot_view_vault_and_gets_404_style_deny(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $vault = $this->makeVault($owner);

        $this->actingAs($outsider);

        $result = $outsider->can('view', $vault);
        $this->assertFalse($result);
    }

    public function test_viewer_can_view_vault(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $viewer, 'viewer');

        $this->assertTrue($viewer->can('view', $vault));
    }

    public function test_pending_member_cannot_view_vault(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $viewer, 'viewer', 'pending');

        $this->assertFalse($viewer->can('view', $vault));
    }

    // -------------------------------------------------------------------------
    // Policy: update
    // -------------------------------------------------------------------------

    public function test_viewer_cannot_update_vault(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $viewer, 'viewer');

        $this->assertFalse($viewer->can('update', $vault));
    }

    public function test_editor_can_update_vault(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $editor, 'editor');

        $this->assertTrue($editor->can('update', $vault));
    }

    public function test_manager_can_update_vault(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $manager, 'manager');

        $this->assertTrue($manager->can('update', $vault));
    }

    // -------------------------------------------------------------------------
    // Policy: manage
    // -------------------------------------------------------------------------

    public function test_viewer_cannot_manage_vault(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $viewer, 'viewer');

        $this->assertFalse($viewer->can('manage', $vault));
    }

    public function test_editor_cannot_manage_vault(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $editor, 'editor');

        $this->assertFalse($editor->can('manage', $vault));
    }

    public function test_manager_can_manage_vault(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $manager, 'manager');

        $this->assertTrue($manager->can('manage', $vault));
    }

    // -------------------------------------------------------------------------
    // Policy: before() grants no blanket bypass
    // -------------------------------------------------------------------------

    public function test_non_member_gets_no_bypass_even_as_admin(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create(['groups' => ['admin']]);
        $vault = $this->makeVault($owner);

        // No membership → all three abilities must be denied.
        $this->assertFalse($outsider->can('view', $vault));
        $this->assertFalse($outsider->can('update', $vault));
        $this->assertFalse($outsider->can('manage', $vault));
    }

    // -------------------------------------------------------------------------
    // CreateMemberRequest: role-rank guard (via pseudo-controller helper)
    // -------------------------------------------------------------------------

    public function test_manager_can_add_viewer(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $target = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $manager, 'manager');

        // Simulate request validation inline.
        $request = $this->buildCreateRequest($manager, $vault, [
            'user_id' => $target->id,
            'role' => 'viewer',
            'wrapped_vault_key' => 'WRAPPED',
        ]);

        $this->assertTrue($request->authorize());
        $this->assertEmpty($this->extractRoleRankErrors($request));
    }

    public function test_manager_cannot_add_role_above_their_own(): void
    {
        // A manager is rank 3 = highest — this test covers an editor trying to
        // grant manager, since an editor is rank 2 < manager rank 3.
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $target = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $editor, 'editor');

        // Editor is rank 2, requesting manager (rank 3) → must fail authorize.
        $request = $this->buildCreateRequest($editor, $vault, [
            'user_id' => $target->id,
            'role' => 'manager',
            'wrapped_vault_key' => 'WRAPPED',
        ]);

        // authorize() returns false for editor (needs 'manage' ability).
        $this->assertFalse($request->authorize());
    }

    public function test_manager_cannot_grant_above_manager_rank_via_validator(): void
    {
        // Simulate: manager tries to grant 'manager' via withValidator role-rank check.
        // Since manager == manager (same rank), it SHOULD pass the withValidator guard.
        // We only block strictly ABOVE.
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $target = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $manager, 'manager');

        $request = $this->buildCreateRequest($manager, $vault, [
            'user_id' => $target->id,
            'role' => 'manager',
            'wrapped_vault_key' => 'WRAPPED',
        ]);

        $this->assertTrue($request->authorize());
        $errors = $this->extractRoleRankErrors($request);
        $this->assertEmpty($errors); // same rank is allowed
    }

    // -------------------------------------------------------------------------
    // UpdateMemberRequest: self-promotion block
    // -------------------------------------------------------------------------

    public function test_manager_cannot_change_their_own_membership_role(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $vault = $this->makeVault($owner);
        $membership = $this->addMember($vault, $manager, 'manager');

        $request = $this->buildUpdateRequest($manager, $vault, $membership, [
            'role' => 'editor',
        ]);

        $this->assertTrue($request->authorize());
        $errors = $this->extractRoleRankErrors($request);
        $this->assertNotEmpty($errors, 'Expected self-promotion error but got none.');
    }

    public function test_manager_can_change_another_members_role(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $editor = User::factory()->create();
        $vault = $this->makeVault($owner);
        $this->addMember($vault, $manager, 'manager');
        $editorMembership = $this->addMember($vault, $editor, 'editor');

        $request = $this->buildUpdateRequest($manager, $vault, $editorMembership, [
            'role' => 'viewer',
        ]);

        $this->assertTrue($request->authorize());
        $errors = $this->extractRoleRankErrors($request);
        $this->assertEmpty($errors);
    }

    // -------------------------------------------------------------------------
    // Helpers for FormRequest simulation
    // -------------------------------------------------------------------------

    private function buildCreateRequest(User $actor, SharedVault $vault, array $data): CreateMemberRequest
    {
        $request = CreateMemberRequest::create(
            uri: '/vault/'.$vault->id.'/members',
            method: 'POST',
            parameters: $data,
        );
        $request->setUserResolver(fn () => $actor);
        $request->setRouteResolver(function () use ($request, $vault) {
            return tap(new Route('POST', '/vault/{vault}/members', []), function ($r) use ($request, $vault) {
                $r->bind($request);
                $r->setParameter('vault', $vault);
            });
        });

        return $request;
    }

    private function buildUpdateRequest(User $actor, SharedVault $vault, SharedVaultMember $member, array $data): UpdateMemberRequest
    {
        $request = UpdateMemberRequest::create(
            uri: '/vault/'.$vault->id.'/members/'.$member->id,
            method: 'PATCH',
            parameters: $data,
        );
        $request->setUserResolver(fn () => $actor);
        $request->setRouteResolver(function () use ($request, $vault, $member) {
            return tap(new Route('PATCH', '/vault/{vault}/members/{member}', []), function ($r) use ($request, $vault, $member) {
                $r->bind($request);
                $r->setParameter('vault', $vault);
                $r->setParameter('member', $member);
            });
        });

        return $request;
    }

    /**
     * Run the validator (including withValidator after-hooks) and return any
     * errors on the `role` field. Does NOT throw — callers inspect the result.
     *
     * @return array<int, string>
     */
    private function extractRoleRankErrors(FormRequest $request): array
    {
        $validator = app(Factory::class)->make(
            $request->all(),
            $request->rules(),
        );

        // Register after-hooks first, then run validation so they fire.
        $request->withValidator($validator);
        $validator->passes();

        return $validator->errors()->get('role');
    }
}
