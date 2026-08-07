<?php

declare(strict_types=1);

namespace App\Dav;

use App\Models\FileEntry;
use App\Models\FileFolder;
use Illuminate\Support\Facades\DB;
use Sabre\DAV\Collection;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\INode;

/**
 * A WebDAV collection over a FileFolder — or, when $folder is null, the user's
 * root. All queries are owner-scoped through the OwnsUserData global scope (the
 * DAV auth backend logged the user into the request).
 */
class FolderNode extends Collection
{
    public function __construct(private readonly ?FileFolder $folder) {}

    public function getName(): string
    {
        return $this->folder?->name ?? '';
    }

    private function parentId(): ?int
    {
        return $this->folder?->id;
    }

    /** @return array<int, INode> */
    public function getChildren(): array
    {
        $nodes = [];
        foreach (FileFolder::query()->where('parent_id', $this->parentId())->orderBy('name')->get() as $f) {
            $nodes[] = new self($f);
        }
        foreach (FileEntry::query()->where('file_folder_id', $this->parentId())->orderBy('name')->get() as $file) {
            $nodes[] = new FileNode($file);
        }

        return $nodes;
    }

    public function getChild($name): INode
    {
        $folder = FileFolder::query()->where('parent_id', $this->parentId())->where('name', $name)->first();
        if ($folder instanceof FileFolder) {
            return new self($folder);
        }
        $file = FileEntry::query()->where('file_folder_id', $this->parentId())->where('name', $name)->first();
        if ($file instanceof FileEntry) {
            return new FileNode($file);
        }
        throw new NotFound($name.' not found');
    }

    public function childExists($name): bool
    {
        return FileFolder::query()->where('parent_id', $this->parentId())->where('name', $name)->exists()
            || FileEntry::query()->where('file_folder_id', $this->parentId())->where('name', $name)->exists();
    }

    public function createFile($name, $data = null): ?string
    {
        [$path, $size, $sha] = DavStorage::writeBlob($data ?? '');
        $file = new FileEntry;
        $file->fill(['name' => $name, 'file_folder_id' => $this->parentId()]);
        $file->forceFill(['storage_path' => $path, 'size' => $size, 'sha256' => $sha, 'mime' => null]);
        $file->save();

        return '"'.(string) $sha.'"';
    }

    public function createDirectory($name): void
    {
        $folder = new FileFolder;
        $folder->fill(['name' => $name, 'parent_id' => $this->parentId()]);
        $folder->save();
    }

    public function setName($name): void
    {
        if (! $this->folder instanceof FileFolder) {
            throw new Forbidden('Cannot rename the root');
        }
        $this->folder->update(['name' => $name]);
    }

    public function delete(): void
    {
        if (! $this->folder instanceof FileFolder) {
            throw new Forbidden('Cannot delete the root');
        }
        $folder = $this->folder;
        DB::transaction(function () use ($folder): void {
            $ids = $this->descendantIds($folder->id);
            FileEntry::query()->whereIn('file_folder_id', $ids)->delete();
            FileFolder::query()->whereIn('id', $ids)->delete();
        });
    }

    /** @return list<int> */
    private function descendantIds(int $rootId): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];
        $guard = 0;
        while ($frontier !== [] && $guard++ < 10000) {
            /** @var list<int> $children */
            $children = FileFolder::query()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($v): int => is_numeric($v) ? (int) $v : 0)->all();
            $frontier = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
