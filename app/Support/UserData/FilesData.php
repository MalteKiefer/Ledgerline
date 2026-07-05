<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\FileFolder;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for the files module: exports and erases a user's
 * files and folders. Both own their data via the `user_id` column. File rows
 * carry only metadata; the bytes live on the files disk at "files/{blob}", so
 * purge deletes the stored blobs before force-deleting the rows.
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
        $disk = Storage::disk(config('files.disk'));

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
