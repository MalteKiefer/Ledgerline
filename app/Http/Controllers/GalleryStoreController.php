<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GalleryStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Opaque zero-knowledge gallery index store: the whole photo/album/people
 * structure the browser seals with the vault key. The server only ever stores
 * and returns ciphertext + a version counter — no photo bytes, names, EXIF, GPS
 * or embeddings. The sealed blob is size-padded (see vault.js sealManifest), so
 * this store alone reveals no counts. (Residual structural metadata — photo
 * count, media type, face count — is inferable only from the separate content-
 * blob ledger, see GalleryBlobController.)
 */
class GalleryStoreController extends Controller
{
    /** Return the current user's sealed gallery index + version (empty on first use). */
    public function show(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $row = GalleryStore::query()->where('user_id', $uid)->first();

        return response()->json([
            'ciphertext' => $row?->ciphertext,
            'version' => (int) ($row?->version ?? 0),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Replace the sealed index. Optimistic concurrency: the client sends the
     * version it based its edit on; a mismatch means another tab/device wrote in
     * between (409) and the client must reload + re-apply.
     */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Opaque ciphertext — cap generously (index blob, not photo bytes).
            'ciphertext' => ['required', 'string', 'max:67108864'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        $uid = $request->user()->id;

        $next = DB::transaction(function () use ($uid, $data): ?int {
            $row = GalleryStore::query()->where('user_id', $uid)->lockForUpdate()->first();
            $current = (int) ($row?->version ?? 0);
            if ($current !== (int) $data['version']) {
                return null;
            }
            $version = $current + 1;
            GalleryStore::query()->updateOrCreate(
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
