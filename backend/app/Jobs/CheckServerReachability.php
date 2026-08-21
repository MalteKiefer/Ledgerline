<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\Server;
use App\Models\ServerCheck;
use App\Services\Servers\ReachabilityChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Ping one server and connect to its monitored ports — WORKER-ONLY, same reason
 * as CollectServerFacts: a connect that hangs must never occupy a web worker.
 *
 * One try. A retry would only double the row count for a host that is down, and
 * the next scheduled run is minutes away anyway.
 */
class CheckServerReachability implements ShouldQueue
{
    use Queueable;

    /** Well above the sum of one ping and a handful of four-second connects. */
    public int $timeout = 45;

    public int $tries = 1;

    /**
     * A single failure is a dropped packet, not an outage. Alert only once a
     * check has failed this many times in a row — the difference between a
     * useful notification and one the owner learns to ignore.
     */
    private const FAILURES_BEFORE_ALERT = 2;

    public function __construct(public int $serverId) {}

    public function handle(ReachabilityChecker $checker): void
    {
        $server = Server::query()->withoutGlobalScopes()->whereKey($this->serverId)->first();
        if ($server === null || ! $server->enabled) {
            return;
        }

        // The SSH port is always checked, whether or not the owner listed it:
        // it is the one port we know serves something, so it is the honest
        // answer to "is this host reachable".
        $ports = [$server->port];
        foreach ($server->monitorPorts() as $p) {
            $ports[] = $p['port'];
        }

        foreach ($checker->check($server->host, $ports) as $r) {
            $before = $this->recentFailures($server, $r['kind'], $r['port']);
            ServerCheck::query()->create([
                'server_id' => $server->id,
                'kind' => $r['kind'],
                'port' => $r['port'],
                'ok' => $r['ok'],
                'latency_ms' => $r['latency_ms'],
                'error' => $r['error'],
            ]);
            $this->notify($server, $r, $before);
        }
    }

    /**
     * How many consecutive failures immediately precede this result, counting
     * backwards from the newest row. Stops at the first success, so a check that
     * has been failing for a day still reports the full streak.
     */
    private function recentFailures(Server $server, string $kind, ?int $port): int
    {
        $rows = ServerCheck::query()
            ->where('server_id', $server->id)
            ->where('kind', $kind)
            ->when($port === null, fn ($q) => $q->whereNull('port'), fn ($q) => $q->where('port', $port))
            ->orderByDesc('id')
            ->limit(self::FAILURES_BEFORE_ALERT + 1)
            ->pluck('ok');

        $streak = 0;
        foreach ($rows as $ok) {
            if ($ok) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /**
     * Notify on the transition only. Down fires as the streak crosses the
     * threshold; up fires when a check succeeds after having been alerted, so
     * a recovery inside the grace period stays silent — nobody was told it was
     * down in the first place.
     *
     * @param  array{kind:string,port:int|null,ok:bool,latency_ms:int|null,error:string|null}  $r
     */
    private function notify(Server $server, array $r, int $priorFailures): void
    {
        $uid = $server->user_id;
        $what = $r['port'] === null
            ? __('servers.notify.check_host')
            : __('servers.notify.check_port', ['port' => (string) $r['port']]);

        if (! $r['ok'] && $priorFailures + 1 === self::FAILURES_BEFORE_ALERT) {
            AppNotification::record(
                $uid,
                'warning',
                __('servers.notify.unreachable', ['name' => $server->name, 'what' => $what]),
                $r['error'],
                'server'
            );

            return;
        }

        if ($r['ok'] && $priorFailures >= self::FAILURES_BEFORE_ALERT) {
            AppNotification::record(
                $uid,
                'info',
                __('servers.notify.reachable', ['name' => $server->name, 'what' => $what]),
                null,
                'server'
            );
        }
    }
}
