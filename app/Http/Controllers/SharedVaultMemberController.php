<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
 *   create  → pending  (manager invites a user; client supplies the vault key
 *                        wrapped for the recipient's x25519 public key)
 *   accept  → active   (only the invited user may accept their own invitation)
 *   update  → role change (manager only; self-change blocked by UpdateMemberRequest)
 *   destroy → row deleted (manager only)
 */
class SharedVaultMemberController extends Controller
{
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
            'vault_id'              => $vault->id,
            'user_id'               => $data['user_id'],
            'role'                  => $data['role'],
            'status'                => 'pending',
            'wrapped_vault_key'     => $data['wrapped_vault_key'],
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
        if ($member->vault_id !== $vault->id) {
            abort(404);
        }

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
        if ($member->vault_id !== $vault->id) {
            abort(404);
        }

        $member->role = $request->validated()['role'];
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
        if ($member->vault_id !== $vault->id) {
            abort(404);
        }

        $member->delete();

        return response()->json(['ok' => true]);
    }
}
