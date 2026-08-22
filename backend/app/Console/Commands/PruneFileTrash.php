<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Support\FileActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Empty the file trash of anything past the retention window.
 *
 * Deleted files kept counting against the quota forever, because nothing ever
 * removed them: the only way out of the trash was for somebody to notice it and
 * empty it by hand. Mail logs, audit rows and reachability checks all have a
 * window; files did not, so the one store that costs real disk was the one that
 * never let go of anything.
 *
 * Zero days disables it. That is deliberate: a retention window silently
 * deleting a file somebody meant to restore is worse than a full trash, so an
 * operator has to choose the number.
 */
class PruneFileTrash extends Command
{
    protected $signature = 'files:prune-trash {--dry-run : List what would go without deleting anything}';

    protected $description = 'Permanently delete trashed files and folders past the retention window.';

    public function handle(): int
    {
        $configured = config('files.trash_retention_days', 0);
        $days = is_numeric($configured) ? (int) $configured : 0;
        if ($days < 1) {
            $this->info('Trash retention is off (files.trash_retention_days = 0); nothing pruned.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $dry = (bool) $this->option('dry-run');
        $configuredDisk = config('files.disk');
        $disk = Storage::disk(is_string($configuredDisk) ? $configuredDisk : 'local');

        // Owner scope is a global scope on these models and there is no user in
        // a scheduled run, so it has to come off explicitly here.
        $files = FileEntry::withoutGlobalScopes()->onlyTrashed()->where('deleted_at', '<', $cutoff)->get();
        $folders = FileFolder::withoutGlobalScopes()->onlyTrashed()->where('deleted_at', '<', $cutoff)->get();

        if ($dry) {
            $this->info("Would delete {$files->count()} file(s) and {$folders->count()} folder(s) trashed before {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $bytes = 0;
        foreach ($files as $file) {
            $owner = (int) $file->user_id;
            $name = (string) $file->name;
            $bytes += (int) $file->size;

            // Bytes first, rows second: a row without its blob is a leak that
            // nothing will ever find again, while a blob without its row is
            // caught by the orphan sweep.
            DB::transaction(function () use ($file, $disk): void {
                foreach ($file->versions()->get() as $version) {
                    $disk->delete((string) $version->storage_path);
                }
                $disk->delete((string) $file->storage_path);
                $file->forceDelete();
            });

            FileActivityLog::record($owner, 'delete', null, ['name' => $name, 'reason' => 'retention']);
        }

        // Folders last: a folder deleted first would take its still-trashed
        // children's rows with it through the cascade, blobs and all.
        foreach ($folders as $folder) {
            if (FileEntry::withoutGlobalScopes()->withTrashed()->where('file_folder_id', $folder->id)->exists()) {
                continue;
            }
            if (FileFolder::withoutGlobalScopes()->withTrashed()->where('parent_id', $folder->id)->exists()) {
                continue;
            }
            $folder->forceDelete();
        }

        $mb = round($bytes / 1024 / 1024, 1);
        $this->info("Pruned {$files->count()} file(s) ({$mb} MB) trashed before {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
