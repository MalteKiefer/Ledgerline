<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Http\Controllers\GalleryController;
use App\Models\FileEntry;
use App\Models\GalleryPhoto;
use Throwable;

/**
 * Re-run content extraction over existing records: file text (PDF/OCR/text →
 * search_text) and gallery photo OCR (ocr_text). Used by the backfill commands and
 * the profile/admin "reindex" triggers. Owner-scope-free — pass a userId to limit
 * to one user, or null for all. Best-effort per record; chunked so it never loads
 * the whole table.
 */
class ContentIndexer
{
    /** Re-extract search_text for files (all, or one user). Returns files processed. */
    public function reindexFiles(?int $userId): int
    {
        $index = new FileTextIndex;
        $count = 0;
        $query = FileEntry::withoutGlobalScopes()->orderBy('id');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        $query->chunkById(200, function ($files) use ($index, &$count): void {
            foreach ($files as $file) {
                if (! $file instanceof FileEntry) {
                    continue;
                }
                try {
                    $file->forceFill(['search_text' => $index->extract($file), 'indexed_at' => now()])->saveQuietly();
                } catch (Throwable) {
                    // best-effort
                }
                $count++;
            }
        });

        return $count;
    }

    /** Re-extract OCR text for gallery photos (all, or one user). Returns photos processed. */
    public function reindexGallery(?int $userId): int
    {
        $gallery = app(GalleryController::class);
        $count = 0;
        $query = GalleryPhoto::withoutGlobalScopes()->orderBy('id');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        $query->chunkById(100, function ($photos) use ($gallery, &$count): void {
            foreach ($photos as $photo) {
                if (! $photo instanceof GalleryPhoto) {
                    continue;
                }
                try {
                    $gallery->ocrPhoto($photo);
                } catch (Throwable) {
                    // best-effort
                }
                $count++;
            }
        });

        return $count;
    }
}
