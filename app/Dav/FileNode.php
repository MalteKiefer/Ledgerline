<?php

declare(strict_types=1);

namespace App\Dav;

use App\Models\FileEntry;
use App\Models\UserSetting;
use App\Support\BlobStore;
use Illuminate\Support\Facades\Auth;
use Sabre\DAV\File;

/**
 * A WebDAV file over a FileEntry. get() streams the stored bytes; put() replaces
 * them (archiving the current bytes as a version). Owner-scoped via the model.
 */
class FileNode extends File
{
    public function __construct(private FileEntry $file) {}

    public function getName(): string
    {
        return (string) $this->file->name;
    }

    /** @return resource|string|null */
    public function get()
    {
        $stream = BlobStore::disk()->readStream((string) $this->file->storage_path);

        return $stream === false ? null : $stream;
    }

    public function put($data): ?string
    {
        [$path, $size, $sha] = DavStorage::writeBlob($data ?? '');
        $keep = min(200, max(1, (int) UserSetting::for((int) Auth::id())->file_max_versions));
        DavStorage::archiveVersion($this->file, $keep);
        $this->file->forceFill([
            'storage_path' => $path,
            'size' => $size,
            'sha256' => $sha,
        ]);
        $this->file->version = (int) $this->file->version + 1;
        $this->file->save();

        return '"'.(string) $sha.'"';
    }

    public function setName($name): void
    {
        $this->file->update(['name' => $name]);
    }

    public function delete(): void
    {
        $this->file->delete(); // soft-delete (to trash)
    }

    public function getSize(): int
    {
        return (int) $this->file->size;
    }

    public function getContentType(): ?string
    {
        return is_string($this->file->mime) && $this->file->mime !== '' ? $this->file->mime : null;
    }

    public function getETag(): ?string
    {
        return is_string($this->file->sha256) && $this->file->sha256 !== '' ? '"'.$this->file->sha256.'"' : null;
    }

    public function getLastModified(): ?int
    {
        return $this->file->updated_at?->getTimestamp();
    }
}
