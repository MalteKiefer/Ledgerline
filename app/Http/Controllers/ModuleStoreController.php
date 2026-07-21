<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SealedManifestStore;
use App\Models\ModuleStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Per-module sealed store (Store v3 per-module split): GET/PUT /store/{module}.
 * Each module (notes/todos/bookmarks/contacts/invoices/passwords/health/sharing)
 * has its own opaque ciphertext row so an edit in one never re-seals the others.
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
        'notes', 'todos', 'bookmarks', 'contacts', 'invoices',
        'passwords', 'health', 'sharing',
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
