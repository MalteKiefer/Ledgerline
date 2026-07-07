<?php

declare(strict_types=1);

namespace App\Dav\Files;

use App\Jobs\ExtractFileText;
use App\Models\StoredFile;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\File;
use Sabre\DAVACL\IACL;

/** A WebDAV file node backed by a StoredFile row + its blob on the files disk. */
class FileNode extends File implements IACL
{
    public function __construct(
        private readonly FileDavBackend $backend,
        private StoredFile $file,
        private readonly string $principalUri,
    ) {}

    public function getName(): string
    {
        return $this->file->name;
    }

    public function storedFile(): StoredFile
    {
        return $this->file;
    }

    /** @return resource|string|null */
    public function get()
    {
        return $this->backend->disk()->readStream('files/'.$this->file->blob);
    }

    /** Replace the file's bytes with a new blob (old one released if unused). */
    public function put($data): string
    {
        $userId = (int) $this->file->user_id;
        $old = $this->file->blob;
        $blob = $this->backend->storeBlob($userId, $data);
        $newSize = (int) ($this->backend->disk()->size('files/'.$blob) ?: 0);

        // macOS webdavfs sends a trailing 0-byte PUT to "finalize" a file it just
        // uploaded. Never let that empty body overwrite existing content — discard
        // the empty blob and keep what is there (leaves the file intact).
        if ($newSize === 0 && (int) $this->file->size > 0) {
            $this->backend->disk()->delete('files/'.$blob);

            return $this->getETag();
        }

        $this->file->forceFill([
            'blob' => $blob,
            'size' => $newSize,
            'mime' => $this->backend->guessMime($this->file->name, $blob),
        ])->save();
        ExtractFileText::dispatch($this->file->id, $blob)->afterCommit();
        if ($old !== $blob) {
            $this->backend->releaseBlob($old);
        }

        return $this->getETag();
    }

    public function getContentType(): ?string
    {
        return $this->file->mime ?: 'application/octet-stream';
    }

    public function getSize(): int
    {
        return (int) $this->file->size;
    }

    public function getETag(): ?string
    {
        return '"'.md5($this->file->blob.'-'.$this->file->size).'"';
    }

    public function getLastModified(): ?int
    {
        return $this->file->updated_at?->getTimestamp() ?? (int) strtotime('2024-01-01 00:00:00');
    }

    public function delete(): void
    {
        // Soft-delete (trash): keep the blob so the file can be restored from the
        // web trash. The blob is freed only on permanent delete (empty trash) or
        // by the orphan sweep.
        $this->file->delete();
    }

    public function setName($name): void
    {
        $this->file->forceFill(['name' => (string) $name])->save();
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
