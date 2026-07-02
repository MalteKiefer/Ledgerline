<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stores the vault's encrypted directory manifest.
 *
 * The manifest is a single ciphertext blob (sealed with the vault key in the
 * browser) holding the entire file/folder structure: names, tree, mime types,
 * sizes, per-file keys and tags. The server can neither read nor partially
 * update it — it only swaps whole ciphertexts, guarded by an optimistic
 * version so concurrent tabs cannot overwrite each other.
 */
class VaultManifestController extends Controller
{
    public function show(): JsonResponse
    {
        $vault = Vault::current();
        abort_if($vault === null, 404);

        return response()->json([
            'cipher' => $vault->manifest_cipher,
            'nonce' => $vault->manifest_nonce,
            'version' => (int) $vault->manifest_version,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $vault = Vault::current();
        abort_if($vault === null, 404);

        $validated = $request->validate([
            'cipher' => ['required', 'string'],
            'nonce' => ['required', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        // Optimistic lock: the writer must have seen the current version.
        if ((int) $validated['version'] !== (int) $vault->manifest_version) {
            return response()->json([
                'message' => 'stale manifest',
                'version' => (int) $vault->manifest_version,
            ], 409);
        }

        $vault->update([
            'manifest_cipher' => $validated['cipher'],
            'manifest_nonce' => $validated['nonce'],
            'manifest_version' => $vault->manifest_version + 1,
        ]);

        return response()->json(['version' => (int) $vault->manifest_version]);
    }
}
