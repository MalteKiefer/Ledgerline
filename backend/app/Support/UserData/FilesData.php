<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\FileEntry;
use App\Models\FileShare;
use App\Models\FileVersion;
use App\Models\FolderShare;
use App\Models\FolderShareMember;
use App\Models\User;
use App\Support\BlobStore;

/**
 * Per-user data contributor for the plaintext-relational Files core (pivot). The
 * file tree (names, folders, tags, notes, versions) lives as rows in the `files`
 * / `file_folders` / `file_versions` tables; bytes live at files/<uuid> on the
 * file disk. Export is a plaintext inventory of the user's files; purge deletes
 * every file's + version's disk bytes and rows so no orphaned bytes remain (the
 * folder rows cascade with the user delete).
 */
final class FilesData implements UserDataContributor
{
    public function key(): string
    {
        return 'files';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $uid = $user->getKey();

        $files = FileEntry::query()
            ->withoutGlobalScopes()
            ->where('user_id', $uid)
            ->orderBy('id')
            ->get(['id', 'file_folder_id', 'name', 'mime', 'size', 'created_at'])
            ->map(fn (FileEntry $f): array => [
                'id' => $f->id,
                'folder_id' => $f->file_folder_id,
                'name' => $f->name,
                'mime' => $f->mime,
                'size' => $f->size,
                'created_at' => $f->created_at,
            ])
            ->all();

        // Public share links the user owns. password_hash is #[$hidden] and a
        // secret — the explicit column list omits it.
        $publicShares = FileShare::query()
            ->withoutGlobalScopes()
            ->where('user_id', $uid)
            ->orderBy('id')
            ->get(['id', 'token', 'kind', 'file_id', 'file_folder_id', 'allow_download', 'expires_at', 'created_at'])
            ->toArray();

        // Cross-user folder shares the user owns (owner column is owner_id) …
        $folderShares = FolderShare::query()
            ->withoutGlobalScopes()
            ->where('owner_id', $uid)
            ->orderBy('id')
            ->get(['id', 'file_folder_id', 'file_id', 'created_at'])
            ->toArray();

        // … and the recipient memberships attached to those shares.
        $folderShareIds = array_column($folderShares, 'id');
        $folderShareMembers = $folderShareIds === []
            ? []
            : FolderShareMember::query()
                ->whereIn('folder_share_id', $folderShareIds)
                ->orderBy('id')
                ->get(['id', 'folder_share_id', 'user_id', 'role', 'created_at'])
                ->toArray();

        return [
            'files' => $files,
            'file_shares' => $publicShares,
            'folder_shares' => $folderShares,
            'folder_share_members' => $folderShareMembers,
        ];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();

        FileEntry::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->chunkById(500, function ($files) use ($disk): void {
                $ids = $files->modelKeys();

                // Prior-version bytes first (they occupy disk until forced too).
                FileVersion::query()
                    ->whereIn('file_id', $ids)
                    ->orderBy('id')
                    ->chunkById(500, function ($versions) use ($disk): void {
                        foreach ($versions as $version) {
                            if (is_string($version->storage_path) && $version->storage_path !== '') {
                                $disk->delete($version->storage_path);
                            }
                        }
                        FileVersion::query()->whereIn('id', $versions->modelKeys())->delete();
                    });

                foreach ($files as $file) {
                    if (is_string($file->storage_path) && $file->storage_path !== '') {
                        $disk->delete($file->storage_path);
                    }
                }

                FileEntry::query()->withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
            });
    }
}
