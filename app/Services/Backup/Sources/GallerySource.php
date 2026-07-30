<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

use App\Models\GalleryBlob;

/**
 * Archives the gallery's zero-knowledge photo/video blobs + renditions (the
 * "gallery/" prefix, where GalleryBlobController writes them — module() is
 * 'gallery'). The sealed photo metadata lives in the gallery_store manifest row,
 * captured by a database backup.
 */
final class GallerySource extends DiskArchiveSource implements MirrorableSource
{
    protected function prefix(): string
    {
        return 'gallery';
    }

    protected function name(): string
    {
        return 'gallery';
    }

    public function diskPrefix(): string
    {
        return 'gallery';
    }

    public function ledgerModel(): string
    {
        return GalleryBlob::class;
    }

    public function supportsLedgerDelta(): bool
    {
        // GalleryBlob is a single-column blob ledger (PK `blob` = disk object
        // name under gallery/), so the incremental delta upload works.
        return true;
    }
}
