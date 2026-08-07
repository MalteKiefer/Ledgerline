<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

/**
 * Archives the Files-module bytes stored under the "files/" prefix on the files
 * disk (current files + version history). These bytes live on disk, NOT in the
 * database dump. Plaintext at rest, so the archive can optionally be encrypted by
 * the backup job like the DB dump. A plain full-prefix archive on each run.
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
