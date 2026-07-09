<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileBlob;
use App\Support\BlobStore;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Crash-safety sweep for the zero-knowledge file store. The client reclaims
 * blobs its (sealed) manifest no longer references via /files/blobs/reconcile;
 * this command handles the one case the client cannot: stored bytes on disk with
 * NO ledger row at all — e.g. bytes leaked by a crash between a committed delete
 * and the post-unlink, or an aborted multipart upload. Age-gated by lastModified
 * so an in-flight upload (whose FileBlob row may not exist yet) is never touched.
 * The server cannot read the manifest, so it never removes a still-referenced
 * blob here — only bytes with no ownership record are swept. Scheduled daily.
 */
class SweepOrphanBlobs extends Command
{
    protected $signature = 'files:sweep-orphans';

    protected $description = 'Reclaim stored file bytes on disk that have no ownership ledger row (leaked/aborted uploads)';

    public function handle(): int
    {
        $disk = BlobStore::disk();
        $cutoff = Carbon::now()->subHours((int) config('files.blob_orphan_grace_hours', 24));

        // Every blob with an ownership record is legitimately referenced (or a
        // fresh upload); only disk bytes with no record at all are candidates.
        $known = FileBlob::query()->pluck('blob')->filter()->flip();

        $swept = 0;
        foreach ($disk->files('files') as $path) {
            $blob = basename($path);
            if (! Str::isUuid($blob) || isset($known[$blob])) {
                continue;
            }
            try {
                if ($disk->lastModified($path) > $cutoff->getTimestamp()) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }
            $disk->delete($path);
            $disk->delete('thumbs/'.$blob.'.jpg');
            $swept++;
        }

        $this->info("Swept {$swept} unreferenced blob file(s).");

        return self::SUCCESS;
    }
}
