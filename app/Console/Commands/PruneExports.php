<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Deletes expired exports (past their retention window) and their zip files.
 * Scheduled daily.
 */
class PruneExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete expired download exports and their files';

    public function handle(): int
    {
        $count = 0;

        Export::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->each(function (Export $export) use (&$count): void {
                $export->purge();
                $count++;
            });

        $this->info("Pruned {$count} expired export(s).");

        return self::SUCCESS;
    }
}
