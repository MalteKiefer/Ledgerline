<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailMessage;
use Illuminate\Console\Command;

/**
 * Backfill the full-text `search_text` (subject + participants + text body +
 * attachment filenames) for archived messages that lack it. New mail is indexed
 * inline at ingest, so this only touches rows whose `indexed_at` is null (legacy
 * rows, or a message stored before search was populated); `--all` re-indexes
 * every message. Rebuilt from the already-denormalised columns — no re-parse of
 * the raw .eml. Owner scope is irrelevant here (a maintenance pass over every
 * user's rows), so the global scopes are dropped.
 */
class ReindexMail extends Command
{
    protected $signature = 'mail:reindex {--all : Re-index every message, not only those missing an index}';

    protected $description = 'Backfill mail_messages.search_text for un-indexed (or all) archived messages';

    /** Cap the stored search string (mirrors MaildirIngestor). */
    private const SEARCH_TEXT_MAX = 200_000;

    public function handle(): int
    {
        $reindexed = 0;

        MailMessage::query()
            ->withoutGlobalScopes()
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('indexed_at'))
            ->orderBy('id')
            ->chunkById(500, function ($messages) use (&$reindexed): void {
                foreach ($messages as $message) {
                    $message->forceFill([
                        'search_text' => $this->buildSearchText($message),
                        'indexed_at' => now(),
                    ])->saveQuietly();
                    $reindexed++;
                }
            });

        $this->info("Re-indexed {$reindexed} message(s).");

        return self::SUCCESS;
    }

    private function buildSearchText(MailMessage $m): ?string
    {
        $parts = [$m->subject, $m->from_name, $m->from_email];
        foreach ([...($m->to_json ?? []), ...($m->cc_json ?? [])] as $addr) {
            if (is_array($addr)) {
                $parts[] = $addr['name'] ?? null;
                $parts[] = $addr['email'] ?? null;
            }
        }
        $parts[] = $m->text_body;
        foreach ($m->attachments()->pluck('filename') as $filename) {
            $parts[] = is_string($filename) ? $filename : null;
        }

        $text = trim(implode(' ', array_filter(
            $parts,
            static fn (?string $p): bool => $p !== null && $p !== '',
        )));

        return $text === '' ? null : mb_substr($text, 0, self::SEARCH_TEXT_MAX);
    }
}
