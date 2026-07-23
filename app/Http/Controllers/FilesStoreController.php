<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SealedManifestStore;
use App\Models\FileBlob;
use App\Models\FilesStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Opaque zero-knowledge files index store (Store v3 §4.2/§13-A10b). The browser
 * seals the folder tree + file-record pointer table with the vault key; the server
 * only stores ciphertext + a version counter. Heavy file records live in the files
 * blob ledger (content-addressed shards), not here. Show/save (with ETag/304 +
 * optimistic-concurrency 409) is the shared SealedManifestStore protocol, identical
 * to the gallery and workspace stores.
 */
class FilesStoreController extends Controller
{
    /** @use SealedManifestStore<FilesStore> */
    use SealedManifestStore;

    protected function manifestModel(): string
    {
        return FilesStore::class;
    }

    /** Cap generously — this is the sealed index blob, not file bytes (64 MiB). */
    protected function manifestMaxBytes(): int
    {
        return 67108864;
    }

    /**
     * Files blob ledger (record shards + file-content blobs), scoped to the caller —
     * drives the shard-reference integrity guard on save.
     *
     * @return Builder<FileBlob>
     */
    protected function manifestBlobLedger(Request $request): ?Builder
    {
        return FileBlob::query()->where('user_id', (int) $this->requireUser($request)->id);
    }
}
