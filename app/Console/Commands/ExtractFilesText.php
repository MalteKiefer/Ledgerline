<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExtractFileText;
use App\Models\StoredFile;
use Illuminate\Console\Command;

/** Backfill searchable text for files that have not been extracted yet. */
class ExtractFilesText extends Command
{
    protected $signature = 'files:extract-text {--all : Re-extract every file, not just missing ones}';

    protected $description = 'Extract (OCR) searchable text from stored files for full-text search';

    public function handle(): int
    {
        $query = StoredFile::withoutGlobalScopes()->withTrashed();
        if (! $this->option('all')) {
            $query->whereNull('content_at');
        }

        $n = 0;
        $query->select(['id', 'blob'])->orderBy('id')->chunk(200, function ($files) use (&$n): void {
            foreach ($files as $f) {
                ExtractFileText::dispatch($f->id, $f->blob);
                $n++;
            }
        });

        $this->info("Queued {$n} file(s) for text extraction.");

        return self::SUCCESS;
    }
}
