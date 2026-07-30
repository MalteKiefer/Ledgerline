<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

use App\Models\GalleryPhoto;

/**
 * Archives the gallery's photo/video bytes + renditions (the "gallery/" prefix,
 * where GalleryController writes them). The plaintext photo metadata lives in the
 * gallery_photos rows, captured by a database backup.
 *
 * Plaintext-relational Gallery (pivot): bytes live at each row's storage_path /
 * thumb_path / medium_path / motion_path (gallery/<uuid>) in gallery_photos —
 * there is no single `blob`-column ledger whose keys are the disk objects, so this
 * source cannot use the incremental blob delta (supportsLedgerDelta() = false).
 * The manager therefore always mirrors it by a full prefix reconcile, which
 * enumerates the disk directly and is correct for originals + every rendition.
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
        // Used only for the cheap total-size metric + cursor; GalleryPhoto has both
        // `size` and `created_at`. The delta path (which would need a `blob` column
        // mapping 1:1 to disk objects) is never taken because supportsLedgerDelta()
        // is false.
        return GalleryPhoto::class;
    }

    public function supportsLedgerDelta(): bool
    {
        return false;
    }
}
