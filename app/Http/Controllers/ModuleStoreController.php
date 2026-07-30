<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SealedManifestStore;
use App\Models\ModuleStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Per-module sealed store (Store v3 per-module split): GET/PUT /store/{module}.
 * Each module (invoices/health/sharing/explore) has its own opaque ciphertext
 * row so an edit in one never re-seals the others.
 * Same optimistic-concurrency + ETag/304 protocol as the gallery/files stores —
 * shared via SealedManifestStore, with the scope/ETag/key/guard hooks overridden
 * to additionally key on {module}. The server only ever sees ciphertext + a
 * version counter (zero-knowledge).
 */
class ModuleStoreController extends Controller
{
    /** @use SealedManifestStore<ModuleStore> */
    use SealedManifestStore;

    /** The only module keys a client may read/write — an unknown key is a 404. */
    private const MODULES = [
        // notes/todos/bookmarks/contacts migrated to plaintext-relational tables (pivot Etappe 1/2);
        // the password manager (+ its sharded store) was removed entirely.
        'invoices',
        'sharing',
    ];

    private const MAX_BYTES = 67108864; // 64 MiB sealed-index cap (metadata, not blobs)

    protected function manifestModel(): string
    {
        return ModuleStore::class;
    }

    protected function manifestMaxBytes(): int
    {
        return self::MAX_BYTES;
    }

    /** Reject an unknown module key before touching the store (404). */
    protected function guardManifestRequest(Request $request): void
    {
        abort_unless(in_array($request->route('module'), self::MODULES, true), 404);
    }

    /**
     * @param  Builder<ModuleStore>  $query
     * @return Builder<ModuleStore>
     */
    protected function manifestScope(Request $request, Builder $query): Builder
    {
        return $query
            ->where('user_id', (int) $this->requireUser($request)->id)
            ->where('module', (string) $request->route('module'));
    }

    protected function etagSuffix(Request $request): string
    {
        return '-'.(string) $request->route('module');
    }

    /**
     * Forensic trail for module-store writes: every persisted root logs its version +
     * ciphertext sha256 + byte length (blob_audit_log). These stores are blobless (no
     * shards), but the write trail alone lets `blob-audit:show --module <m>` pinpoint an
     * overwrite that dropped records — a ciphertext suddenly SMALLER than a prior
     * version is the smoking gun. ZK-safe: the hash is over ciphertext, sizes are of
     * the padded blob; no plaintext, never the key. (Reads are not logged — volume.)
     */
    protected function manifestAuditModule(Request $request): ?string
    {
        return 'store:'.(string) $request->route('module');
    }

    /**
     * @return array<string, int|string>
     */
    protected function manifestKey(Request $request): array
    {
        return [
            'user_id' => (int) $this->requireUser($request)->id,
            'module' => (string) $request->route('module'),
        ];
    }
}
