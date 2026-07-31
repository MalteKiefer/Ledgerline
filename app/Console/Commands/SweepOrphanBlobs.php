<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\BlobAudit;
use App\Support\BlobStore;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Crash-safety sweep for a zero-knowledge blob store. The client reclaims blobs
 * its (sealed) manifest no longer references via the module's /blobs/reconcile;
 * this command handles the one case the client cannot: stored bytes on disk with
 * NO ledger row at all — e.g. bytes leaked by a crash between a committed delete
 * and the post-unlink, or an aborted multipart upload. Age-gated by lastModified
 * so an in-flight upload (whose ledger row may not exist yet) is never touched.
 * The server cannot read the manifest, so it never removes a still-referenced
 * blob here — only bytes with no ownership record are swept.
 *
 * Concrete per-module commands (files:sweep-orphans, gallery:sweep-orphans) only
 * supply the disk prefix and ownership-ledger model; the sweep body is shared.
 */
abstract class SweepOrphanBlobs extends Command
{
    /** Disk prefix the module stores its content blobs under (e.g. 'files'). */
    abstract protected function prefix(): string;

    /** Fully-qualified ownership-ledger model (FileBlob / GalleryBlob). */
    abstract protected function blobModel(): string;

    /** Config namespace holding blob_orphan_grace_hours (e.g. 'files'). */
    abstract protected function configNs(): string;

    public function handle(): int
    {
        $disk = BlobStore::disk();
        $grace = config($this->configNs().'.blob_orphan_grace_hours', 24);
        $cutoff = Carbon::now()->subHours(is_numeric($grace) ? (int) $grace : 24);

        // Every blob with an ownership record is legitimately referenced (or a
        // fresh upload); only disk bytes with no record at all are candidates.
        /** @var class-string<Model> $model */
        $model = $this->blobModel();
        $known = $model::query()->pluck('blob')->filter()->flip();

        $swept = 0;
        foreach ($disk->files($this->prefix()) as $path) {
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
            BlobAudit::record('sweep_delete', $this->configNs(), [
                'blob' => $blob,
                'source' => 'command',
                'reason' => 'orphan_sweep',
            ]);
        }

        $this->info("Swept {$swept} unreferenced blob file(s).");

        return self::SUCCESS;
    }
}
