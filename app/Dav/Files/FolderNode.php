<?php

declare(strict_types=1);

namespace App\Dav\Files;

use App\Models\FileFolder;
use App\Models\StoredFile;

/** A WebDAV directory backed by a FileFolder row. */
class FolderNode extends FileCollection
{
    public function __construct(
        FileDavBackend $backend,
        int $userId,
        string $principalUri,
        private FileFolder $folder,
    ) {
        parent::__construct($backend, $userId, $principalUri, $folder->id);
    }

    public function getName(): string
    {
        return $this->folder->name;
    }

    public function folderModel(): FileFolder
    {
        return $this->folder;
    }

    public function getLastModified(): ?int
    {
        return $this->folder->updated_at?->getTimestamp() ?? (int) strtotime('2024-01-01 00:00:00');
    }

    public function setName($name): void
    {
        $this->folder->forceFill(['name' => (string) $name])->save();
    }

    /** Delete the folder and its subtree atomically (files → trash). */
    public function delete(): void
    {
        // One transaction so a mid-recursion failure can't leave a half-deleted
        // tree (some files trashed, some folders gone, parents dangling).
        \DB::transaction(function (): void {
            $this->deleteTree($this->folder->id);
            $this->folder->delete();
        });
    }

    private function deleteTree(string $folderId): void
    {
        // Trash the files (move to root + keep the blob) so they stay restorable
        // from the web trash after the folder itself is gone; then drop the now
        // empty subfolders (folders are not soft-deletable).
        foreach (StoredFile::withoutGlobalScopes()->where('file_folder_id', $folderId)->get() as $file) {
            $file->forceFill(['file_folder_id' => null])->save();
            $file->delete();
        }
        foreach (FileFolder::withoutGlobalScopes()->where('parent_id', $folderId)->get() as $sub) {
            $this->deleteTree($sub->id);
            $sub->delete();
        }
    }
}
