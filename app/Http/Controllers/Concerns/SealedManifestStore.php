<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Support\BlobAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
     * Blob ledger backing a SHARDED store, scoped to the caller, or null for a
     * store with no content blobs (the per-module store). When non-null, a save may
     * carry a `shards` array — the blob refs the new sealed root points at — and the
     * server verifies every one exists in this ledger before persisting the root.
     *
     * This is the referential-integrity guard that makes the sharded-store data-loss
     * bug impossible from ANY client: a root that references a shard whose blob never
     * durably landed (a partial/racy save) is REJECTED (422), so the store never ends
     * up pointing at a missing shard — which is what corrupted the gallery index and
     * blanked the library. The refs are blob UUIDs, already non-secret (they appear in
     * the ledger and in /raw URLs), so sending them reveals nothing about content — ZK
     * is preserved. The check reads the ledger ROW (created synchronously on upload),
     * not the object-store bytes, so it is immune to eventual-consistency false-404s.
     *
     * @return Builder<Model>|null
     */
    protected function manifestBlobLedger(Request $request): ?Builder
    {
        return null;
    }

    /**
     * Module label for the blob forensic trail (root_write / root_reject events), or
     * null to skip auditing this store (the blobless per-module store). Sharded
     * stores return 'gallery' / 'files'.
     */
    protected function manifestAuditModule(Request $request): ?string
    {
        return null;
    }

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

        $request->validate([
            'ciphertext' => ['required', 'string', 'max:'.$this->manifestMaxBytes()],
            'version' => ['required', 'integer', 'min:0'],
            'shards' => ['sometimes', 'array', 'max:200000'],
            'shards.*' => ['uuid'],
        ]);

        $auditModule = $this->manifestAuditModule($request);
        $expectedVersion = $request->integer('version');

        // Referential-integrity guard for sharded stores: reject a root that points
        // at a shard blob with no ledger row (a partial/racy save), so the store can
        // never be persisted in the corrupt "dangling shard" state that lost data.
        $ledger = $this->manifestBlobLedger($request);
        $refs = null;
        if ($request->has('shards')) {
            $refs = $request->collect('shards')
                ->map(static fn ($s): string => is_scalar($s) ? (string) $s : '')
                ->filter()
                ->unique()
                ->values();
            if ($ledger !== null && $refs->isNotEmpty()) {
                $present = (clone $ledger)->whereIn('blob', $refs->all())->pluck('blob');
                if ($present->count() < $refs->count()) {
                    if ($auditModule !== null) {
                        BlobAudit::record('root_reject', $auditModule, [
                            'user_id' => (int) $this->requireUser($request)->id,
                            'result' => 'rejected',
                            'reason' => 'missing_shard',
                            'meta' => [
                                'version' => $expectedVersion,
                                'shard_count' => $refs->count(),
                                'missing' => $refs->reject(static fn (string $r): bool => $present->contains($r))->values()->all(),
                            ],
                        ]);
                    }

                    return response()->json(['error' => 'missing_shard'], 422);
                }
            }
        }

        $ciphertext = $request->string('ciphertext')->value();

        $model = $this->manifestModel();
        $key = $this->manifestKey($request);

        $next = DB::transaction(function () use ($request, $ciphertext, $expectedVersion, $model, $key): ?int {
            $row = $this->manifestScope($request, $model::query())->lockForUpdate()->first();
            $current = (int) ($row?->version ?? 0);
            if ($current !== $expectedVersion) {
                return null; // conflict
            }
            $version = $current + 1;
            $model::query()->updateOrCreate(
                $key,
                ['ciphertext' => $ciphertext, 'version' => $version],
            );

            return $version;
        });

        if ($next === null) {
            return response()->json(['error' => 'version_conflict'], 409);
        }

        // Forensic trail of the persisted root: its ciphertext hash + the fingerprint
        // of the exact shard set it points at. Comparing shard_set_sha256 across
        // versions pinpoints the write that added or DROPPED a shard — the single most
        // useful signal when reconstructing a data-loss event.
        if ($auditModule !== null) {
            BlobAudit::record('root_write', $auditModule, [
                'user_id' => (int) $this->requireUser($request)->id,
                'sha256' => BlobAudit::hashString($ciphertext),
                'meta' => [
                    'version' => $next,
                    'bytes' => strlen($ciphertext),
                    'shard_count' => $refs?->count(),
                    'shard_set_sha256' => $refs !== null ? BlobAudit::shardSetHash($refs->all()) : null,
                ],
            ]);
        }

        return response()->json(['version' => $next]);
    }
}
