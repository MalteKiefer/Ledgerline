<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stores a user's zero-knowledge encryption vault. Every value handled here is
 * opaque ciphertext or a public KDF parameter — the passphrase, recovery code
 * and vault key exist only in the browser and are never sent to the server.
 * Scoped per user: a caller only ever sees or writes their own vault row.
 */
class VaultController extends Controller
{
    /**
     * Whether the caller's vault is set up, and the material the browser needs
     * to derive and unwrap the vault key after the user types the passphrase.
     */
    public function show(): JsonResponse
    {
        $vault = Vault::current();

        if ($vault === null) {
            return response()->json(['configured' => false]);
        }

        return response()->json([
            'configured' => true,
            'salt' => $vault->salt,
            'kdf_ops' => $vault->kdf_ops,
            'kdf_mem' => $vault->kdf_mem,
            'wrapped_vault_key' => $vault->wrapped_vault_key,
            'wrap_nonce' => $vault->wrap_nonce,
            'has_recovery' => $vault->wrapped_vault_key_recovery !== null,
            // Ciphertext of the recovery wrap — safe to expose; only the offline
            // recovery code can open it.
            'wrapped_vault_key_recovery' => $vault->wrapped_vault_key_recovery,
            'recovery_nonce' => $vault->recovery_nonce,
        ]);
    }

    /**
     * First-time setup: persist the wrapped vault key and KDF parameters for the
     * caller. Refuses to overwrite an existing vault (that is a passphrase change
     * → rotate).
     */
    public function store(Request $request): JsonResponse
    {
        if (Vault::current() !== null) {
            return response()->json(['message' => __('vault.already_configured')], 409);
        }

        $vault = new Vault($this->rules($request, withRecovery: true));
        $vault->user_id = $request->user()->id; // stamped server-side, unfakeable
        $vault->save();

        return response()->json(['configured' => true], 201);
    }

    /**
     * Passphrase (or recovery) change: re-wrap the same vault key under a new
     * passphrase-derived key. The vault key itself is unchanged, so files need
     * no re-encryption.
     */
    public function rotate(Request $request): JsonResponse
    {
        $vault = Vault::current();

        if ($vault === null) {
            return response()->json(['message' => __('vault.not_configured')], 404);
        }

        $vault->update($this->rules($request, withRecovery: $request->filled('wrapped_vault_key_recovery')));

        return response()->json(['configured' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, bool $withRecovery): array
    {
        return $request->validate([
            'salt' => ['required', 'string', 'max:255'],
            // Enforce a real Argon2id cost floor so a tampered/downgraded row can't
            // leave the passphrase near-unstretched for an offline attacker. The
            // legit client uses OPSLIMIT_SENSITIVE (4) + MEMLIMIT_MODERATE (256 MiB),
            // so this floor (3 ops, 64 MiB) never rejects a genuine setup.
            'kdf_ops' => ['required', 'integer', 'min:3'],
            'kdf_mem' => ['required', 'integer', 'min:67108864'],
            'wrapped_vault_key' => ['required', 'string', 'max:1024'],
            'wrap_nonce' => ['required', 'string', 'max:255'],
            'wrapped_vault_key_recovery' => [$withRecovery ? 'required' : 'nullable', 'string', 'max:1024'],
            'recovery_nonce' => [$withRecovery ? 'required' : 'nullable', 'string', 'max:255'],
        ]);
    }
}
