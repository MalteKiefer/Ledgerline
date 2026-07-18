<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use App\Models\SharedVaultStore;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $vault = DB::transaction(function () use ($data, $request): SharedVault {
            $vault = SharedVault::create([]);

            SharedVaultStore::create([
                'vault_id' => $vault->id,
                'version'  => 0,
            ]);

            SharedVaultMember::create([
                'vault_id'           => $vault->id,
                'user_id'            => $request->user()->id,
                'role'               => 'manager',
                'status'             => 'active',
                'wrapped_vault_key'  => $data['wrapped_vault_key'],
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
        $memberships = $request->user()
            ->vaultMemberships()
            ->with('vault')
            ->get()
            ->map(fn (SharedVaultMember $m) => [
                'vault_id'          => $m->vault_id,
                'role'              => $m->role,
                'status'            => $m->status,
                'wrapped_vault_key' => $m->wrapped_vault_key,
            ]);

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
            'user_id'     => $recipient->id,
            'public_key'  => $recipient->x25519_public_key,
            'fingerprint' => $recipient->public_key_fingerprint,
        ]);
    }
}
