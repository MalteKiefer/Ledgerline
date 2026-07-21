<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared show/save for an opaque zero-knowledge manifest store — one sealed
 * ciphertext blob per user with an optimistic-concurrency version counter. The
 * workspace store (notes/bookmarks/todos/files tree) and the gallery index store
 * are byte-for-byte the same protocol; a using controller only supplies its model
 * and the ciphertext cap. The server never sees anything but ciphertext + version.
 */
trait SealedManifestStore
{
    /** Fully-qualified per-user manifest model (VaultStore / GalleryStore). */
    abstract protected function manifestModel(): string;

    /** Upper bound on the sealed ciphertext (manifest metadata, not file bytes). */
    abstract protected function manifestMaxBytes(): int;

    /** Return the current user's sealed manifest + version (empty on first use). */
    public function show(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $model = $this->manifestModel();
        $row = $model::query()->where('user_id', $user->id)->first();

        // Never let a proxy/browser cache the sealed manifest (defence-in-depth:
        // keep the ciphertext off shared caches entirely).
        return response()->json([
            'ciphertext' => $row?->ciphertext,
            'version' => (int) ($row?->version ?? 0),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Replace the sealed manifest. Optimistic concurrency: the client sends the
     * version it based its edit on; a mismatch means another tab/device wrote in
     * between, so we reject with 409 and the client re-loads + re-applies.
     */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ciphertext' => ['required', 'string', 'max:'.$this->manifestMaxBytes()],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        $uid = (int) $this->requireUser($request)->id;
        $model = $this->manifestModel();

        $next = DB::transaction(function () use ($uid, $data, $model): ?int {
            $row = $model::query()->where('user_id', $uid)->lockForUpdate()->first();
            $current = (int) ($row?->version ?? 0);
            if ($current !== (int) $data['version']) {
                return null; // conflict
            }
            $version = $current + 1;
            $model::query()->updateOrCreate(
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
