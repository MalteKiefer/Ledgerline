<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
                $referenced = MailMessage::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $ids)
                    ->pluck('id')
                    ->flip();

                $orphanIds = [];
                foreach ($blobs as $blob) {
                    if (isset($referenced[$blob->blob])) {
                        continue;
                    }
                    $orphanIds[] = $blob->blob;

                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete('mail/'.$blob->blob);
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
