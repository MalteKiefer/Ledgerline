<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

use Illuminate\Database\Eloquent\Model;

/**
 * Marker for backup sources whose blobs can be mirrored object-by-object
 * (already-encrypted ciphertext) rather than archived into a tarball.
 * Implemented by FilesSource and GallerySource.
 */
interface MirrorableSource extends BackupSource
{
    /** Disk prefix where blobs are stored (e.g. 'files', 'gallery'). */
    public function diskPrefix(): string;

    /**
     * Fully-qualified Eloquent model class for the blob ownership ledger.
     *
     * @return class-string<Model>
     */
    public function ledgerModel(): string;
}
