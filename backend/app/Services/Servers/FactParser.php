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
            'cpu' => $this->cpu($s['cpu'] ?? ''),
            'mem' => $mem,
            'disks' => $this->disks($s['disk'] ?? ''),
            'reboot_required' => trim($s['reboot'] ?? '') === 'yes',
            'failed_units' => $this->failedUnits($s['failed'] ?? ''),
            'ports' => $this->ports($s['ports'] ?? ''),
            'containers' => $this->containers($s['containers'] ?? ''),
            'updates' => $this->updates($s['updates'] ?? ''),
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
    private function cpu(string $text): array
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

        return ['cores' => $cores, 'model' => $this->nullable((string) $model)];
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
     * @return list<array{fs:string,mount:string,size_kb:int,used_kb:int,avail_kb:int,used_pct:float}>
     */
    private function disks(string $text): array
    {
        $out = [];
        foreach (array_slice(explode("\n", trim($text)), 1) as $line) {
            $c = preg_split('/\s+/', trim($line), 6) ?: [];
            if (count($c) < 6 || ! is_numeric($c[1]) || ! is_numeric($c[2]) || ! is_numeric($c[3])) {
                continue;
            }
            $size = (int) $c[1];
            // Pseudo filesystems say nothing about the host's storage.
            if ($size <= 0 || in_array($c[0], ['tmpfs', 'devtmpfs', 'udev', 'none'], true)) {
                continue;
            }
            $used = (int) $c[2];
            $out[] = [
                'fs' => $c[0],
                'mount' => $c[5],
                'size_kb' => $size,
                'used_kb' => $used,
                'avail_kb' => (int) $c[3],
                'used_pct' => round($used / $size * 100, 1),
            ];
        }

        return $out;
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
