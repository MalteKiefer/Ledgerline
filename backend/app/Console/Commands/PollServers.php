<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CollectServerFacts;
use App\Models\Server;
use Illuminate\Console\Command;

/**
 * Enqueue a probe for every enabled server that is DUE, across all users.
 *
 * Dispatches rather than probing inline: a slow or unreachable host must not hold
 * up the rest of the fleet, and the worker already bounds each probe's runtime.
 *
 * The schedule ticks every 30 seconds — the tightest interval a server may ask
 * for — and each server is only queued once its own `poll_interval_s` has
 * elapsed since its last snapshot. Without that check the per-server setting
 * would be decoration and every host would still be probed on the tick.
 *
 * `--force` ignores the due check (the manual "refresh now" path and tests).
 */
class PollServers extends Command
{
    /** Matches the client-side default; a server may override it per row. */
    private const DEFAULT_INTERVAL_S = 300;

    /** Half a tick, so a due snapshot is never pushed into the next one. */
    private const TOLERANCE_S = 15;

    protected $signature = 'servers:poll {--user= : Limit to one owner} {--force : Ignore each server\'s interval and probe now}';

    protected $description = 'Queue an SSH snapshot for every enabled monitored server.';

    public function handle(): int
    {
        // No auth context in a scheduled command, so the owner scope is inactive:
        // this is the intended fleet-wide sweep.
        $query = Server::query()->where('enabled', true);
        if (is_numeric($user = $this->option('user'))) {
            $query->where('user_id', (int) $user);
        }

        $force = (bool) $this->option('force');
        $count = 0;
        foreach ($query->with('latestFact')->get() as $server) {
            if (! $force && ! $this->isDue($server)) {
                continue;
            }
            CollectServerFacts::dispatch($server->id);
            $count++;
        }

        $this->info("Queued {$count} server probe(s).");

        return self::SUCCESS;
    }

    /**
     * Due when nothing has been collected yet, or when the server's own interval
     * has elapsed. A small tolerance absorbs the drift between the tick and the
     * moment the previous snapshot finished — without it a 300s interval on a
     * 30s tick would slip to 330s and keep sliding.
     */
    private function isDue(Server $server): bool
    {
        $last = $server->latestFact?->collected_at;
        if ($last === null) {
            return true;
        }
        $interval = $server->poll_interval_s ?: self::DEFAULT_INTERVAL_S;

        return $last->addSeconds($interval)->subSeconds(self::TOLERANCE_S)->isPast();
    }
}
