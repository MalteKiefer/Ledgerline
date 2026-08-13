<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailAttachment;
use App\Models\MailBlob;
use App\Models\MailMessage;
use App\Support\BlobStore;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reclaim stored raw mail blob bytes (mail/{blob}) whose ownership ledger row
 * (mail_blobs) survives but no archived message references it anymore.
 *
 * MaildirIngestor writes the MailBlob ledger row and the MailMessage row in the
 * SAME transaction, so a mail blob is never durably stored without a matching
 * ledger row. What DOES orphan a mail blob is account deletion: the account's
 * mail_messages rows keep (account_id nullOnDelete) so their blobs stay
 * referenced — but a message that is force-purged (GDPR) or a future hard-delete
 * path can leave a MailBlob row with no MailMessage of the same id. The
 * message's own `id` doubles as the blob's primary key (see MaildirIngestor), so
 * "referenced" means a MailMessage row still exists with that same id.
 *
 * Age-gated by the blob's `created_at` (hour-snapped at write time) so a blob
 * mid-ingest — whose MailMessage row commits in the same transaction a moment
 * later — is never mistaken for an orphan. Scheduled daily.
 */
class SweepOrphanMailBlobs extends Command
{
    protected $signature = 'mail:sweep-orphans';

    protected $description = 'Reclaim stored mail blob bytes on disk that no archived message references';

    public function handle(): int
    {
        $disk = BlobStore::disk();
        $grace = config('mail_archive.blob_orphan_grace_hours', 24);
        $cutoff = Carbon::now()->subHours(is_numeric($grace) ? (int) $grace : 24);

        $swept = 0;

        MailBlob::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk, &$swept): void {
                $ids = $blobs->pluck('blob')->all();

                // A raw-message blob (kind=message) is referenced by a MailMessage
                // of the same id; an attachment blob (kind=attachment) is
                // referenced by a MailAttachment.blob. The on-disk prefix differs
                // (mail/{blob} vs mail/att/{blob}), so resolve both per kind.
                $referencedMessages = MailMessage::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $ids)
                    ->pluck('id')
                    ->flip();
                $referencedAttachments = MailAttachment::query()
                    ->withoutGlobalScopes()
                    ->whereIn('blob', $ids)
                    ->pluck('blob')
                    ->flip();

                $orphanIds = [];
                foreach ($blobs as $blob) {
                    $isAttachment = $blob->kind === 'attachment';
                    $referenced = $isAttachment
                        ? isset($referencedAttachments[$blob->blob])
                        : isset($referencedMessages[$blob->blob]);
                    if ($referenced) {
                        continue;
                    }
                    $orphanIds[] = $blob->blob;

                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete(($isAttachment ? 'mail/att/' : 'mail/').$blob->blob);
                    }
                    $swept++;
                }

                if ($orphanIds !== []) {
                    MailBlob::query()->whereIn('blob', $orphanIds)->delete();
                }
            }, 'blob');

        $this->info("Swept {$swept} unreferenced mail blob(s).");

        return self::SUCCESS;
    }
}
