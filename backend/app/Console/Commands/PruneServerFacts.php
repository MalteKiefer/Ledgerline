<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ServerFact;
use Illuminate\Console\Command;

/**
 * Prune server snapshots outside the retention window.
 *
 * The newest snapshot per server is always kept, whatever its age: it is what the
 * UI renders and what a state-change notification compares against. Pruning it
 * would make a long-idle server look like it had never been probed.
 */
class PruneServerFacts extends Command
{
    protected $signature = 'servers:prune-facts';

    protected $description = 'Delete server snapshots older than the retention window (keeps the newest per server).';

    public function handle(): int
    {
        $configured = config('servers.fact_retention_days', 30);
        $days = is_numeric($configured) ? (int) $configured : 30;
        $cutoff = now()->subDays(max(1, $days));

        // MAX(id) is the newest run per server: ids are assigned in insert
        // order and collected_at is always "now" at write time.
        $keep = ServerFact::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('server_id')
            ->pluck('id')
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $query = ServerFact::query()->where('collected_at', '<', $cutoff);
        if ($keep !== []) {
            $query->whereNotIn('id', $keep);
        }

        $result = $query->delete();
        $deleted = is_numeric($result) ? (int) $result : 0;
        $this->info("Pruned {$deleted} server snapshot(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
