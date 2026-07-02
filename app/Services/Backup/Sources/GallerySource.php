<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

/**
 * Archives the gallery's photo/video files and renditions (the "photos/"
 * prefix). Photo metadata lives in the database (captured by a database backup).
 */
final class GallerySource extends DiskArchiveSource
{
    protected function prefix(): string
    {
        return 'photos';
    }

    protected function name(): string
    {
        return 'gallery';
    }
}
