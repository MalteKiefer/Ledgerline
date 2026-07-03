<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailMessage;
use App\Services\Mail\MailArchiveReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfill the search columns (cc, body_text, attachment_names) on already
 * archived messages by re-parsing their stored .eml. New messages get these at
 * sync time; this catches everything captured before the columns existed.
 */
class ReindexMailArchive extends Command
{
    /** Largest plain-text body stored for content search. */
    private const BODY_TEXT_CAP = 100000;

    protected $signature = 'mail:reindex {--force : Reindex every message, not only those missing search data}';

    protected $description = 'Backfill mail archive search columns from the stored .eml files';

    public function handle(MailArchiveReader $reader): int
    {
        $disk = Storage::disk(config('files.disk'));

        $query = MailMessage::query()
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('body_text')->whereNull('cc'));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to reindex.');

            return self::SUCCESS;
        }

        $done = 0;
        $failed = 0;
        $query->chunkById(200, function ($messages) use ($reader, $disk, &$done, &$failed): void {
            foreach ($messages as $message) {
                $path = 'mail/'.$message->blob;
                if (! $disk->exists($path)) {
                    $failed++;

                    continue;
                }

                try {
                    $parsed = $reader->parse((string) $disk->get($path));
                    $text = (string) ($parsed['text'] ?? '');
                    $message->forceFill([
                        'cc' => $parsed['cc'] ?? [],
                        'attachment_names' => array_map(static fn ($a): string => (string) ($a['name'] ?? ''), $parsed['attachments'] ?? []),
                        'body_text' => $text !== '' ? mb_substr($text, 0, self::BODY_TEXT_CAP) : null,
                    ])->save();
                    $done++;
                } catch (\Throwable) {
                    $failed++;
                }
            }
        });

        $this->info("Reindexed {$done} message(s); {$failed} skipped.");

        return self::SUCCESS;
    }
}
