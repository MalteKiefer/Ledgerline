<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\MailBlob;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for the mail archive's OPAQUE sealed message blobs
 * (mail/{blob}) — mirrors ContactsData/InvoicesData exactly. The message
 * METADATA (mail_accounts/mail_sync_state/mail_messages) already cascades on
 * `users.id` via FK (cascadeOnDelete, see the 2026_12_03_100000 migration), so
 * PurgeUserAccount's final `$user->delete()` removes those rows on its own.
 *
 * What does NOT cascade is the sealed RFC822 blob BYTES on disk, and — because
 * `mail_blobs.user_id` cascades with the USER but `mail_messages` also cascades
 * independently with the ACCOUNT (deleting one account leaves the user's other
 * mail_blobs rows alone, but a message row referencing a now-deleted account's
 * blob is gone while the blob ledger row survives) — this contributor is the
 * one place that promptly frees the actual ciphertext bytes + their ownership
 * ledger row (mail_blobs) for account erasure, exactly like every other
 * blob-backed module. Without it, GDPR export omits the blob inventory and
 * account erasure leaves ciphertext bytes on disk until the next
 * mail:sweep-orphans grace window.
 */
final class MailData implements UserDataContributor
{
    public function key(): string
    {
        return 'mail';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $blobs = MailBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->get(['blob', 'size', 'created_at'])
            ->map(fn (MailBlob $b): array => [
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

        MailBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk): void {
                foreach ($blobs as $blob) {
                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete('mail/'.$blob->blob);
                    }
                }

                MailBlob::query()
                    ->whereIn('blob', $blobs->modelKeys())
                    ->delete();
            }, 'blob');
    }
}
