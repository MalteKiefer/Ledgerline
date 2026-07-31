<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\ContactBlob;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for the Contacts module's OPAQUE AVATAR BLOBS
 * (contacts/{blob}) under zero-knowledge. The contact RECORDS live sealed in the
 * `contacts` module store, which StoreData already exports and purges via the
 * module_stores rows — this contributor is only for the optional avatar image
 * blobs + their ownership ledger (contact_blobs).
 *
 * Without it, an account purge relied on the contact_blobs FK cascade, which drops
 * the ledger ROWS but leaves the ciphertext BYTES on disk (reclaimed only later by
 * the daily contacts:sweep-orphans after the grace) and the GDPR export omitted the
 * avatar blob inventory — the same gap the other seven blob modules each close.
 */
final class ContactsData implements UserDataContributor
{
    public function key(): string
    {
        return 'contacts';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $blobs = ContactBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->get(['blob', 'size', 'created_at'])
            ->map(fn (ContactBlob $b): array => [
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

        ContactBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk): void {
                foreach ($blobs as $blob) {
                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete('contacts/'.$blob->blob);
                    }
                }

                ContactBlob::query()
                    ->whereIn('blob', $blobs->modelKeys())
                    ->delete();
            }, 'blob');
    }
}
