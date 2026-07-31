<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
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
        $user = $this->requireUser($request);

        return response()->json([
            'public_key' => $user->x25519_public_key,
            'wrapped_secret_key' => $user->wrapped_x25519_secret_key,
            'fingerprint' => $user->public_key_fingerprint,
            // Store v3 (§6.3): post-quantum ML-KEM-768 identity material.
            'mlkem_public_key' => $user->mlkem_public_key,
            'wrapped_mlkem_secret_key' => $user->wrapped_mlkem_secret_key,
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
        $request->validate([
            'public_key' => ['required', 'string'],
            'wrapped_secret_key' => ['required', 'string'],
            'fingerprint' => ['required', 'string'],
            // ML-KEM-768 identity (Store v3 §6.3), published with the X25519 pair
            // so every recipient can be wrapped to the hybrid KEM.
            'mlkem_public_key' => ['required', 'string'],
            'wrapped_mlkem_secret_key' => ['required', 'string'],
        ]);

        $publicKey = $request->string('public_key')->value();
        $wrappedSecretKey = $request->string('wrapped_secret_key')->value();
        $fingerprint = $request->string('fingerprint')->value();
        $mlkemPublicKey = $request->string('mlkem_public_key')->value();
        $wrappedMlkemSecretKey = $request->string('wrapped_mlkem_secret_key')->value();

        $user = $this->requireUser($request);

        // Write-once guard: if the user already has a different public key stored,
        // reject. This prevents silent key rotation that would break all pending
        // vault invitations (wrapped vault keys are sealed to the original pubkey).
        if (
            filled($user->x25519_public_key) &&
            $user->x25519_public_key !== $publicKey
        ) {
            return response()->json(['error' => 'key_conflict'], 409);
        }

        $user->forceFill([
            'x25519_public_key' => $publicKey,
            'wrapped_x25519_secret_key' => $wrappedSecretKey,
            'public_key_fingerprint' => $fingerprint,
            'mlkem_public_key' => $mlkemPublicKey,
            'wrapped_mlkem_secret_key' => $wrappedMlkemSecretKey,
        ])->save();

        // Fingerprint is already non-secret (it is compared out-of-band for TOFU).
        // NEVER log the wrapped secret key material.
        AuditLog::record('identity.key.published', $user, [
            'fingerprint' => $fingerprint,
        ]);

        return response()->json(['ok' => true]);
    }
}
