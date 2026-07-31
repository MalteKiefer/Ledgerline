<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\ExploreBlob;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for the Explore module's OPAQUE CONTENT BLOBS under
 * zero-knowledge. The Explore structure (tracks, couplings, tolerances) lives
 * sealed inside the `explore` module store, which StoreData already exports and
 * purges via the module_stores rows — this contributor is only for the optional
 * raw track blobs + their ownership ledger (explore_blobs). The export is the
 * ciphertext blob inventory (ids/sizes — no plaintext); purge deletes the user's
 * stored bytes and ledger rows so no orphans remain.
 *
 * Without this contributor a purge relied on the explore_blobs FK cascade, which
 * drops the ledger rows but leaves the ciphertext bytes on disk forever —
 * unreclaimable, since the orphan sweep only reaps bytes older than the grace.
 */
final class ExploreData implements UserDataContributor
{
    public function key(): string
    {
        return 'explore';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $blobs = ExploreBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->get(['blob', 'size', 'created_at'])
            ->map(fn (ExploreBlob $b): array => [
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

        ExploreBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk): void {
                foreach ($blobs as $blob) {
                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete('explore/'.$blob->blob);
                    }
                }

                ExploreBlob::query()
                    ->whereIn('blob', $blobs->modelKeys())
                    ->delete();
            }, 'blob');
    }
}
