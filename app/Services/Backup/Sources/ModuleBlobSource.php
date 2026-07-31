<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

use App\Support\BlobRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * Generalised mirror/archive source for any registered zero-knowledge blob
 * module — notes, passwords, invoices, contacts, explore, shared-folders. The
 * bytes under each prefix are opaque ciphertext (or, for shared-folders, blobs
 * wrapped under a shared vault key), so like FilesSource/GallerySource they can
 * be mirrored object-by-object or archived.
 *
 * These prefixes hold the ACTUAL encrypted record content of the sharded stores
 * (a record shard blob, not the database row) plus their attachment blobs
 * (invoice PDFs, contact avatars, explore tracks). Without a source per prefix a
 * database-only backup restores roots that point at blobs which were never
 * captured — i.e. total loss of that module's content on object-store failure.
 * Driven off BlobRegistry so a new blob module is backed up the moment it is
 * registered (and BackupJob::SOURCES lists it).
 */
final class ModuleBlobSource extends DiskArchiveSource implements MirrorableSource
{
    public function __construct(private readonly string $module)
    {
        // Fail fast on an unknown module rather than silently archiving nothing.
        BlobRegistry::prefix($this->module);
    }

    protected function prefix(): string
    {
        return BlobRegistry::prefix($this->module);
    }

    protected function name(): string
    {
        return $this->module;
    }

    public function diskPrefix(): string
    {
        return BlobRegistry::prefix($this->module);
    }

    /** @return class-string<Model> */
    public function ledgerModel(): string
    {
        return BlobRegistry::model($this->module);
    }
}
