<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\NoteBlob;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for the sharded notes store (store merge-safety spec §3b),
 * mirroring FilesData. The note records live sealed inside the notes shard blobs; the
 * server-side state is those opaque blobs + their ownership ledger (notes_blobs). The
 * export is the ciphertext blob inventory (ids/sizes — no plaintext); purge deletes the
 * stored bytes + ledger rows so no orphans remain (the notes_store root row cascades
 * on user delete). The old single-blob module_stores notes row is handled by StoreData.
 */
final class NotesData implements UserDataContributor
{
    public function key(): string
    {
        return 'notes_shards';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $blobs = NoteBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->get(['blob', 'size', 'created_at'])
            ->map(fn (NoteBlob $b): array => [
                'blob' => $b->blob,
                'size' => $b->size,
                'created_at' => $b->created_at,
            ])
            ->all();

        return ['blobs' => $blobs];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();

        NoteBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk): void {
                foreach ($blobs as $blob) {
                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete('notes/'.$blob->blob);
                    }
                }
                NoteBlob::query()->whereIn('blob', $blobs->modelKeys())->delete();
            }, 'blob');
    }
}
