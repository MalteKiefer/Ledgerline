<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FileEntry;
use App\Services\Files\FileMetadata;
use App\Services\Files\FileTextIndex;
use Throwable;

/**
 * Keeps a file's searchable text (search_text/indexed_at) in sync with its
 * bytes. Indexing is dispatched to the queue (the app uses a database queue,
 * so extraction — which may shell out to pdftotext/tesseract for up to a
 * minute — never blocks the upload request; on a sync queue, e.g. tests, it
 * runs inline). The write happens via saveQuietly so it does not re-fire the
 * observer.
 */
class FileEntryObserver
{
    /** A freshly uploaded file gets indexed. */
    public function created(FileEntry $file): void
    {
        $this->queueIndex((int) $file->id);
    }

    /** Re-index only when the underlying bytes changed (new revision / re-upload). */
    public function updated(FileEntry $file): void
    {
        if ($file->wasChanged(['storage_path', 'sha256'])) {
            $this->queueIndex((int) $file->id);
        }
    }

    private function queueIndex(int $fileId): void
    {
        dispatch(function () use ($fileId): void {
            try {
                // No auth context on the queue → bypass the owner read scope and
                // resolve the row by id (the owning user was already authorised
                // when the file was created/updated).
                $file = FileEntry::withoutGlobalScopes()->find($fileId);
                if (! $file instanceof FileEntry) {
                    return;
                }

                $text = (new FileTextIndex)->extract($file);
                $meta = (new FileMetadata)->extract($file);
                $file->forceFill([
                    'search_text' => $text,
                    'metadata' => $meta,
                    'indexed_at' => now(),
                ])->saveQuietly();
            } catch (Throwable) {
                // Indexing is best-effort; never let it fail the job/request.
            }
        });
    }
}
