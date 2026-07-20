<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileBlob;
use App\Models\SharedFolderBlob;
use App\Models\SharedVault;
use Illuminate\Http\Request;

/**
 * Member-scoped blob store for a shared folder. Blobs live at
 * shared-folders/{blob}; access is by vault membership (SharedVaultPolicy),
 * NOT personal ownership. Read requires view (viewer+); upload/delete require
 * update (editor+). Quota is attributed to the folder owner (owner_id) and
 * enforced against the owner's personal files quota. Zero-knowledge: bytes are
 * client ciphertext encrypted under the folder vault key.
 */
class SharedFolderBlobController extends BlobStoreController
{
    protected function blobModel(): string
    {
        return SharedFolderBlob::class;
    }

    protected function module(): string
    {
        return 'shared-folders';
    }

    protected function maxUploadMb(): int
    {
        return (int) config('shared-folders.max_upload_mb', 2048);
    }

    /** The vault from the bound route, gated for the required ability. */
    private function vault(Request $request, string $ability): SharedVault
    {
        $vaultId = $request->route('vault');
        // Resolve the model when implicit binding hasn't run (e.g. the controller
        // action only declares Request, not SharedVault $vault, so Laravel does not
        // auto-bind). A missing vault is treated as a policy denial — 404.
        $vault = $vaultId instanceof SharedVault
            ? $vaultId
            : SharedVault::find($vaultId);

        abort_if($vault === null, 404);
        $this->authorize($ability, $vault); // denyAsNotFound → 404 for non-members

        return $vault;
    }

    // Quota + ledger owner = the FOLDER OWNER, never the uploader.
    protected function ownerId(Request $request): int
    {
        return (int) $this->vault($request, 'update')->owner_id;
    }

    protected function chunkOwnerId(Request $request): int
    {
        return (int) $this->vault($request, 'update')->owner_id;
    }

    protected function stampAttributes(Request $request): array
    {
        $vault = $this->vault($request, 'update');

        return ['vault_id' => $vault->id, 'owner_id' => (int) $vault->owner_id];
    }

    // Read/usage/reconcile scope: this vault's blobs (any active member ≥ viewer).
    protected function scopeLedger(Request $request)
    {
        $vault = $this->vault($request, 'view');

        return SharedFolderBlob::where('vault_id', $vault->id);
    }

    protected function authorizeRaw(Request $request, string $blob): void
    {
        $this->vault($request, 'view');
    }

    protected function authorizeMutation(Request $request): void
    {
        $this->vault($request, 'update');
    }

    /**
     * Owner-attributed usage: the folder owner's personal file bytes PLUS all
     * shared-folder bytes they own, checked against the owner's files quota.
     */
    protected function usedBytes(int $userId): int
    {
        return (int) FileBlob::where('user_id', $userId)->sum('size')
            + (int) SharedFolderBlob::where('owner_id', $userId)->sum('size');
    }

    protected function usedBytesFor(Request $request): int
    {
        return $this->usedBytes($this->ownerId($request));
    }

    protected function quotaBytes(): int
    {
        // Attributed to the owner's personal files quota.
        return (int) config('files.quota_mb', 0) * 1024 * 1024;
    }

    /**
     * Thread the vault_id into the chunk session so chunkComplete can stamp it
     * without re-resolving the route-bound vault from the stale cached session.
     *
     * @return array<string, mixed>
     */
    protected function chunkSessionExtra(Request $request): array
    {
        return ['vault_id' => $this->vault($request, 'update')->id];
    }

    /**
     * Vault-scoped chunk-completion stamping (owner + vault_id). SharedFolderBlob
     * has no user_id column — it uses vault_id + owner_id instead.
     *
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    protected function chunkLedgerAttributes(array $session): array
    {
        return ['vault_id' => $session['vault_id'] ?? null, 'owner_id' => $session['owner'] ?? null];
    }
}
