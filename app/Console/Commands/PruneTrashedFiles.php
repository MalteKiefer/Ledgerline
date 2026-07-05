<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StoredFile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently purges files that have been in the trash (soft-deleted) longer
 * than the retention window. For each file we delete its blob from disk, any
 * file_versions rows for it (plus their blobs, guarded in case that table does
 * not exist yet), then forceDelete the file row. Scheduled daily; runs globally
 * (no auth) like the other prune commands.
 */
class PruneTrashedFiles extends Command
{
    protected $signature = 'files:prune-trash';

    protected $description = 'Permanently delete files trashed longer than the retention window and their blobs';

    public function handle(): int
    {
        $disk = Storage::disk(config('files.disk'));
        $cutoff = Carbon::now()->subDays((int) config('files.trash_retention_days', 30));
        $hasVersions = Schema::hasTable('file_versions');

        $count = 0;

        StoredFile::withoutGlobalScopes()
            ->onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->chunkById(200, function ($files) use ($disk, $hasVersions, &$count): void {
                foreach ($files as $file) {
                    if ($hasVersions) {
                        $blobs = DB::table('file_versions')
                            ->where('file_id', $file->id)
                            ->pluck('blob');

                        foreach ($blobs as $blob) {
                            if ($blob) {
                                $disk->delete('files/'.$blob);
                            }
                        }

                        DB::table('file_versions')->where('file_id', $file->id)->delete();
                    }

                    if ($file->blob) {
                        $disk->delete('files/'.$file->blob);
                    }

                    $file->forceDelete();
                    $count++;
                }
            });

        $this->info("Purged {$count} trashed file(s).");

        return self::SUCCESS;
    }
}
