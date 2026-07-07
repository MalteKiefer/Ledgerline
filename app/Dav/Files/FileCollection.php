<?php

declare(strict_types=1);

namespace App\Dav\Files;

use App\Models\FileFolder;
use App\Models\FileVersion;
use App\Models\StoredFile;
use Illuminate\Support\Str;
use Sabre\DAV\Collection;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\IMoveTarget;
use Sabre\DAV\INode;
use Sabre\DAV\IQuota;
use Sabre\DAVACL\IACL;

/**
 * Base for a WebDAV directory: lists the folders and files that live directly
 * under a parent (a FileFolder id, or null for the user's root), and creates
 * child files/folders. Owner-scoped by user id; DAV has no Auth context.
 */
abstract class FileCollection extends Collection implements IACL, IMoveTarget, IQuota
{
    public function __construct(
        protected readonly FileDavBackend $backend,
        protected readonly int $userId,
        protected readonly string $principalUri,
        protected readonly ?string $parentId, // FileFolder id, or null for root
    ) {}

    /** @return array<int, INode> */
    public function getChildren(): array
    {
        $folders = FileFolder::withoutGlobalScopes()->where('user_id', $this->userId)
            ->where('parent_id', $this->parentId)->orderBy('name')->get()
            ->map(fn (FileFolder $f) => new FolderNode($this->backend, $this->userId, $this->principalUri, $f));

        $files = StoredFile::withoutGlobalScopes()->where('user_id', $this->userId)
            ->where('file_folder_id', $this->parentId)->orderBy('name')->get()
            ->map(fn (StoredFile $s) => new FileNode($this->backend, $s, $this->principalUri));

        // De-dup names (a folder and a file could share one): folders win.
        $seen = [];
        $out = [];
        foreach ([...$folders, ...$files] as $node) {
            $name = $node->getName();
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $out[] = $node;
        }

        return $out;
    }

    public function getChild($name): INode
    {
        foreach ($this->getChildren() as $child) {
            if ($child->getName() === $name) {
                return $child;
            }
        }
        throw new NotFound('File not found: '.$name);
    }

    public function childExists($name): bool
    {
        if (FileFolder::withoutGlobalScopes()->where('user_id', $this->userId)
            ->where('parent_id', $this->parentId)->where('name', $name)->exists()) {
            return true;
        }

        return StoredFile::withoutGlobalScopes()->where('user_id', $this->userId)
            ->where('file_folder_id', $this->parentId)->where('name', $name)->exists();
    }

    public function createFile($name, $data = null): ?string
    {
        $blob = $this->backend->storeBlob($this->userId, $data ?? '');
        $file = new StoredFile;
        $file->forceFill([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userId,
            'file_folder_id' => $this->parentId,
            'name' => (string) $name,
            'blob' => $blob,
            'size' => (int) ($this->backend->disk()->size('files/'.$blob) ?: 0),
            'mime' => $this->backend->guessMime((string) $name, $blob),
        ])->save();

        return '"'.md5($blob.'-'.$file->size).'"';
    }

    public function createDirectory($name): void
    {
        $folder = new FileFolder;
        $folder->forceFill([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userId,
            'parent_id' => $this->parentId,
            'name' => (string) $name,
        ])->save();
    }

    /**
     * Re-parent a file/folder into this collection instead of copy+delete, so a
     * move keeps the same blob and id. Only same-owner nodes are handled here.
     */
    public function moveInto($targetName, $sourcePath, INode $sourceNode): bool
    {
        if ($sourceNode instanceof FileNode) {
            $file = $sourceNode->storedFile();
            if ((int) $file->user_id !== $this->userId) {
                return false;
            }
            $file->forceFill(['file_folder_id' => $this->parentId, 'name' => (string) $targetName])->save();

            return true;
        }
        if ($sourceNode instanceof FolderNode) {
            $folder = $sourceNode->folderModel();
            if ((int) $folder->user_id !== $this->userId || $folder->id === $this->parentId) {
                return false;
            }
            $folder->forceFill(['parent_id' => $this->parentId, 'name' => (string) $targetName])->save();

            return true;
        }

        return false;
    }

    /**
     * [usedBytes, availableBytes] for the whole account — macOS Finder needs
     * this to mount and show free space. Without a configured quota, report a
     * generous headroom so clients do not think the drive is full.
     *
     * @return array{0:int,1:int}
     */
    public function getQuotaInfo(): array
    {
        $used = (int) StoredFile::withoutGlobalScopes()->withTrashed()
            ->where('user_id', $this->userId)->sum('size')
            + (int) FileVersion::where('user_id', $this->userId)->sum('size');
        $quota = (int) config('files.quota_mb', 0) * 1024 * 1024;
        $available = $quota > 0 ? max(0, $quota - $used) : 100 * 1024 * 1024 * 1024; // 100 GB headroom

        return [$used, $available];
    }

    public function getLastModified(): ?int
    {
        // macOS webdavfs requires getlastmodified on collections, but it MUST be
        // stable: a value that changes every request makes Finder invalidate its
        // cache and re-PROPFIND everything endlessly (mounts/copies crawl). Use
        // the newest child mtime (changes only when content changes), or a fixed
        // epoch for an empty directory.
        $folder = FileFolder::withoutGlobalScopes()->where('user_id', $this->userId)
            ->where('parent_id', $this->parentId)->max('updated_at');
        $file = StoredFile::withoutGlobalScopes()->where('user_id', $this->userId)
            ->where('file_folder_id', $this->parentId)->max('updated_at');
        $ts = max((int) strtotime((string) $folder), (int) strtotime((string) $file));

        return $ts > 0 ? $ts : (int) strtotime('2024-01-01 00:00:00');
    }

    // ---- ACL: owner-only ----
    public function getOwner(): ?string
    {
        return $this->principalUri;
    }

    public function getGroup(): ?string
    {
        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getACL(): array
    {
        return [['privilege' => '{DAV:}all', 'principal' => $this->principalUri, 'protected' => true]];
    }

    public function setACL(array $acl): void
    {
        throw new Forbidden('Changing ACL is not supported.');
    }

    public function getSupportedPrivilegeSet(): ?array
    {
        return null;
    }
}
