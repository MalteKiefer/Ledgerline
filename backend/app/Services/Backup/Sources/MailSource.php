<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

/**
 * Archives the "mail/" prefix on the files disk — bytes that live on disk and are
 * NOT in the database dump, so without this source they would be unbacked-up.
 * Plaintext at rest; the archive can be encrypted by the backup job like the DB
 * dump.
 */
final class MailSource extends DiskArchiveSource
{
    protected function prefix(): string
    {
        return 'mail';
    }

    protected function name(): string
    {
        return 'mail';
    }
}
