<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vault;
use App\Models\VaultManifest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stores the vault's encrypted manifests, one per zero-knowledge module
 * (files, notes).
 *
 * A manifest is a single ciphertext blob (sealed with the vault key in the
 * browser) holding a module's entire structure and metadata. The server can
 * neither read nor partially update it — it only swaps whole ciphertexts,
 * guarded by an optimistic version so concurrent tabs cannot overwrite each
 * other.
 */
class VaultManifestController extends Controller
{
    public function show(string $name): JsonResponse
    {
        abort_if(Vault::current() === null, 404);

        $manifest = VaultManifest::named($name);

        return response()->json([
            'cipher' => $manifest->cipher,
            'nonce' => $manifest->nonce,
            'version' => (int) $manifest->version,
        ]);
    }

    public function update(Request $request, string $name): JsonResponse
    {
        abort_if(Vault::current() === null, 404);

        $validated = $request->validate([
            'cipher' => ['required', 'string'],
            'nonce' => ['required', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        $manifest = VaultManifest::named($name);

        // Optimistic lock: the writer must have seen the current version.
        if ((int) $validated['version'] !== (int) $manifest->version) {
            return response()->json([
                'message' => 'stale manifest',
                'version' => (int) $manifest->version,
            ], 409);
        }

        $manifest->update([
            'cipher' => $validated['cipher'],
            'nonce' => $validated['nonce'],
            'version' => $manifest->version + 1,
        ]);

        return response()->json(['version' => (int) $manifest->version]);
    }
}
