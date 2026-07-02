<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NoteShare;
use Illuminate\Console\Command;

/**
 * Delete expired note shares.
 *
 * Expired ciphertext is also removed lazily when a link is accessed, but a link
 * that is never opened again would linger until this runs. Schedule it (or run
 * manually) to keep the table clean.
 */
class PruneShares extends Command
{
    protected $signature = 'shares:prune';

    protected $description = 'Delete expired note share links';

    public function handle(): int
    {
        $deleted = NoteShare::query()->where('expires_at', '<', now())->delete();

        $this->info("Deleted {$deleted} expired share(s).");

        return self::SUCCESS;
    }
}
