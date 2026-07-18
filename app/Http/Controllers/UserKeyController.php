<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages the user's x25519 identity keypair (used for ZK vault-key wrapping).
 *
 * Identity keys are write-once: once a public key is registered it cannot be
 * overwritten by a different key (doing so would invalidate all existing vault
 * invitations the user holds). A re-publish of the same public key is idempotent
 * and succeeds so that clients can safely retry on network errors.
 */
class UserKeyController extends Controller
{
    /**
     * Return the current user's published identity key material so the browser
     * can recover the wrapped private key on a second device without regenerating.
     *
     * Returns null fields when no identity key has been published yet.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'public_key' => $user->x25519_public_key,
            'wrapped_secret_key' => $user->wrapped_x25519_secret_key,
            'fingerprint' => $user->public_key_fingerprint,
        ]);
    }

    /**
     * Publish (or re-publish) the authenticated user's x25519 identity keypair.
     *
     * Returns 200 OK on first publish or same-key idempotent re-publish.
     * Returns 409 Conflict when the stored public key differs from the incoming one.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'public_key' => ['required', 'string'],
            'wrapped_secret_key' => ['required', 'string'],
            'fingerprint' => ['required', 'string'],
        ]);

        $user = $request->user();

        // Write-once guard: if the user already has a different public key stored,
        // reject. This prevents silent key rotation that would break all pending
        // vault invitations (wrapped vault keys are sealed to the original pubkey).
        if (
            filled($user->x25519_public_key) &&
            $user->x25519_public_key !== $data['public_key']
        ) {
            return response()->json(['error' => 'key_conflict'], 409);
        }

        $user->forceFill([
            'x25519_public_key' => $data['public_key'],
            'wrapped_x25519_secret_key' => $data['wrapped_secret_key'],
            'public_key_fingerprint' => $data['fingerprint'],
        ])->save();

        return response()->json(['ok' => true]);
    }
}
