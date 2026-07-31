<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\StoreHistory;
use App\Support\BlobAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
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
            // Non-secret per-slice record cardinality (how many notes / invoices /
            // receipts …), NOT content. Recorded in the root_write trail so a scan can
            // flag the exact version where a count regressed — the strongest signal for
            // a silently-dropped record. Optional: older clients omit it.
            'counts' => ['sometimes', 'array', 'max:64'],
            'counts.*' => ['integer', 'min:0', 'max:100000000'],
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
            // Forensic trail of the conflict (store merge-safety spec §8): a stale
            // writer was rejected; the client must rebase + retry. Secret-free.
            if ($auditModule !== null) {
                BlobAudit::record('store_conflict', $auditModule, [
                    'user_id' => (int) $this->requireUser($request)->id,
                    'result' => 'conflict',
                    'meta' => ['expected_version' => $expectedVersion],
                ]);
            }

            return response()->json(['error' => 'version_conflict'], 409);
        }

        // Forensic trail of the persisted root: its ciphertext hash + the fingerprint
        // of the exact shard set it points at. Comparing shard_set_sha256 across
        // versions pinpoints the write that added or DROPPED a shard — the single most
        // useful signal when reconstructing a data-loss event.
        if ($auditModule !== null) {
            // Recovery net: retain this sealed root so an earlier version can be
            // pulled back if a later save dropped a record (store:anomaly-scan flags
            // the drop; this makes it undoable). Opaque ciphertext — ZK preserved.
            $this->recordHistory($request, $auditModule, $ciphertext, $next);

            $counts = $this->manifestCounts($request);
            BlobAudit::record('root_write', $auditModule, [
                'user_id' => (int) $this->requireUser($request)->id,
                'sha256' => BlobAudit::hashString($ciphertext),
                'meta' => [
                    'version' => $next,
                    'bytes' => strlen($ciphertext),
                    'shard_count' => $refs?->count(),
                    'shard_set_sha256' => $refs !== null ? BlobAudit::shardSetHash($refs->all()) : null,
                    // P1: record cardinality per slice + the total, so store:anomaly-scan
                    // can pinpoint a version where records vanished.
                    'counts' => $counts,
                    'count_total' => $counts === null ? null : array_sum($counts),
                    // P3: which device wrote this version (from the API token, if any),
                    // so a multiclient loss is attributable to a specific client.
                ] + $this->clientCorrelation($request),
            ]);
        }

        return response()->json(['version' => $next]);
    }

    /**
     * List the retained previous sealed roots for this store (newest first), without
     * the ciphertext — a cheap version index the client uses to pick a version to
     * recover from. Owner-scoped by the manifest scope's user id + the store module.
     */
    public function history(Request $request): JsonResponse
    {
        $this->guardManifestRequest($request);
        $module = $this->manifestAuditModule($request);
        if ($module === null) {
            return response()->json(['versions' => []]);
        }

        $rows = StoreHistory::query()
            ->where('user_id', (int) $this->requireUser($request)->id)
            ->where('module', $module)
            ->orderByDesc('version')
            ->get(['version', 'ciphertext', 'created_at']);

        return response()->json([
            'versions' => $rows->map(static fn (StoreHistory $r): array => [
                'version' => $r->version,
                'bytes' => strlen($r->ciphertext),
                'created_at' => $r->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Return one retained sealed root ciphertext by version so the client can decrypt
     * it and re-merge a lost record. Owner-scoped; 404 if that version isn't retained.
     */
    public function historyVersion(Request $request): JsonResponse
    {
        $this->guardManifestRequest($request);
        // Read `version` by name, not as a positional arg: the module-store route has
        // TWO params ({module}/history/{version}) so a positional $version would get
        // the module. whereNumber on the route guarantees it is numeric.
        $version = (int) $request->route('version');
        $module = $this->manifestAuditModule($request);
        $row = $module === null ? null : StoreHistory::query()
            ->where('user_id', (int) $this->requireUser($request)->id)
            ->where('module', $module)
            ->where('version', $version)
            ->first();

        if ($row === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json([
            'version' => $row->version,
            'ciphertext' => $row->ciphertext,
            'created_at' => $row->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Persist this sealed root as history and prune to the last N versions per
     * (user, module). Best-effort: a history failure must never break the save.
     */
    private function recordHistory(Request $request, string $module, string $ciphertext, int $version): void
    {
        try {
            $uid = (int) $this->requireUser($request)->id;
            StoreHistory::query()->updateOrCreate(
                ['user_id' => $uid, 'module' => $module, 'version' => $version],
                ['ciphertext' => $ciphertext, 'created_at' => now()],
            );

            $keepCfg = config('store.history_versions', 20);
            $keep = is_numeric($keepCfg) ? max(1, (int) $keepCfg) : 20;
            $cutoff = StoreHistory::query()
                ->where('user_id', $uid)
                ->where('module', $module)
                ->orderByDesc('version')
                ->skip($keep)
                ->take(1)
                ->value('version');
            if (is_numeric($cutoff)) {
                StoreHistory::query()
                    ->where('user_id', $uid)
                    ->where('module', $module)
                    ->where('version', '<=', (int) $cutoff)
                    ->delete();
            }
        } catch (\Throwable) {
            // Recovery history is best-effort; never break the audited save.
        }
    }

    /**
     * The client-sent per-slice record counts (non-secret cardinality), or null when
     * the client didn't send them (older client).
     *
     * @return array<string, int>|null
     */
    private function manifestCounts(Request $request): ?array
    {
        if (! $request->has('counts') || ! is_array($request->input('counts'))) {
            return null;
        }
        $out = [];
        foreach ($request->collect('counts') as $k => $v) {
            if (is_string($k) && is_numeric($v)) {
                $out[$k] = (int) $v;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * Non-secret client correlation (install id + app version) from the presenting
     * API token, so a store write is attributable to a specific device. A web-session
     * write has no personal access token → both null.
     *
     * @return array{install_id: ?string, app_version: ?string}
     */
    private function clientCorrelation(Request $request): array
    {
        $token = $request->user()?->currentAccessToken();
        $isPat = $token instanceof PersonalAccessToken;

        return [
            'install_id' => $isPat && is_string($token->install_id) ? $token->install_id : null,
            'app_version' => $isPat && is_string($token->app_version) ? $token->app_version : null,
        ];
    }
}
