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
     * Fully-qualified Eloquent model class used for the cheap size metric and the
     * incremental-delta cursor. Must expose a `size` and `created_at` column.
     *
     * @return class-string<Model>
     */
    public function ledgerModel(): string;

    /**
     * Whether the ledger model is a single-column blob ledger whose primary key
     * (queried as `blob`) maps 1:1 to a disk object under diskPrefix(). Sources
     * where that holds (gallery's GalleryBlob) can use the fast incremental delta;
     * sources whose disk keys don't come from a single `blob` column (the
     * plaintext-relational Files core, whose bytes live at row storage_paths across
     * two tables) return false and are always mirrored by a full prefix reconcile.
     */
    public function supportsLedgerDelta(): bool;
}
