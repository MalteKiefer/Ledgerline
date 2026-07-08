<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StoredFile;
use App\Services\Files\FileTextExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Extracts a file's searchable text (OCR where needed) off the request path.
 * Owner-scope-free: the queue has no Auth context, so it loads the row without
 * global scopes and only touches that one file.
 */
class ExtractFileText implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 360;

    // Retry a few times with backoff so a transient disk/tool failure doesn't
    // permanently leave the file unindexed.
    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [30, 120];

    public function __construct(private readonly string $fileId, private readonly string $blob) {}

    public function handle(FileTextExtractor $extractor): void
    {
        $file = StoredFile::withoutGlobalScopes()->withTrashed()->find($this->fileId);
        // Skip if gone or the blob already changed again (a newer job will run).
        if ($file === null || $file->blob !== $this->blob) {
            return;
        }

        $text = $extractor->extract($file);

        // Re-check the blob didn't change while extracting (avoid clobbering).
        $fresh = StoredFile::withoutGlobalScopes()->withTrashed()->find($this->fileId);
        if ($fresh !== null && $fresh->blob === $this->blob) {
            $fresh->forceFill(['content' => $text !== '' ? $text : null, 'content_at' => now()])->save();
        }
    }
}
