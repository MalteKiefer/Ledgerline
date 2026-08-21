<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CollectServerFacts;
use App\Models\Server;
use Illuminate\Console\Command;

/**
 * Enqueue a probe for every enabled server, across all users.
 *
 * Dispatches rather than probing inline: a slow or unreachable host must not hold
 * up the rest of the fleet, and the worker already bounds each probe's runtime.
 */
class PollServers extends Command
{
    protected $signature = 'servers:poll {--user= : Limit to one owner}';

    protected $description = 'Queue an SSH snapshot for every enabled monitored server.';

    public function handle(): int
    {
        // No auth context in a scheduled command, so the owner scope is inactive:
        // this is the intended fleet-wide sweep.
        $query = Server::query()->where('enabled', true);
        if (is_numeric($user = $this->option('user'))) {
            $query->where('user_id', (int) $user);
        }

        $count = 0;
        foreach ($query->pluck('id') as $id) {
            if (! is_numeric($id)) {
                continue;
            }
            CollectServerFacts::dispatch((int) $id);
            $count++;
        }

        $this->info("Queued {$count} server probe(s).");

        return self::SUCCESS;
    }
}
