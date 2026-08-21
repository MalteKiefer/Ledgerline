<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckServerReachability;
use App\Models\Server;
use Illuminate\Console\Command;

/**
 * Enqueue a reachability check for every enabled server.
 *
 * Runs far more often than servers:poll — a check is a socket, a probe is an SSH
 * session — which is the whole point: an outage that lasts ten minutes should
 * not be invisible until the next quarter-hourly snapshot.
 */
class CheckServers extends Command
{
    protected $signature = 'servers:check {--user= : Limit to one owner}';

    protected $description = 'Queue a ping and port check for every enabled monitored server.';

    public function handle(): int
    {
        // Scheduled command: no auth context, so the owner scope is inactive and
        // this sweeps the whole fleet, as intended.
        $query = Server::query()->where('enabled', true);
        if (is_numeric($user = $this->option('user'))) {
            $query->where('user_id', (int) $user);
        }

        $count = 0;
        foreach ($query->pluck('id') as $id) {
            if (! is_numeric($id)) {
                continue;
            }
            CheckServerReachability::dispatch((int) $id);
            $count++;
        }

        $this->info("Queued {$count} reachability check(s).");

        return self::SUCCESS;
    }
}
