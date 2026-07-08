<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\VaultStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The opaque zero-knowledge store API. The whole workspace lives in one sealed
 * manifest the browser encrypts with the vault key; the server only ever stores
 * and returns ciphertext + a version counter. No content, structure, counts or
 * flags are server-visible.
 */
class StoreController extends Controller
{
    /** Return the current user's sealed manifest + version (empty on first use). */
    public function show(Request $request): JsonResponse
    {
        $row = VaultStore::query()->find($request->user()->id);

        return response()->json([
            'ciphertext' => $row?->ciphertext,
            'version' => (int) ($row?->version ?? 0),
        ]);
    }

    /**
     * Replace the sealed manifest. Optimistic concurrency: the client sends the
     * version it based its edit on; a mismatch means another tab/device wrote in
     * between, so we reject with 409 and the client re-loads + re-applies.
     */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Opaque ciphertext — cap generously (metadata manifest, not file bytes).
            'ciphertext' => ['required', 'string', 'max:16000000'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        $uid = (int) $request->user()->id;

        $next = DB::transaction(function () use ($uid, $data): ?int {
            $row = VaultStore::query()->lockForUpdate()->find($uid);
            $current = (int) ($row?->version ?? 0);
            if ($current !== (int) $data['version']) {
                return null; // conflict
            }
            $version = $current + 1;
            VaultStore::query()->updateOrCreate(
                ['user_id' => $uid],
                ['ciphertext' => $data['ciphertext'], 'version' => $version],
            );

            return $version;
        });

        if ($next === null) {
            return response()->json(['error' => 'version_conflict'], 409);
        }

        return response()->json(['version' => $next]);
    }
}
