<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SharedVault;
use App\Models\SharedVaultStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Opaque zero-knowledge sealed manifest store for a shared password-Tresor.
 *
 * The server only ever stores and returns ciphertext + a version counter.
 * No content, structure or item counts are server-visible. The optimistic-
 * concurrency protocol prevents two concurrent writers from silently clobbering
 * each other: the client sends the version it based its edit on; a mismatch
 * means another device wrote in between, so we reject with 409 and return the
 * current state so the client can re-load + re-apply its changes.
 *
 * This mirrors the SealedManifestStore trait used by StoreController and
 * GalleryStoreController but is intentionally NOT implemented via that trait
 * because the shared vault store uses a vault-scoped (vault_id) row rather than
 * a per-user row, and requires vault-level authorization.
 */
class SharedVaultStoreController extends Controller
{
    /** Cap matches StoreController (personal vault manifest, not file bytes). */
    private const MANIFEST_MAX_BYTES = 16_000_000;

    /**
     * Return the vault's current sealed manifest and version.
     *
     * Requires `view` ability on the vault. Sends Cache-Control: no-store to
     * keep the ciphertext off shared caches.
     */
    public function show(Request $request, SharedVault $vault): JsonResponse
    {
        $this->authorize('view', $vault);

        $row = SharedVaultStore::find($vault->id);

        return response()->json([
            'sealed_manifest' => $row?->sealed_manifest,
            'version'         => (int) ($row?->version ?? 0),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Save a new sealed manifest.
     *
     * Requires `update` ability on the vault.
     * Optimistic concurrency: the client sends the version it based its edit on.
     * If the stored version differs, return 409 with the current version and
     * sealed manifest so the client can re-load and re-apply.
     */
    public function save(Request $request, SharedVault $vault): JsonResponse
    {
        $this->authorize('update', $vault);

        $data = $request->validate([
            'sealed_manifest'  => ['required', 'string', 'max:'.self::MANIFEST_MAX_BYTES],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);

        $result = DB::transaction(function () use ($vault, $data): array|null {
            /** @var SharedVaultStore|null $row */
            $row = SharedVaultStore::where('vault_id', $vault->id)
                ->lockForUpdate()
                ->first();

            $current = (int) ($row?->version ?? 0);

            if ($current !== (int) $data['expected_version']) {
                // Version conflict — return current state so the client can merge.
                return [
                    'conflict'        => true,
                    'version'         => $current,
                    'sealed_manifest' => $row?->sealed_manifest,
                ];
            }

            $nextVersion = $current + 1;

            SharedVaultStore::where('vault_id', $vault->id)->update([
                'sealed_manifest' => $data['sealed_manifest'],
                'version'         => $nextVersion,
            ]);

            return ['conflict' => false, 'version' => $nextVersion];
        });

        if ($result['conflict']) {
            return response()->json([
                'version'         => $result['version'],
                'sealed_manifest' => $result['sealed_manifest'],
            ], 409);
        }

        return response()->json(['version' => $result['version']]);
    }
}
