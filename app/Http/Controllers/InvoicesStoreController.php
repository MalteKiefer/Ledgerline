<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SealedManifestStore;
use App\Models\InvoiceBlob;
use App\Models\InvoicesStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Opaque zero-knowledge invoices index store (store merge-safety spec §3b). The browser
 * seals the invoice-record pointer table with the vault key; the server stores only
 * ciphertext + a version counter. Note records live in the invoices blob ledger
 * (content-addressed shards), not here. Show/save (ETag/304 + optimistic-concurrency
 * 409 + shard-ref integrity guard) is the shared SealedManifestStore protocol,
 * identical to the files and gallery stores.
 */
class InvoicesStoreController extends Controller
{
    /** @use SealedManifestStore<InvoicesStore> */
    use SealedManifestStore;

    protected function manifestModel(): string
    {
        return InvoicesStore::class;
    }

    /** The sealed index blob (shard pointer table), not invoice bytes (64 MiB cap). */
    protected function manifestMaxBytes(): int
    {
        return 67108864;
    }

    /**
     * Invoices blob ledger (record shards), scoped to the caller — drives the
     * shard-reference integrity guard on save.
     *
     * @return Builder<InvoiceBlob>
     */
    protected function manifestBlobLedger(Request $request): ?Builder
    {
        return InvoiceBlob::query()->where('user_id', (int) $this->requireUser($request)->id);
    }

    protected function manifestAuditModule(Request $request): ?string
    {
        return 'invoices';
    }
}
