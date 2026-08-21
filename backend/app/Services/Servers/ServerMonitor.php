<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\AppNotification;
use App\Models\Server;
use App\Models\ServerFact;
use Illuminate\Support\Carbon;

/**
 * Persists one probe run and decides whether the owner should hear about it.
 *
 * Notifications fire on a CHANGE of state, never on every poll: a host that has
 * been unreachable for two days must not produce a notification every quarter
 * hour. The previous run is the comparison point, which is why the history table
 * keeps more than just the newest row.
 */
final class ServerMonitor
{
    /** A filesystem above this share is worth telling the owner about. */
    private const DISK_ALERT_PCT = 90.0;

    public function __construct(private readonly ServerProbe $probe) {}

    /** Probe a stored server, record the run, notify on a state change. */
    public function refresh(Server $server): ServerFact
    {
        $previous = ServerFact::query()
            ->where('server_id', $server->id)
            ->orderByDesc('collected_at')
            ->first();

        $credentials = $server->credentials ?? [];
        $result = $this->probe->run(new ServerTarget(
            host: $server->host,
            port: $server->port,
            username: $server->username,
            privateKey: is_string($credentials['private_key'] ?? null) ? $credentials['private_key'] : '',
            passphrase: is_string($credentials['passphrase'] ?? null) ? $credentials['passphrase'] : '',
            fingerprint: (string) $server->host_fingerprint,
            hostKey: (string) $server->host_key,
        ));

        $fact = new ServerFact;
        $fact->forceFill([
            'server_id' => $server->id,
            'ok' => $result->ok,
            'error' => $result->error,
            'facts' => $result->ok ? $result->facts : null,
            'duration_ms' => $result->durationMs,
            'collected_at' => Carbon::now(),
        ])->save();

        $this->notify($server, $previous, $fact);

        return $fact;
    }

    private function notify(Server $server, ?ServerFact $previous, ServerFact $current): void
    {
        $uid = $server->user_id;

        // Reachability, both directions — the recovery message matters as much as
        // the failure, otherwise a resolved outage stays visible as an alarm.
        if ($previous !== null && $previous->ok !== $current->ok) {
            $current->ok
                ? AppNotification::record($uid, 'info', __('servers.notify.up', ['name' => $server->name]), null, 'server')
                : AppNotification::record($uid, 'warning', __('servers.notify.down', ['name' => $server->name]), $current->error, 'server');
        } elseif ($previous === null && ! $current->ok) {
            // First ever run failed: there is no state change, but silence would
            // leave a freshly added server looking fine.
            AppNotification::record($uid, 'warning', __('servers.notify.down', ['name' => $server->name]), $current->error, 'server');
        }

        if (! $current->ok) {
            return;
        }

        $facts = $current->facts ?? [];
        $prev = $previous?->ok === true ? ($previous->facts ?? []) : [];

        // Reboot required — only when it flips on.
        if (($facts['reboot_required'] ?? false) === true && ($prev['reboot_required'] ?? false) !== true) {
            AppNotification::record($uid, 'info', __('servers.notify.reboot', ['name' => $server->name]), null, 'server');
        }

        // Disk pressure — only when it crosses the threshold, per filesystem.
        foreach ($this->disks($facts) as $mount => $pct) {
            if ($pct < self::DISK_ALERT_PCT) {
                continue;
            }
            if (($this->disks($prev)[$mount] ?? 0.0) >= self::DISK_ALERT_PCT) {
                continue;
            }
            AppNotification::record(
                $uid,
                'warning',
                __('servers.notify.disk', ['name' => $server->name, 'mount' => $mount, 'pct' => (string) $pct]),
                null,
                'server',
            );
        }

        // Failed systemd units — only units that were not already failing.
        $units = $this->units($facts);
        $newlyFailed = array_values(array_diff($units, $this->units($prev)));
        if ($newlyFailed !== []) {
            AppNotification::record(
                $uid,
                'warning',
                __('servers.notify.units', ['name' => $server->name, 'count' => (string) count($newlyFailed)]),
                implode(', ', array_slice($newlyFailed, 0, 10)),
                'server',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, float>
     */
    private function disks(array $facts): array
    {
        $out = [];
        $disks = is_array($facts['disks'] ?? null) ? $facts['disks'] : [];
        foreach ($disks as $d) {
            if (is_array($d) && is_string($d['mount'] ?? null) && is_numeric($d['used_pct'] ?? null)) {
                $out[$d['mount']] = (float) $d['used_pct'];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return list<string>
     */
    private function units(array $facts): array
    {
        $units = is_array($facts['failed_units'] ?? null) ? $facts['failed_units'] : [];

        return array_values(array_filter(array_map(
            static fn (mixed $u): string => is_string($u) ? $u : '',
            $units,
        ), static fn (string $u): bool => $u !== ''));
    }
}
