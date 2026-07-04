<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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
        $disk = Storage::disk(config('files.disk'));
        $count = 0;

        Export::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->each(function (Export $export) use ($disk, &$count): void {
                foreach ($export->parts() as $part) {
                    $disk->delete($part['path']);
                }
                $export->delete();
                $count++;
            });

        $this->info("Pruned {$count} expired export(s).");

        return self::SUCCESS;
    }
}
