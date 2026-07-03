<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

/**
 * Archives the stored files (the "files/" prefix on the files disk). Files are
 * plain (unencrypted) now, so the archive can optionally be encrypted by the
 * backup job like the database dump.
 */
final class FilesSource extends DiskArchiveSource
{
    protected function prefix(): string
    {
        return 'files';
    }

    protected function name(): string
    {
        return 'files';
    }
}
