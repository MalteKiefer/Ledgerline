<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BuildExport;
use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Rescues exports that never finished — a worker died mid-build (or was never
 * picked up) so the row is stuck in 'queued'/'processing' with no result and no
 * expires_at. Anything older than twice the build timeout is marked 'failed' and
 * given an expires_at so the daily prune later cleans it up. Scheduled hourly.
 */
class RecoverStuckExports extends Command
{
    protected $signature = 'exports:recover-stuck';

    protected $description = 'Fail exports stuck building past the build timeout so they get pruned';

    public function handle(): int
    {
        // Twice the job timeout: comfortably past any legitimately running build.
        $cutoff = Carbon::now()->subSeconds(BuildExport::TIMEOUT * 2);
        $count = 0;

        Export::query()
            ->whereIn('status', ['queued', 'processing'])
            ->where('updated_at', '<', $cutoff)
            ->each(function (Export $export) use (&$count): void {
                $export->forceFill([
                    'status' => 'failed',
                    'error' => __('downloads.error.stuck'),
                    'expires_at' => Carbon::now()->addDays(BuildExport::RETENTION_DAYS),
                ])->save();
                $count++;
            });

        $this->info("Recovered {$count} stuck export(s).");

        return self::SUCCESS;
    }
}
