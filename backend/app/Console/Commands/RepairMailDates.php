<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailMessage;
use App\Support\BlobStore;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Puts the sent time of already-archived mail right.
 *
 * A Date header carries its own offset ("09:13:15 +0200"), and Eloquent writes a
 * datetime as the wall clock of its own timezone — so every message archived
 * before the parser was fixed stored the sender's local time as if it were UTC.
 * Read back and shown, such a mail arrives two hours after it was sent, and a
 * reply sorts ahead of the message it answers.
 *
 * The raw .eml is still here and is the source of truth, so this is repairable:
 * read the Date header again and store the instant it actually names.
 *
 * Only the header is read, never the body — for twenty thousand messages the
 * difference is minutes against an hour.
 */
class RepairMailDates extends Command
{
    protected $signature = 'mail:repair-dates
        {--user= : Only this account owner}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Re-read the Date header of archived mail and store the instant it names, not the sender\'s wall clock';

    /** Enough for the header block of any sane message. */
    private const HEAD_BYTES = 65536;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $fixed = 0;
        $agreed = 0;
        $noHeader = 0;
        $unreadable = 0;

        MailMessage::query()
            ->withoutGlobalScopes()
            ->when($this->option('user') !== null, fn ($q) => $q->where('user_id', (int) $this->option('user')))
            ->select(['id', 'user_id', 'date'])
            ->chunkById(500, function ($rows) use ($dry, &$fixed, &$agreed, &$noHeader, &$unreadable): void {
                foreach ($rows as $row) {
                    $head = $this->head((string) $row->id);
                    if ($head === null) {
                        $unreadable++;

                        continue;
                    }

                    $sent = $this->dateOf($head);
                    if ($sent === null) {
                        // No Date header, or one no parser can read. The stored
                        // value came from somewhere else and is left alone.
                        $noHeader++;

                        continue;
                    }

                    // Compare to the second: the stored column has no sub-second
                    // precision, so anything finer would report false changes.
                    if ($row->date !== null && $row->date->equalTo($sent)) {
                        $agreed++;

                        continue;
                    }

                    $fixed++;
                    if (! $dry) {
                        $row->forceFill(['date' => $sent])->save();
                    }
                }
            });

        $this->info(sprintf(
            '%s%d corrected, %d already right, %d without a usable Date header, %d unreadable',
            $dry ? '[dry run] ' : '',
            $fixed,
            $agreed,
            $noHeader,
            $unreadable,
        ));

        return self::SUCCESS;
    }

    /** The first bytes of the stored .eml — the header block, not the body. */
    private function head(string $id): ?string
    {
        try {
            $disk = BlobStore::disk();
            $path = 'mail/'.$id;
            if (! $disk->exists($path)) {
                return null;
            }
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                return null;
            }
            $head = (string) fread($stream, self::HEAD_BYTES);
            fclose($stream);

            return $head === '' ? null : $head;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The instant the Date header names, in UTC.
     *
     * Unfolds a header wrapped across lines before parsing — a folded Date is
     * unusual but legal, and a half-read one would produce a plausible wrong
     * answer rather than no answer.
     */
    private function dateOf(string $head): ?Carbon
    {
        $unfolded = preg_replace('/\r?\n[ \t]+/', ' ', $head);
        if (! is_string($unfolded)) {
            return null;
        }
        if (preg_match('/^date:\s*(.+)$/im', $unfolded, $m) !== 1) {
            return null;
        }

        try {
            $parsed = Carbon::parse(trim($m[1]));
        } catch (Throwable) {
            return null;
        }

        // A year far outside anything real means the header was garbage and
        // parsed into something meaningless; better to leave the row alone.
        $year = (int) $parsed->format('Y');
        if ($year < 1970 || $year > 2100) {
            return null;
        }

        return $parsed->utc();
    }
}
