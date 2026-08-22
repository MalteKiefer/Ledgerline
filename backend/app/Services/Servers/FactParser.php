<?php

declare(strict_types=1);

namespace App\Services\Servers;

/**
 * Turns the marker-delimited probe output into the snapshot the UI renders.
 *
 * Pure text in, array out — no I/O — so the whole distribution matrix is
 * testable from fixtures without an SSH server. Every section is optional: a
 * host without systemd or docker simply omits those keys rather than failing.
 */
final class FactParser
{
    /**
     * Filesystems that describe no storage of their own. `overlay` is in here
     * because a container layer reports the figures of the disk beneath it,
     * which is already listed under its real mount point.
     */
    private const PSEUDO_FILESYSTEMS = ['tmpfs', 'devtmpfs', 'udev', 'none', 'overlay', 'squashfs', 'ramfs', 'efivarfs'];

    /** @return array<string, mixed> */
    public function parse(string $output): array
    {
        $s = $this->sections($output);

        $os = $this->osRelease($s['os'] ?? '');
        $mem = $this->meminfo($s['mem'] ?? '');
        [$kernel, $arch] = $this->kernel($s['kernel'] ?? '');

        $facts = [
            'hostname' => $this->firstLine($s['hostname'] ?? ''),
            'os' => $os,
            'kernel' => $kernel,
            'arch' => $arch,
            'uptime_s' => $this->uptime($s['uptime'] ?? ''),
            'load' => $this->load($s['load'] ?? ''),
            'cpu' => $this->cpu($s['cpu'] ?? '', $s['cpustat'] ?? ''),
            'mem' => $mem,
            'disks' => $this->disks($s['disk'] ?? ''),
            'reboot_required' => trim($s['reboot'] ?? '') === 'yes',
            'failed_units' => $this->failedUnits($s['failed'] ?? ''),
            'ports' => $this->ports($s['ports'] ?? ''),
            'containers' => $this->containers($s['containers'] ?? ''),
            'updates' => $this->updates($s['updates'] ?? ''),
            'addresses' => $this->addresses($s['ip'] ?? ''),
            'virt' => $this->virt($s['virt'] ?? ''),
            'boot_at' => $this->bootAt($s['boot'] ?? ''),
            'sessions' => $this->sessions($s['sessions'] ?? ''),
            'processes' => $this->processes($s['procs'] ?? ''),
            'temp_c' => $this->tempC($s['temp'] ?? ''),
            'network' => $this->network($s['gateway'] ?? '', $s['dns'] ?? '', $s['netstat'] ?? '', $s),
        ];

        // Convenience for the list view: the fullest disk drives the status dot.
        $facts['disk_max_pct'] = $facts['disks'] === []
            ? null
            : max(array_map(static fn (array $d): float => $d['used_pct'], $facts['disks']));

        return $facts;
    }

    /**
     * Split on the `##LL:<key>` markers. Anything before the first marker (a
     * login banner, an MOTD) is discarded.
     *
     * @return array<string, string>
     */
    private function sections(string $output): array
    {
        $out = [];
        $key = null;
        $buf = [];
        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
            if (str_starts_with($line, '##LL:')) {
                if ($key !== null) {
                    $out[$key] = implode("\n", $buf);
                }
                $key = substr($line, 5);
                $buf = [];

                continue;
            }
            if ($key !== null) {
                $buf[] = $line;
            }
        }
        if ($key !== null) {
            $out[$key] = implode("\n", $buf);
        }

        return $out;
    }

    /** @return array{name:string|null,id:string|null,version:string|null} */
    private function osRelease(string $text): array
    {
        $kv = [];
        foreach (explode("\n", $text) as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $kv[trim($k)] = trim($v, " \t\"'");
        }

        return [
            'name' => $this->nullable($kv['PRETTY_NAME'] ?? $kv['NAME'] ?? ''),
            'id' => $this->nullable($kv['ID'] ?? ''),
            // Space-separated, most specific first. A derivative that we do not
            // know by name is still recognisable through what it is built on.
            'id_like' => $this->nullable($kv['ID_LIKE'] ?? ''),
            'version' => $this->nullable($kv['VERSION_ID'] ?? ''),
        ];
    }

    /**
     * `uname -s -r -m` → "Linux 6.1.0-18-amd64 x86_64". Kernel is the release,
     * which is what an operator compares against a pending update.
     *
     * @return array{0:string|null,1:string|null}
     */
    private function kernel(string $text): array
    {
        $parts = preg_split('/\s+/', trim($this->firstLine($text) ?? '')) ?: [];

        return [$this->nullable($parts[1] ?? ''), $this->nullable($parts[2] ?? '')];
    }

    private function uptime(string $text): ?int
    {
        $first = preg_split('/\s+/', trim($this->firstLine($text) ?? ''))[0] ?? '';

        return is_numeric($first) ? (int) round((float) $first) : null;
    }

    /** @return list<float> */
    private function load(string $text): array
    {
        $parts = preg_split('/\s+/', trim($this->firstLine($text) ?? '')) ?: [];
        $out = [];
        foreach (array_slice($parts, 0, 3) as $p) {
            if (is_numeric($p)) {
                $out[] = (float) $p;
            }
        }

        return $out;
    }

    /** @return array{cores:int|null,model:string|null} */
    /**
     * Cores and model from the first section; utilisation from two samples of
     * /proc/stat a second apart in the second.
     *
     * Utilisation has to be a delta — /proc/stat counts jiffies since boot, so a
     * single read gives the average since the machine started, which on a
     * long-lived host is a number that never moves. The load average already in
     * `load` is a different thing again: queue length, not busy time, and it
     * routinely exceeds 100% of the cores without the CPU being the bottleneck.
     *
     * @return array{cores:int|null,model:string|null,used_pct:float|null}
     */
    private function cpu(string $text, string $stat = ''): array
    {
        $lines = array_values(array_filter(explode("\n", trim($text)), static fn (string $l): bool => trim($l) !== ''));
        $cores = isset($lines[0]) && is_numeric(trim($lines[0])) ? (int) trim($lines[0]) : null;
        $model = null;
        foreach ($lines as $l) {
            if (str_contains($l, ':')) {
                $model = trim(explode(':', $l, 2)[1]);
                break;
            }
        }

        return [
            'cores' => $cores,
            'model' => $this->nullable((string) $model),
            'used_pct' => $this->cpuUsedPct($stat),
        ];
    }

    /**
     * Two `cpu ...` lines from /proc/stat. Busy is everything except idle and
     * iowait: a process waiting on disk is not the CPU working.
     */
    private function cpuUsedPct(string $text): ?float
    {
        $rows = [];
        foreach (explode("\n", trim($text)) as $line) {
            $f = preg_split('/\s+/', trim($line)) ?: [];
            if (($f[0] ?? '') !== 'cpu' || count($f) < 6) {
                continue;
            }
            $nums = array_map(static fn (string $v): int => (int) $v, array_slice($f, 1));
            $rows[] = $nums;
        }
        if (count($rows) < 2) {
            return null;
        }

        [$a, $b] = [$rows[0], $rows[count($rows) - 1]];
        $total = array_sum($b) - array_sum($a);
        // Fields 4 and 5 are idle and iowait.
        $idle = (($b[3] ?? 0) + ($b[4] ?? 0)) - (($a[3] ?? 0) + ($a[4] ?? 0));
        if ($total <= 0) {
            return null;
        }

        return round(max(0.0, min(100.0, (1 - $idle / $total) * 100)), 1);
    }

    /** @return array{total_kb:int|null,available_kb:int|null,used_pct:float|null,swap_total_kb:int|null,swap_used_kb:int|null} */
    private function meminfo(string $text): array
    {
        $kv = [];
        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', trim($line), $m) === 1) {
                $kv[$m[1]] = (int) $m[2];
            }
        }
        $total = $kv['MemTotal'] ?? null;
        // MemAvailable is the honest figure (free + reclaimable cache); MemFree
        // alone reads as "almost full" on any healthy machine.
        $avail = $kv['MemAvailable'] ?? ($kv['MemFree'] ?? null);
        $swapTotal = $kv['SwapTotal'] ?? null;
        $swapFree = $kv['SwapFree'] ?? null;

        return [
            'total_kb' => $total,
            'available_kb' => $avail,
            'used_pct' => ($total !== null && $total > 0 && $avail !== null)
                ? round((1 - $avail / $total) * 100, 1)
                : null,
            'swap_total_kb' => $swapTotal,
            'swap_used_kb' => ($swapTotal !== null && $swapFree !== null) ? $swapTotal - $swapFree : null,
        ];
    }

    /**
     * `df -P -k` output: Filesystem, 1024-blocks, Used, Available, Capacity, Mounted on.
     * The -P (POSIX) format is guaranteed one record per line, so a long device
     * name cannot wrap and shift the columns.
     *
     * Deduplicated by device, which is the whole difficulty: a Docker host lists
     * every overlay2 layer as its own line, all reporting the figures of the
     * filesystem underneath. Fifteen identical bars saying 399/502 GiB tell the
     * reader nothing and hide the mounts that matter, so each device appears
     * once, under its shortest mount point — the one a human would name.
     *
     * @return list<array{fs:string,mount:string,size_kb:int,used_kb:int,avail_kb:int,used_pct:float}>
     */
    private function disks(string $text): array
    {
        $byDevice = [];
        foreach (array_slice(explode("\n", trim($text)), 1) as $line) {
            $c = preg_split('/\s+/', trim($line), 6) ?: [];
            if (count($c) < 6 || ! is_numeric($c[1]) || ! is_numeric($c[2]) || ! is_numeric($c[3])) {
                continue;
            }
            $size = (int) $c[1];
            // Pseudo and stacked filesystems say nothing about the host's storage.
            if ($size <= 0 || in_array($c[0], self::PSEUDO_FILESYSTEMS, true)) {
                continue;
            }
            $used = (int) $c[2];
            $avail = (int) $c[3];
            // used/(used+avail), which is df's own Capacity column — NOT
            // used/size. The difference is the blocks reserved for root, and
            // using size would have this view disagree with what `df` prints on
            // the machine itself (79% here against df's 84%).
            $usable = $used + $avail;
            $entry = [
                'fs' => $c[0],
                'mount' => $c[5],
                'size_kb' => $size,
                'used_kb' => $used,
                'avail_kb' => $avail,
                'used_pct' => $usable > 0 ? round($used / $usable * 100, 1) : round($used / $size * 100, 1),
            ];

            $seen = $byDevice[$c[0]] ?? null;
            // Shortest mount wins: "/" over "/var/lib/docker/overlay2/<hash>".
            if ($seen === null || strlen($entry['mount']) < strlen($seen['mount'])) {
                $byDevice[$c[0]] = $entry;
            }
        }

        $out = array_values($byDevice);
        usort($out, static fn (array $a, array $b): int => strcmp($a['mount'], $b['mount']));

        return $out;
    }

    /** @return list<string> Interface + CIDR, e.g. "eth0 192.168.3.200/24". */
    private function addresses(string $text): array
    {
        $out = [];
        foreach (explode("\n", trim($text)) as $line) {
            $line = trim($line);
            if ($line !== '' && str_contains($line, ' ')) {
                $out[] = $line;
            }
        }

        return $out;
    }

    /** "none" on bare metal — reported as null, since that is not a hypervisor. */
    private function virt(string $text): ?string
    {
        $value = $this->firstLine($text);

        return ($value === null || $value === 'none') ? null : $value;
    }

    /** Boot wall-clock from /proc/stat's btime, as an ISO timestamp. */
    private function bootAt(string $text): ?string
    {
        $parts = preg_split('/\s+/', trim($this->firstLine($text) ?? '')) ?: [];
        $seconds = $parts[1] ?? '';

        return is_numeric($seconds) ? gmdate('c', (int) $seconds) : null;
    }

    /** @return list<string> */
    private function sessions(string $text): array
    {
        $out = [];
        foreach (explode("\n", trim($text)) as $line) {
            $c = preg_split('/\s+/', trim($line)) ?: [];
            if (($c[0] ?? '') !== '') {
                // "user pts/0 2026-08-21 19:30 (10.0.0.5)"
                $out[] = trim(implode(' ', array_slice($c, 0, 5)));
            }
        }

        return $out;
    }

    /**
     * The largest resident processes. Memory rather than CPU: a snapshot taken
     * every fifteen minutes cannot say anything honest about instantaneous CPU,
     * while resident size is a stable property worth seeing.
     *
     * @return list<array{name:string,rss_kb:int}>
     */
    private function processes(string $text): array
    {
        $out = [];
        foreach (explode("\n", trim($text)) as $line) {
            $c = preg_split('/\s+/', trim($line), 2) ?: [];
            if (count($c) === 2 && is_numeric($c[0]) && trim($c[1]) !== '') {
                $out[] = ['name' => trim($c[1]), 'rss_kb' => (int) $c[0]];
            }
        }

        return $out;
    }

    /** Thermal zone 0 reports millidegrees; anything absurd is treated as absent. */
    private function tempC(string $text): ?float
    {
        $raw = trim($this->firstLine($text) ?? '');
        if (! is_numeric($raw)) {
            return null;
        }
        $celsius = round((float) $raw / 1000, 1);

        return ($celsius > 0 && $celsius < 150) ? $celsius : null;
    }

    /**
     * Default route, resolvers, and per-interface byte counters.
     *
     * The counters are totals since boot, not a rate — two snapshots apart
     * would be needed for throughput, and calling a since-boot total "traffic"
     * would be the same mistake as reading /proc/stat once for CPU.
     *
     * @param  array<string,string>  $s  every section, for the per-interface detail
     * @return array{gateway:string|null,dns:list<string>,search:string|null,interfaces:list<array<string,mixed>>}
     */
    private function network(string $gateway, string $dns, string $netstat, array $s = []): array
    {
        $gw = null;
        foreach (explode("\n", trim($gateway)) as $line) {
            // "default via 192.168.3.1 dev eth0 ..."
            if (preg_match('/default via (\S+)/', $line, $m) === 1) {
                $gw = $m[1];
                break;
            }
        }

        $servers = [];
        $search = null;
        foreach (explode("\n", trim($dns)) as $line) {
            $f = preg_split('/\s+/', trim($line)) ?: [];
            if (($f[0] ?? '') === 'nameserver' && isset($f[1])) {
                $servers[] = $f[1];
            }
            if (in_array($f[0] ?? '', ['search', 'domain'], true) && isset($f[1])) {
                $search ??= implode(' ', array_slice($f, 1));
            }
        }

        $interfaces = [];
        foreach (explode("\n", trim($netstat)) as $line) {
            // "eth0: 1234 5 0 0 0 0 0 0 5678 ..." — receive first, transmit at 9.
            if (preg_match('/^\s*([^:]+):\s*(.+)$/', $line, $m) !== 1) {
                continue;
            }
            $name = trim($m[1]);
            if ($name === 'lo') {
                continue;
            }
            $cols = preg_split('/\s+/', trim($m[2])) ?: [];
            if (count($cols) < 10) {
                continue;
            }
            $interfaces[] = [
                'name' => $name,
                'rx_bytes' => (int) $cols[0],
                'tx_bytes' => (int) $cols[8],
            ];
        }

        return [
            'gateway' => $gw,
            'dns' => array_values(array_unique($servers)),
            'search' => $search,
            'interfaces' => $this->enrichInterfaces($interfaces, $s),
        ];
    }

    /**
     * Add to each interface what it actually is: kind (bridge, bond, vlan,
     * tunnel, physical), whether it is up, its addresses, the route it carries,
     * its MTU and MAC, and — where systemd-resolved is in charge — its own
     * resolvers.
     *
     * A Docker or libvirt host is mostly bridges and veth pairs, and a list that
     * calls them all "interface" says nothing about which one carries traffic.
     *
     * @param  list<array{name:string,rx_bytes:int,tx_bytes:int}>  $interfaces
     * @param  array<string,string>  $s
     * @return list<array<string,mixed>>
     */
    private function enrichInterfaces(array $interfaces, array $s): array
    {
        $bridges = [];
        $bonds = [];
        foreach (explode("\n", trim($s['bridges'] ?? '')) as $line) {
            // /sys/class/net/<name>/bridge/bridge_id, or .../bonding/mode
            if (preg_match('#/sys/class/net/([^/]+)/(bridge|bonding)/#', trim($line), $m) === 1) {
                if ($m[2] === 'bridge') {
                    $bridges[] = $m[1];
                } else {
                    $bonds[] = $m[1];
                }
            }
        }

        // "2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 ... link/ether aa:bb ..."
        $link = [];
        foreach (explode("\n", trim($s['iflink'] ?? '')) as $line) {
            if (preg_match('/^\d+:\s*([^:@]+)[@:]/', trim($line), $m) !== 1) {
                continue;
            }
            $name = trim($m[1]);
            $up = str_contains($line, ',UP') || str_contains($line, '<UP');
            $mtu = preg_match('/mtu (\d+)/', $line, $mm) === 1 ? (int) $mm[1] : null;
            $mac = preg_match('#link/\w+ ([0-9a-f:]{17})#', $line, $ma) === 1 ? $ma[1] : null;
            $link[$name] = ['up' => $up, 'mtu' => $mtu, 'mac' => $mac];
        }

        // "2: eth0    inet 192.168.3.5/24 brd ... scope global eth0"
        $addrs = [];
        foreach (explode("\n", trim($s['ifaddr'] ?? '')) as $line) {
            $f = preg_split('/\s+/', trim($line)) ?: [];
            if (count($f) < 4 || ! in_array($f[2], ['inet', 'inet6'], true)) {
                continue;
            }
            $name = rtrim($f[1], ':');
            // Link-local v6 is on every interface and tells a reader nothing.
            if ($f[2] === 'inet6' && str_starts_with($f[3], 'fe80')) {
                continue;
            }
            $addrs[$name][] = $f[3];
        }

        // Per-interface default route, which is what "gateway" means on a host
        // with more than one uplink.
        $gateways = [];
        foreach (explode("\n", trim($s['routes'] ?? '')) as $line) {
            if (preg_match('/^default via (\S+) dev (\S+)/', trim($line), $m) === 1) {
                $gateways[$m[2]] ??= $m[1];
            }
        }

        // "Link 2 (eth0): 192.168.3.1 1.1.1.1"
        $resolvers = [];
        $currentLink = null;
        foreach (explode("\n", trim($s['resolved'] ?? '')) as $line) {
            if (preg_match('/^Link \d+ \(([^)]+)\):(.*)$/', trim($line), $m) === 1) {
                $currentLink = $m[1];
                $found = preg_split('/\s+/', trim($m[2])) ?: [];
                $resolvers[$currentLink] = array_values(array_filter($found, static fn (string $v): bool => $v !== ''));
            }
        }

        $out = [];
        foreach ($interfaces as $i) {
            $name = $i['name'];
            $out[] = $i + [
                'kind' => $this->interfaceKind($name, $bridges, $bonds),
                'up' => $link[$name]['up'] ?? null,
                'mtu' => $link[$name]['mtu'] ?? null,
                'mac' => $link[$name]['mac'] ?? null,
                'addresses' => $addrs[$name] ?? [],
                'gateway' => $gateways[$name] ?? null,
                'dns' => $resolvers[$name] ?? [],
            ];
        }

        return $out;
    }

    /**
     * What kind of interface this is. Named from the kernel where it says so
     * (a bridge has a bridge/ directory), from the name where it does not —
     * veth, tun and wireguard interfaces are conventional enough to read.
     *
     * @param  list<string>  $bridges
     * @param  list<string>  $bonds
     */
    private function interfaceKind(string $name, array $bridges, array $bonds): string
    {
        if (in_array($name, $bridges, true)) {
            return 'bridge';
        }
        if (in_array($name, $bonds, true)) {
            return 'bond';
        }
        if (str_contains($name, '.')) {
            return 'vlan';
        }
        if (str_starts_with($name, 'veth')) {
            return 'veth';
        }
        if (str_starts_with($name, 'docker') || str_starts_with($name, 'br-')) {
            return 'bridge';
        }
        if (str_starts_with($name, 'wg')) {
            return 'wireguard';
        }
        if (str_starts_with($name, 'tun') || str_starts_with($name, 'tap')) {
            return 'tunnel';
        }
        if (str_starts_with($name, 'wl')) {
            return 'wireless';
        }

        return 'ethernet';
    }

    /** @return list<string> */
    private function failedUnits(string $text): array
    {
        $out = [];
        foreach (explode("\n", trim($text)) as $line) {
            $unit = preg_split('/\s+/', trim($line))[0] ?? '';
            if ($unit !== '' && str_contains($unit, '.')) {
                $out[] = $unit;
            }
        }

        return $out;
    }

    /**
     * `ss -H -ltn` columns: State Recv-Q Send-Q Local:Port Peer:Port.
     *
     * @return list<string>
     */
    private function ports(string $text): array
    {
        $out = [];
        foreach (explode("\n", trim($text)) as $line) {
            $c = preg_split('/\s+/', trim($line)) ?: [];
            if (isset($c[3]) && str_contains($c[3], ':')) {
                $out[] = $c[3];
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<array{name:string,status:string}> */
    private function containers(string $text): array
    {
        $out = [];
        foreach (explode("\n", trim($text)) as $line) {
            if (! str_contains($line, '|')) {
                continue;
            }
            [$name, $status] = explode('|', trim($line), 2);
            if (trim($name) !== '') {
                $out[] = ['name' => trim($name), 'status' => trim($status)];
            }
        }

        return $out;
    }

    /** Pending package updates, or null where no supported package manager answered. */
    private function updates(string $text): ?int
    {
        foreach (explode("\n", trim($text)) as $line) {
            $line = trim($line);
            if ($line !== '' && ctype_digit($line)) {
                return (int) $line;
            }
        }

        return null;
    }

    private function firstLine(string $text): ?string
    {
        foreach (explode("\n", $text) as $line) {
            if (trim($line) !== '') {
                return trim($line);
            }
        }

        return null;
    }

    private function nullable(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
