<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SealedManifestStore;
use App\Models\NoteBlob;
use App\Models\NotesStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Opaque zero-knowledge notes index store (store merge-safety spec §3b). The browser
 * seals the note-record pointer table with the vault key; the server stores only
 * ciphertext + a version counter. Note records live in the notes blob ledger
 * (content-addressed shards), not here. Show/save (ETag/304 + optimistic-concurrency
 * 409 + shard-ref integrity guard) is the shared SealedManifestStore protocol,
 * identical to the files and gallery stores.
 */
class NotesStoreController extends Controller
{
    /** @use SealedManifestStore<NotesStore> */
    use SealedManifestStore;

    protected function manifestModel(): string
    {
        return NotesStore::class;
    }

    /** The sealed index blob (shard pointer table), not note bytes (64 MiB cap). */
    protected function manifestMaxBytes(): int
    {
        return 67108864;
    }

    /**
     * Notes blob ledger (record shards), scoped to the caller — drives the
     * shard-reference integrity guard on save.
     *
     * @return Builder<NoteBlob>
     */
    protected function manifestBlobLedger(Request $request): ?Builder
    {
        return NoteBlob::query()->where('user_id', (int) $this->requireUser($request)->id);
    }

    protected function manifestAuditModule(Request $request): ?string
    {
        return 'notes';
    }
}
