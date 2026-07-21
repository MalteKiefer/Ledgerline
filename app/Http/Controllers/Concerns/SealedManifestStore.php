<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared show/save for an opaque zero-knowledge manifest store — one sealed
 * ciphertext blob per scope with an optimistic-concurrency version counter. The
 * files store, the gallery index store and the per-module store (module_stores)
 * are byte-for-byte the same protocol; a using controller only supplies its model
 * and the ciphertext cap, and may override the scope/ETag/key hooks (e.g. the
 * per-module store additionally keys on `module`). The server never sees anything
 * but ciphertext + version.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
trait SealedManifestStore
{
    /**
     * Fully-qualified per-scope manifest model (GalleryStore / FilesStore / ModuleStore).
     *
     * @return class-string<TModel>
     */
    abstract protected function manifestModel(): string;

    /** Upper bound on the sealed ciphertext (manifest metadata, not file bytes). */
    abstract protected function manifestMaxBytes(): int;

    /**
     * Scope the manifest query to the current caller's row(s). Default: the
     * per-user store (one row per user). Override to add extra key columns
     * (e.g. the per-module store adds `->where('module', $module)`).
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function manifestScope(Request $request, Builder $query): Builder
    {
        return $query->where('user_id', (int) $this->requireUser($request)->id);
    }

    /**
     * Extra dash-prefixed component inserted into the ETag between the user id and
     * the version (default: none). The per-module store returns "-{module}" so the
     * ETag stays `W/"{uid}-{module}-{version}"`.
     */
    protected function etagSuffix(Request $request): string
    {
        return '';
    }

    /**
     * Composite key columns for updateOrCreate (default: just user_id). Override to
     * include additional key columns (e.g. the per-module store adds `module`).
     *
     * @return array<string, int|string>
     */
    protected function manifestKey(Request $request): array
    {
        return ['user_id' => (int) $this->requireUser($request)->id];
    }

    /**
     * Hook run at the start of show/save before any work, for request validation
     * that must short-circuit (e.g. the per-module store aborts 404 on an unknown
     * module key). Default: no-op.
     */
    protected function guardManifestRequest(Request $request): void {}

    /**
     * Return the caller's sealed manifest + version (empty on first use).
     *
     * Store v3 (§10.4/A4): a weak, version-derived ETag lets the client send
     * `If-None-Match` and get a bodyless 304 when the root is unchanged — avoiding
     * re-transferring a large sealed root on repeat opens. The ciphertext is
     * opaque, so revalidation caching is ZK-safe; `private, must-revalidate` keeps
     * it off shared caches while still allowing the 304 round-trip.
     */
    public function show(Request $request): Response
    {
        $this->guardManifestRequest($request);

        $user = $this->requireUser($request);
        $model = $this->manifestModel();
        $row = $this->manifestScope($request, $model::query())->first();

        $version = (int) ($row?->version ?? 0);
        $etag = sprintf('W/"%d%s-%d"', (int) $user->id, $this->etagSuffix($request), $version);

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, must-revalidate');
        }

        return response()->json([
            'ciphertext' => $row?->ciphertext,
            'version' => $version,
        ])
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, must-revalidate');
    }

    /**
     * Replace the sealed manifest. Optimistic concurrency: the client sends the
     * version it based its edit on; a mismatch means another tab/device wrote in
     * between, so we reject with 409 and the client re-loads + re-applies.
     */
    public function save(Request $request): JsonResponse
    {
        $this->guardManifestRequest($request);

        $data = $request->validate([
            'ciphertext' => ['required', 'string', 'max:'.$this->manifestMaxBytes()],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        $model = $this->manifestModel();
        $key = $this->manifestKey($request);

        $next = DB::transaction(function () use ($request, $data, $model, $key): ?int {
            $row = $this->manifestScope($request, $model::query())->lockForUpdate()->first();
            $current = (int) ($row?->version ?? 0);
            if ($current !== (int) $data['version']) {
                return null; // conflict
            }
            $version = $current + 1;
            $model::query()->updateOrCreate(
                $key,
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
