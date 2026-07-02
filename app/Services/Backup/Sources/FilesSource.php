<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

/**
 * Archives the vault's opaque content blobs (the "vault/" prefix). The blobs
 * are already client-side encrypted; the archive holds them verbatim.
 */
final class FilesSource extends DiskArchiveSource
{
    protected function prefix(): string
    {
        return 'vault';
    }

    protected function name(): string
    {
        return 'files';
    }
}
