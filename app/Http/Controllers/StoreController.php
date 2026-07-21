<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SealedManifestStore;
use App\Models\VaultStore;

/**
 * The opaque zero-knowledge store API. The whole workspace lives in one sealed
 * manifest the browser encrypts with the vault key; the server only ever stores
 * and returns ciphertext + a version counter. No content, structure, counts or
 * flags are server-visible. The show/save protocol lives in SealedManifestStore.
 */
class StoreController extends Controller
{
    use SealedManifestStore;

    protected function manifestModel(): string
    {
        return VaultStore::class;
    }

    /** Cap generously — this is the metadata manifest, not file bytes. */
    protected function manifestMaxBytes(): int
    {
        $max = config('vault.manifest_max_bytes');

        return is_numeric($max) ? (int) $max : 0;
    }
}
