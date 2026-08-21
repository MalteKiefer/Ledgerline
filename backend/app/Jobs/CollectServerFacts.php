<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Server;
use App\Services\Servers\ServerMonitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Probe one server over SSH — WORKER-ONLY.
 *
 * The web request never opens the SSH session: under Octane a fixed pool of
 * workers serves every request, and one hanging connect would take a worker out
 * of rotation for the whole timeout. The UI reads the last recorded snapshot and
 * a refresh enqueues this job.
 *
 * One try, no retries: a failed probe is recorded as a failed run (the owner sees
 * the reason and can retry), and retrying a down host on a schedule only
 * multiplies the notifications.
 */
class CollectServerFacts implements ShouldQueue
{
    use Queueable;

    /** Comfortably above the probe's own connect + exec ceilings. */
    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(public int $serverId) {}

    public function handle(ServerMonitor $monitor): void
    {
        // No auth context in a queue worker, so the owner scope does not apply —
        // look the row up by key and let the monitor use its stored owner.
        $server = Server::query()->withoutGlobalScopes()->whereKey($this->serverId)->first();
        if ($server === null || ! $server->enabled) {
            return;
        }

        $monitor->refresh($server);
    }
}
