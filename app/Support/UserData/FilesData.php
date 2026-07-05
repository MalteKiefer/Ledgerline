<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\FileBlob;
use App\Models\FileFolder;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for the files module: exports and erases a user's
 * files and folders. Both own their data via the `user_id` column. File rows
 * carry only metadata; the bytes live on the files disk at "files/{blob}", so
 * purge deletes the stored blobs before force-deleting the rows. Purge also
 * clears the user's version snapshots (file_versions) and raw upload ownership
 * records (file_blobs), including their on-disk bytes, so no orphans remain.
 */
final class FilesData implements UserDataContributor
{
    public function key(): string
    {
        return 'files';
    }

    public function export(User $user): array
    {
        $folders = FileFolder::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (FileFolder $folder): array => $folder->attributesToArray())
            ->all();

        $files = StoredFile::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (StoredFile $file): array => $file->attributesToArray())
            ->all();

        return [
            'folders' => $folders,
            'files' => $files,
        ];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();

        StoredFile::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->chunkById(500, function ($files) use ($disk): void {
                foreach ($files as $file) {
                    if (is_string($file->blob) && Str::isUuid($file->blob)) {
                        $disk->delete('files/'.$file->blob);
                    }
                }

                StoredFile::query()
                    ->withoutGlobalScopes()
                    ->withTrashed()
                    ->whereIn('id', $files->modelKeys())
                    ->forceDelete();
            });

        // Prior blobs kept as version snapshots: delete their bytes and rows
        // (owner-scoped by user_id). Each version references a "files/{blob}".
        FileVersion::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->chunkById(500, function ($versions) use ($disk): void {
                foreach ($versions as $version) {
                    if (is_string($version->blob) && Str::isUuid($version->blob)) {
                        $disk->delete('files/'.$version->blob);
                    }
                }

                FileVersion::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $versions->modelKeys())
                    ->delete();
            });

        // Raw upload ownership records: delete their bytes (orphaned blobs never
        // attached to a StoredFile) and rows. The blob column is the primary key.
        FileBlob::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk): void {
                foreach ($blobs as $blob) {
                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete('files/'.$blob->blob);
                    }
                }

                FileBlob::query()
                    ->withoutGlobalScopes()
                    ->whereIn('blob', $blobs->modelKeys())
                    ->delete();
            }, 'blob');

        FileFolder::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->chunkById(500, function ($folders): void {
                FileFolder::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $folders->modelKeys())
                    ->delete();
            });
    }
}
