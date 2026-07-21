<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VaultRole;
use App\Http\Requests\Vault\CreateMemberRequest;
use App\Http\Requests\Vault\UpdateMemberRequest;
use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Manages membership rows in shared password-Tresore.
 *
 * Membership lifecycle:
 *   index   → list all active members with their public keys (manage-gated)
 *   create  → pending  (manager invites a user; client supplies the vault key
 *                        wrapped for the recipient's x25519 public key)
 *   accept  → active   (only the invited user may accept their own invitation)
 *   update  → role change (manager only; self-change blocked by UpdateMemberRequest)
 *   destroy → row deleted (manager only)
 */
class SharedVaultMemberController extends Controller
{
    /**
     * List all members of the vault with their public keys.
     *
     * Manage-gated. Returns id, user_id, role, status, recipient_fingerprint,
     * and the user's x25519 public key. wrapped_vault_key is intentionally
     * excluded (the key material is per-recipient ciphertext that only the
     * client that generated it would need).
     */
    public function index(Request $request, SharedVault $vault): JsonResponse
    {
        $this->authorize('manage', $vault);

        $members = $vault->members()
            ->with('user:id,name,email,x25519_public_key,mlkem_public_key')
            ->get()
            ->map(fn (SharedVaultMember $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role->value,
                'status' => $m->status,
                'recipient_fingerprint' => $m->recipient_fingerprint,
                'public_key' => $m->user?->x25519_public_key,
                // Store v3 (§6.3): needed to re-wrap VK_vault to each member on rotate.
                'mlkem_public_key' => $m->user?->mlkem_public_key,
            ]);

        return response()->json($members);
    }

    /**
     * Add a pending member to the vault.
     *
     * Authorization and role-rank validation are handled by CreateMemberRequest.
     * vault_id is taken from the route, not from request input.
     */
    public function store(CreateMemberRequest $request, SharedVault $vault): JsonResponse
    {
        $data = $request->validated();

        SharedVaultMember::create([
            'vault_id' => $vault->id,
            'user_id' => $data['user_id'],
            'role' => $data['role'],
            'status' => 'pending',
            'wrapped_vault_key' => $data['wrapped_vault_key'],
            'recipient_fingerprint' => $data['recipient_fingerprint'] ?? null,
        ]);

        return response()->json(['ok' => true], 201);
    }

    /**
     * Accept a pending vault invitation.
     *
     * Only the invited user (whose user_id matches the member row) may accept.
     * The member must belong to the route-bound vault.
     */
    public function accept(Request $request, SharedVault $vault, SharedVaultMember $member): JsonResponse
    {
        // Cross-vault isolation: the member row must belong to this vault.
        $this->ensureMemberOfVault($member, $vault);

        // Only pending invitations may be accepted — a revoked/active member
        // cannot re-activate themselves via a stale accept URL.
        abort_unless($member->status === 'pending', 422);

        // Only the target user may accept their own invitation.
        abort_unless((int) $member->user_id === (int) Auth::id(), 403);

        $member->status = 'active';
        $member->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Update a member's role.
     *
     * Authorization, role-rank validation and self-change guard are handled
     * by UpdateMemberRequest. The member must belong to the route-bound vault.
     */
    public function update(UpdateMemberRequest $request, SharedVault $vault, SharedVaultMember $member): JsonResponse
    {
        // Cross-vault isolation.
        $this->ensureMemberOfVault($member, $vault);

        $role = $request->validated()['role'];
        $member->role = VaultRole::from(is_string($role) ? $role : '');
        $member->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Remove a member from the vault.
     *
     * Requires the `manage` ability on the vault. The member must belong to
     * this vault (cross-vault isolation).
     */
    public function destroy(Request $request, SharedVault $vault, SharedVaultMember $member): JsonResponse
    {
        $this->authorize('manage', $vault);

        // Cross-vault isolation.
        $this->ensureMemberOfVault($member, $vault);

        $member->delete();

        return response()->json(['ok' => true]);
    }

    private function ensureMemberOfVault(SharedVaultMember $member, SharedVault $vault): void
    {
        if ($member->vault_id !== $vault->id) {
            abort(404);
        }
    }
}
