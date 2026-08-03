<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SealedManifestStore;
use App\Models\ContactBlob;
use App\Models\ContactsStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Opaque zero-knowledge contacts index store (store merge-safety spec §3b). The browser
 * seals the contact-record pointer table with the vault key; the server stores only
 * ciphertext + a version counter. Contact records live in the contact_blobs ledger
 * (content-addressed shards, shared with avatar blobs), not here. Show/save (ETag/304 +
 * optimistic-concurrency 409 + shard-ref integrity guard) is the shared
 * SealedManifestStore protocol, identical to the notes/invoices/files/gallery stores.
 */
class ContactsStoreController extends Controller
{
    /** @use SealedManifestStore<ContactsStore> */
    use SealedManifestStore;

    protected function manifestModel(): string
    {
        return ContactsStore::class;
    }

    /** The sealed index blob (shard pointer table), not contact bytes (64 MiB cap). */
    protected function manifestMaxBytes(): int
    {
        return 67108864;
    }

    /**
     * Contact blob ledger (record shards + avatar blobs share it, content-addressed),
     * scoped to the caller — drives the shard-reference integrity guard on save.
     *
     * @return Builder<ContactBlob>
     */
    protected function manifestBlobLedger(Request $request): ?Builder
    {
        return ContactBlob::query()->where('user_id', (int) $this->requireUser($request)->id);
    }

    protected function manifestAuditModule(Request $request): ?string
    {
        return 'contacts';
    }
}
