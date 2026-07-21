<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VaultRole;
use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use App\Models\SharedVaultStore;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Manages shared password-Tresor containers.
 *
 * Create: the owner's wrapped vault key is captured up-front; an empty sealed
 * store and an active manager membership are created atomically. The owner is
 * stamped by the SharedVault creating-hook — not from request input.
 *
 * Index: returns the authenticated user's vault memberships (all statuses)
 * including the per-vault wrapped key so the client can unlock each vault it
 * has been invited to.
 *
 * ResolveRecipient: a manage-gated lookup that returns a recipient's x25519
 * public key and fingerprint so the inviting client can wrap the vault key for
 * them before calling the members endpoint. Returns a uniform 422 for both
 * "user not found" and "user has no key" to avoid enumerating user existence.
 *
 * Rotate: atomically removes one member, re-wraps the vault key for remaining
 * active members, and bumps the sealed manifest — all in a single transaction
 * with optimistic-concurrency version check.
 *
 * Destroy: deletes the vault and cascades to store + members (manager only).
 */
class SharedVaultController extends Controller
{
    /**
     * Create a new shared vault and return its id (201).
     *
     * In a single DB transaction:
     *   1. Create the SharedVault (owner stamped by model hook, not fillable).
     *   2. Create an empty SharedVaultStore at version 0.
     *   3. Create the owner's active manager membership with the provided
     *      wrapped vault key.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wrapped_vault_key' => ['required', 'string'],
        ]);

        // Validate kind explicitly so the controller always returns JSON for
        // this endpoint (ValidationException on web routes redirects; abort
        // emits the exact status code regardless of Accept header).
        $kind = $request->input('kind', 'password');
        if (! in_array($kind, ['password', 'folder'], true)) {
            abort(422, 'The selected kind is invalid.');
        }

        $vault = DB::transaction(function () use ($data, $request, $kind): SharedVault {
            $vault = new SharedVault;
            $vault->kind = $kind; // server-assigned; not mass-assignable
            $vault->save();

            SharedVaultStore::create([
                'vault_id' => $vault->id,
                'version' => 0,
            ]);

            SharedVaultMember::create([
                'vault_id' => $vault->id,
                'user_id' => $this->requireUser($request)->id,
                'role' => VaultRole::Manager->value,
                'status' => 'active',
                'wrapped_vault_key' => $data['wrapped_vault_key'],
                'recipient_fingerprint' => null,
            ]);

            return $vault;
        });

        return response()->json(['id' => $vault->id], 201);
    }

    /**
     * List all vault memberships (and their wrapped keys) for the current user.
     *
     * Includes pending and revoked rows so the client can render invitations.
     * The vault relation is eager-loaded to avoid N+1.
     */
    public function index(Request $request): JsonResponse
    {
        $kind = $request->query('kind');

        $memberships = $this->requireUser($request)
            ->vaultMemberships()
            ->with('vault')
            ->get()
            ->when($kind !== null, fn ($c) => $c->filter(fn (SharedVaultMember $m) => $m->vault?->kind === $kind))
            ->map(fn (SharedVaultMember $m) => [
                'id' => $m->id,
                'vault_id' => $m->vault_id,
                'kind' => $m->vault?->kind ?? 'password',
                'role' => $m->role->value,
                'status' => $m->status,
                // Whether the caller is the vault's owner (not just an invited manager).
                // Drives owner-only actions like converting a shared folder back to private.
                'owner' => $m->vault?->owner_id === $this->requireUser($request)->id,
                'wrapped_vault_key' => $m->wrapped_vault_key,
            ])
            ->values();

        return response()->json($memberships);
    }

    /**
     * Resolve a recipient by email or OIDC subject identifier and return their
     * x25519 public key and fingerprint so the inviting client can wrap the
     * vault key for them (manage-gated; rate-limited via `pubkey-lookup`).
     *
     * Returns a uniform 422 for both "user not found" and "user without key"
     * to avoid leaking user existence to the caller.
     */
    public function resolveRecipient(Request $request, SharedVault $vault): JsonResponse
    {
        $this->authorize('manage', $vault);

        $data = $request->validate([
            'identifier' => ['required', 'string'],
        ]);

        /** @var User|null $recipient */
        $recipient = User::where(function ($q) use ($data): void {
            $q->where('email', $data['identifier'])
                ->orWhere('oidc_sub', $data['identifier']);
        })
            ->whereNotNull('x25519_public_key')
            ->first();

        if ($recipient === null) {
            abort(422, 'Recipient not found or has no identity key registered.');
        }

        return response()->json([
            'user_id' => $recipient->id,
            'public_key' => $recipient->x25519_public_key,
            'fingerprint' => $recipient->public_key_fingerprint,
        ]);
    }

    /**
     * Atomically rotate the vault key: remove one member, update wrapped keys
     * for remaining active members, and bump the sealed manifest version.
     *
     * The operation is manage-gated and uses optimistic concurrency on the
     * store version to prevent silent clobbers from concurrent writers.
     *
     * Returns 409 on version conflict (current version + sealed_manifest
     * returned so the client can re-base) or 422 for invalid member references.
     */
    public function rotate(Request $request, SharedVault $vault): JsonResponse
    {
        $this->authorize('manage', $vault);

        $data = $request->validate([
            'sealed_manifest' => ['required', 'string', 'max:'.config('vault.manifest_max_bytes')],
            'expected_version' => ['required', 'integer', 'min:0'],
            'members' => ['required', 'array'],
            'members.*.user_id' => ['required', 'integer'],
            'members.*.wrapped_vault_key' => ['required', 'string'],
            'remove_member_id' => ['required', 'integer'],
        ]);

        // Inject authenticated user id so the transaction closure can perform the self-remove guard.
        $data['_auth_user_id'] = $this->requireUser($request)->id;

        $result = DB::transaction(function () use ($vault, $data): array {
            /** @var SharedVaultStore|null $row */
            $row = SharedVaultStore::where('vault_id', $vault->id)
                ->lockForUpdate()
                ->first();

            // Verify remove_member_id belongs to this vault (inside the lock to prevent races).
            $removeMemberRow = SharedVaultMember::where('vault_id', $vault->id)
                ->where('id', $data['remove_member_id'])
                ->first();

            if ($removeMemberRow === null) {
                return ['conflict' => false, 'invalid_member' => true];
            }

            // R4: A manager cannot remove themselves via rotate.
            if ((int) $removeMemberRow->user_id === (int) $data['_auth_user_id']) {
                return ['conflict' => false, 'self_remove' => true];
            }

            // Verify the removed member does not appear in the supplied members list.
            $incomingUserIds = array_column($data['members'], 'user_id');

            if (in_array((int) $removeMemberRow->user_id, array_map('intval', $incomingUserIds), true)) {
                return ['conflict' => false, 'invalid_user' => true, 'removed_in_list' => true];
            }

            $current = (int) ($row?->version ?? 0);

            if ($current !== (int) $data['expected_version']) {
                return [
                    'conflict' => true,
                    'version' => $current,
                    'sealed_manifest' => $row?->sealed_manifest,
                ];
            }

            // Collect active member user_ids for incoming-member validation.
            $activeUserIds = SharedVaultMember::where('vault_id', $vault->id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($data['members'] as $entry) {
                if (! in_array((int) $entry['user_id'], $activeUserIds, true)) {
                    return [
                        'conflict' => false,
                        'invalid_user' => true,
                    ];
                }
            }

            // Update wrapped vault keys for the remaining members.
            foreach ($data['members'] as $entry) {
                SharedVaultMember::where('vault_id', $vault->id)
                    ->where('user_id', $entry['user_id'])
                    ->update(['wrapped_vault_key' => $entry['wrapped_vault_key']]);
            }

            // Remove the rotated-out member.
            SharedVaultMember::where('vault_id', $vault->id)
                ->where('id', $data['remove_member_id'])
                ->delete();

            $nextVersion = $current + 1;

            SharedVaultStore::where('vault_id', $vault->id)->update([
                'sealed_manifest' => $data['sealed_manifest'],
                'version' => $nextVersion,
            ]);

            return ['conflict' => false, 'invalid_user' => false, 'version' => $nextVersion];
        });

        if ($result['conflict']) {
            return response()->json([
                'version' => $result['version'],
                'sealed_manifest' => $result['sealed_manifest'],
            ], 409);
        }

        if ($result['invalid_member'] ?? false) {
            return response()->json(['error' => 'remove_member_id not a member of this vault'], 422);
        }

        if ($result['self_remove'] ?? false) {
            return response()->json(['error' => 'A manager cannot remove themselves via rotate'], 422);
        }

        if ($result['removed_in_list'] ?? false) {
            return response()->json(['error' => 'removed member must not appear in members list'], 422);
        }

        if ($result['invalid_user'] ?? false) {
            return response()->json(['error' => 'members contains unknown or inactive user'], 422);
        }

        return response()->json(['version' => $result['version'] ?? 0]);
    }

    /**
     * Delete the vault and cascade members + store (manager only).
     *
     * Returns 204 No Content on success.
     */
    public function destroy(Request $request, SharedVault $vault): Response
    {
        $this->authorize('manage', $vault);

        $vault->delete();

        return response()->noContent();
    }
}
