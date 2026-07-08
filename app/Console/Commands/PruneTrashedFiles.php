<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileBlob;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Support\BlobStore;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Permanently purges files trashed longer than the retention window (blob +
 * versions + row) and reclaims orphaned upload blobs (uploaded but never synced
 * into a file). A blob is only deleted from disk once NOTHING else references it
 * — the client may point several rows/versions at the same blob, so a blind
 * delete could destroy bytes still in use. Scheduled daily; runs globally.
 */
class PruneTrashedFiles extends Command
{
    protected $signature = 'files:prune-trash';

    protected $description = 'Permanently delete files trashed longer than the retention window and reclaim orphan blobs';

    public function handle(): int
    {
        $disk = BlobStore::disk();
        $cutoff = Carbon::now()->subDays((int) config('files.trash_retention_days', 30));

        $count = 0;
        StoredFile::withoutGlobalScopes()
            ->onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->chunkById(200, function ($files) use ($disk, &$count): void {
                foreach ($files as $file) {
                    $blobs = FileVersion::where('file_id', $file->id)->pluck('blob')->all();
                    $blobs[] = $file->blob;

                    FileVersion::where('file_id', $file->id)->delete();
                    $file->forceDelete();

                    foreach (array_unique(array_filter($blobs)) as $blob) {
                        $this->deleteBlobIfUnreferenced($disk, (string) $blob);
                    }
                    $count++;
                }
            });

        // Reclaim blobs uploaded but never attached to a file (abandoned uploads).
        $orphanCutoff = Carbon::now()->subHours((int) config('files.blob_orphan_grace_hours', 24));
        $orphans = 0;
        FileBlob::where('created_at', '<', $orphanCutoff)->chunkById(200, function ($rows) use ($disk, &$orphans): void {
            foreach ($rows as $row) {
                if (! StoredFile::withoutGlobalScopes()->withTrashed()->where('blob', $row->blob)->exists()) {
                    $disk->delete('files/'.$row->blob);
                    $orphans++;
                }
                $row->delete();
            }
        }, 'blob');

        // True-orphan disk sweep: reclaim blob files on disk that NOTHING in the
        // database references and that have no upload record — e.g. bytes leaked
        // by a crash between a committed delete and the post-commit unlink.
        // Age-gated by lastModified so an in-flight upload (whose row/FileBlob may
        // not exist yet) is never touched.
        $referenced = StoredFile::withoutGlobalScopes()->withTrashed()->pluck('blob')
            ->merge(FileVersion::pluck('blob'))
            ->merge(FileBlob::pluck('blob'))
            ->filter()->flip();
        $swept = 0;
        foreach ($disk->files('files') as $path) {
            $blob = basename($path);
            if (! Str::isUuid($blob) || isset($referenced[$blob])) {
                continue;
            }
            try {
                if ($disk->lastModified($path) > $orphanCutoff->getTimestamp()) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }
            $disk->delete($path);
            $disk->delete('thumbs/'.$blob.'.jpg');
            $swept++;
        }

        $this->info("Purged {$count} trashed file(s); reclaimed {$orphans} orphan blob(s); swept {$swept} unreferenced blob file(s).");

        return self::SUCCESS;
    }

    /** Delete a blob from disk only if no live file or version still references it. */
    private function deleteBlobIfUnreferenced(Filesystem $disk, string $blob): void
    {
        if ($blob === '') {
            return;
        }
        $stillUsed = StoredFile::withoutGlobalScopes()->withTrashed()->where('blob', $blob)->exists()
            || FileVersion::where('blob', $blob)->exists();
        if (! $stillUsed) {
            $disk->delete('files/'.$blob);
        }
    }
}
