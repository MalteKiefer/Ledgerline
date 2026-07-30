<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

use App\Models\FileEntry;

/**
 * Archives the stored files (the "files/" prefix on the files disk). Files are
 * plain (unencrypted) now, so the archive can optionally be encrypted by the
 * backup job like the database dump.
 *
 * Plaintext-relational Files (pivot): bytes live at each row's storage_path
 * (files/<uuid>) across the `files` + `file_versions` tables — there is no
 * single `blob`-column ledger whose keys are the disk objects, so this source
 * cannot use the incremental blob delta (supportsLedgerDelta() = false). The
 * manager therefore always mirrors it by a full prefix reconcile, which enumerates
 * the disk directly and is correct for both current files and version bytes.
 */
final class FilesSource extends DiskArchiveSource implements MirrorableSource
{
    protected function prefix(): string
    {
        return 'files';
    }

    protected function name(): string
    {
        return 'files';
    }

    public function diskPrefix(): string
    {
        return 'files';
    }

    public function ledgerModel(): string
    {
        // Used only for the cheap size metric + cursor; FileEntry has both columns.
        // The delta path (which would need a `blob` column) is never taken because
        // supportsLedgerDelta() is false.
        return FileEntry::class;
    }

    public function supportsLedgerDelta(): bool
    {
        return false;
    }
}
