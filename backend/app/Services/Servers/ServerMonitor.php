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
    /** Defaults, used where a server sets no threshold of its own. */
    private const DISK_ALERT_PCT = 90.0;

    private const MEM_ALERT_PCT = 95.0;

    private const TEMP_ALERT_C = 85.0;

    public function __construct(private readonly ServerProbe $probe) {}

    /** Probe a stored server, record the run, notify on a state change. */
    public function refresh(Server $server): ServerFact
    {
        $previous = ServerFact::query()
            ->where('server_id', $server->id)
            ->orderByDesc('collected_at')
            ->first();

        $result = $this->probe->run(ServerTarget::fromServer($server));

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
        $diskLimit = (float) ($server->disk_alert_pct ?? self::DISK_ALERT_PCT);
        foreach ($this->disks($facts) as $mount => $pct) {
            if ($pct < $diskLimit) {
                continue;
            }
            if (($this->disks($prev)[$mount] ?? 0.0) >= $diskLimit) {
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

        // A drive that has started remapping sectors is on its way out, whatever
        // its overall verdict says — and unlike a full disk, nobody finds this
        // by looking. Fires when a drive first goes bad, not on every poll.
        $bad = $this->badDrives($facts);
        $newlyBad = array_values(array_diff($bad, $this->badDrives($prev)));
        if ($newlyBad !== []) {
            AppNotification::record(
                $uid,
                'warning',
                __('servers.notify.drive', ['name' => $server->name, 'drive' => implode(', ', $newlyBad)]),
                null,
                'server',
            );
        }

        // Same for an array: it keeps working, right up until the second disk
        // goes, which is why it has to be said out loud the first time.
        $degraded = $this->degradedArrays($facts);
        $newlyDegraded = array_values(array_diff($degraded, $this->degradedArrays($prev)));
        if ($newlyDegraded !== []) {
            AppNotification::record(
                $uid,
                'warning',
                __('servers.notify.array', ['name' => $server->name, 'array' => implode(', ', $newlyDegraded)]),
                null,
                'server',
            );
        }

        // Memory and temperature, both threshold crossings like the disk.
        $memLimit = (float) ($server->mem_alert_pct ?? self::MEM_ALERT_PCT);
        $memNow = $this->memPct($facts);
        if ($memNow !== null && $memNow >= $memLimit && ($this->memPct($prev) ?? 0.0) < $memLimit) {
            AppNotification::record(
                $uid,
                'warning',
                __('servers.notify.memory', ['name' => $server->name, 'pct' => (string) $memNow]),
                null,
                'server',
            );
        }

        $tempLimit = (float) ($server->temp_alert_c ?? self::TEMP_ALERT_C);
        $hotNow = $this->hottest($facts);
        if ($hotNow !== null && $hotNow >= $tempLimit && ($this->hottest($prev) ?? 0.0) < $tempLimit) {
            AppNotification::record(
                $uid,
                'warning',
                __('servers.notify.temperature', ['name' => $server->name, 'temp' => (string) $hotNow]),
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
     * Drives whose health has gone bad, named so two runs can be compared.
     *
     * `unknown` is not in here: a drive whose state could not be read has not
     * failed, and waking somebody for a missing tool would train them to
     * ignore the alert that matters.
     *
     * @param  array<string, mixed>  $facts
     * @return list<string>
     */
    private function badDrives(array $facts): array
    {
        $out = [];
        $drives = is_array($facts['storage'] ?? null) ? $facts['storage'] : [];
        foreach ($drives as $d) {
            if (! is_array($d) || ! is_string($d['name'] ?? null)) {
                continue;
            }
            $failing = ($d['health'] ?? '') === 'failing';
            // A count we cannot read is not a count of zero, so it is not
            // treated as one: only a real number above zero counts.
            $remapped = (is_numeric($d['reallocated'] ?? null) && (int) $d['reallocated'] > 0)
                || (is_numeric($d['pending'] ?? null) && (int) $d['pending'] > 0);
            if ($failing || $remapped) {
                $out[] = $d['name'];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return list<string>
     */
    private function degradedArrays(array $facts): array
    {
        $out = [];
        $arrays = is_array($facts['arrays'] ?? null) ? $facts['arrays'] : [];
        foreach ($arrays as $a) {
            if (is_array($a) && ($a['degraded'] ?? false) === true && is_string($a['name'] ?? null)) {
                $out[] = $a['name'];
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $facts */
    private function memPct(array $facts): ?float
    {
        $mem = is_array($facts['mem'] ?? null) ? $facts['mem'] : [];

        return is_numeric($mem['used_pct'] ?? null) ? (float) $mem['used_pct'] : null;
    }

    /**
     * The hottest sensor on the box, which is the one that decides whether it
     * is running hot — an average would hide it behind the cool ones.
     *
     * @param  array<string, mixed>  $facts
     */
    private function hottest(array $facts): ?float
    {
        $best = null;
        $sensors = is_array($facts['sensors'] ?? null) ? $facts['sensors'] : [];
        foreach ($sensors as $s) {
            if (is_array($s) && is_numeric($s['temp_c'] ?? null)) {
                $best = max($best ?? 0.0, (float) $s['temp_c']);
            }
        }

        return $best;
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
