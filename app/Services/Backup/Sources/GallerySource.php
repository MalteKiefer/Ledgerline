<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

/**
 * Archives the gallery's zero-knowledge photo/video blobs + renditions (the
 * "gallery/" prefix, where GalleryBlobController writes them — module() is
 * 'gallery'). The sealed photo metadata lives in the gallery_store manifest row,
 * captured by a database backup.
 */
final class GallerySource extends DiskArchiveSource
{
    protected function prefix(): string
    {
        return 'gallery';
    }

    protected function name(): string
    {
        return 'gallery';
    }
}
