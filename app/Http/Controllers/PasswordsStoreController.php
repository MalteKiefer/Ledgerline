<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SealedManifestStore;
use App\Models\PasswordBlob;
use App\Models\PasswordsStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Opaque zero-knowledge passwords index store (merge-safety spec §3b). The browser
 * (and the extension) seal the secret/folder pointer table with the vault key; the
 * server stores only ciphertext + a version. Secret records live in the passwords
 * shard blobs. Show/save (ETag/304 + 409 + shard-ref integrity guard) is the shared
 * SealedManifestStore protocol, identical to files/gallery/notes.
 */
class PasswordsStoreController extends Controller
{
    /** @use SealedManifestStore<PasswordsStore> */
    use SealedManifestStore;

    protected function manifestModel(): string
    {
        return PasswordsStore::class;
    }

    protected function manifestMaxBytes(): int
    {
        return 67108864;
    }

    /**
     * @return Builder<PasswordBlob>
     */
    protected function manifestBlobLedger(Request $request): ?Builder
    {
        return PasswordBlob::query()->where('user_id', (int) $this->requireUser($request)->id);
    }

    protected function manifestAuditModule(Request $request): ?string
    {
        return 'passwords';
    }
}
