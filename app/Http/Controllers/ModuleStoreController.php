<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ModuleStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-module sealed store (Store v3 per-module split): GET/PUT /store/{module}.
 * Each module (notes/todos/bookmarks/contacts/invoices/passwords/health/sharing)
 * has its own opaque ciphertext row so an edit in one never re-seals the others.
 * Same optimistic-concurrency + ETag/304 protocol as the gallery/files stores;
 * the server only ever sees ciphertext + a version counter (zero-knowledge).
 */
class ModuleStoreController extends Controller
{
    /** The only module keys a client may read/write — an unknown key is a 404. */
    private const MODULES = [
        'notes', 'todos', 'bookmarks', 'contacts', 'invoices',
        'passwords', 'health', 'sharing',
    ];

    private const MAX_BYTES = 67108864; // 64 MiB sealed-index cap (metadata, not blobs)

    public function show(Request $request, string $module): Response
    {
        abort_unless(in_array($module, self::MODULES, true), 404);
        $uid = (int) $this->requireUser($request)->id;

        $row = ModuleStore::query()->where('user_id', $uid)->where('module', $module)->first();
        $version = (int) ($row?->version ?? 0);
        $etag = sprintf('W/"%d-%s-%d"', $uid, $module, $version);

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304)->header('ETag', $etag)->header('Cache-Control', 'private, must-revalidate');
        }

        return response()->json([
            'ciphertext' => $row?->ciphertext,
            'version' => $version,
        ])->header('ETag', $etag)->header('Cache-Control', 'private, must-revalidate');
    }

    public function save(Request $request, string $module): Response
    {
        abort_unless(in_array($module, self::MODULES, true), 404);
        $data = $request->validate([
            'ciphertext' => ['required', 'string', 'max:'.self::MAX_BYTES],
            'version' => ['required', 'integer', 'min:0'],
        ]);
        $uid = (int) $this->requireUser($request)->id;

        $next = DB::transaction(function () use ($uid, $module, $data): ?int {
            $row = ModuleStore::query()
                ->where('user_id', $uid)->where('module', $module)
                ->lockForUpdate()->first();
            $current = (int) ($row?->version ?? 0);
            if ($current !== (int) $data['version']) {
                return null; // conflict
            }
            $version = $current + 1;
            ModuleStore::query()->updateOrCreate(
                ['user_id' => $uid, 'module' => $module],
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
