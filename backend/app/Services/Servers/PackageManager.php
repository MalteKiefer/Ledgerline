<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * Which updates are waiting, and applying them.
 *
 * The snapshot counts them. A count is not enough to decide anything: fourteen
 * pending updates might be a kernel and thirteen fonts, or thirteen fonts and
 * one remote hole. Security-relevant ones are therefore separated from the
 * rest, and the list itself is available rather than just its length.
 *
 * Applying them is the same boundary as restarting a service — anything here
 * could be typed into the terminal that has existed since v1.702.0. It is
 * audited, it names a fixed command per package manager, and nothing from the
 * request becomes part of that command.
 */
final class PackageManager
{
    /** apt and apk only. dnf and zypper differ enough to deserve their own work. */
    private const LIST_SCRIPT = <<<'SH'
    if command -v apt-get >/dev/null 2>&1; then
      echo "##LL:kind"; echo apt
      echo "##LL:list"; apt-get -s -o Debug::NoLocking=1 upgrade 2>/dev/null | grep '^Inst ' | head -200
    elif command -v apk >/dev/null 2>&1; then
      echo "##LL:kind"; echo apk
      echo "##LL:list"; apk version -l '<' 2>/dev/null | tail -n +2 | head -200
    else
      echo "##LL:kind"; echo none
    fi
    echo "##LL:end"
    SH;

    /**
     * Non-interactive on purpose, and only what is already configured: this
     * never adds a repository, never changes a source, and never removes a
     * package to satisfy a dependency.
     */
    private const APPLY_APT = 'DEBIAN_FRONTEND=noninteractive apt-get -y -o Dpkg::Options::=--force-confold upgrade 2>&1 | tail -60';

    private const APPLY_APK = 'apk upgrade 2>&1 | tail -60';

    /** Applying updates takes minutes; it never runs inline. */
    public const APPLY_TIMEOUT = 1800;

    public function __construct(private ServerProbe $probe) {}

    /**
     * The pending updates, split by whether they come from a security source.
     *
     * @return array{ok:bool,kind:string,packages:list<array{name:string,version:string,current:string,security:bool}>,error:string|null}
     */
    public function pending(Server $server): array
    {
        $out = $this->run($server, self::LIST_SCRIPT, 45);
        if ($out === null) {
            return ['ok' => false, 'kind' => 'unknown', 'packages' => [], 'error' => 'unreachable'];
        }

        $sections = $this->sections($out);
        $kind = trim($sections['kind'] ?? '');
        if ($kind === '' || $kind === 'none') {
            return ['ok' => true, 'kind' => 'none', 'packages' => [], 'error' => null];
        }

        $packages = $kind === 'apt'
            ? $this->parseApt($sections['list'] ?? '')
            : $this->parseApk($sections['list'] ?? '');

        // Security first: that is the order somebody deciding whether to apply
        // them now rather than at the weekend wants to read.
        usort($packages, static fn (array $a, array $b): int => ($b['security'] <=> $a['security']) ?: strcmp($a['name'], $b['name']));

        return ['ok' => true, 'kind' => $kind, 'packages' => $packages, 'error' => null];
    }

    /**
     * Apply everything pending. Long-running, so this belongs in a job.
     *
     * @return array{ok:bool,output:string,error:string|null}
     */
    public function apply(Server $server): array
    {
        $probeResult = $this->run($server, 'command -v apt-get >/dev/null 2>&1 && echo apt || (command -v apk >/dev/null 2>&1 && echo apk || echo none)', 20);
        $kind = trim((string) $probeResult);

        $script = match ($kind) {
            'apt' => self::APPLY_APT,
            'apk' => self::APPLY_APK,
            default => null,
        };

        if ($script === null) {
            return ['ok' => false, 'output' => '', 'error' => 'no_package_manager'];
        }

        $key = (string) $server->host_key;
        if ($key === '') {
            return ['ok' => false, 'output' => '', 'error' => 'no_host_key'];
        }

        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, $script, timeout: self::APPLY_TIMEOUT);

        return [
            'ok' => $result['ok'],
            'output' => substr($result['out'], 0, 64 * 1024),
            'error' => $result['ok'] ? null : 'command_failed',
        ];
    }

    /**
     * `Inst nginx [1.24] (1.26 Debian:13/stable-security [amd64])`
     *
     * The origin in brackets is what says whether it is a security update; the
     * package name alone never does.
     *
     * @return list<array{name:string,version:string,current:string,security:bool}>
     */
    private function parseApt(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            if (! preg_match('/^Inst\s+(\S+)\s+(?:\[([^\]]*)\]\s+)?\(([^\s)]+)\s*(.*)\)/', trim($line), $m)) {
                continue;
            }
            $out[] = [
                'name' => $m[1],
                'current' => $m[2],
                'version' => $m[3],
                'security' => stripos($m[4], 'security') !== false,
            ];
        }

        return $out;
    }

    /**
     * `nginx-1.24.0-r7 < 1.26.2-r0`
     *
     * Alpine does not label a security source in this output, so nothing here
     * is marked as one — guessing from the package name would be worse than
     * saying nothing.
     *
     * @return list<array{name:string,version:string,current:string,security:bool}>
     */
    private function parseApk(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            $parts = preg_split('/\s*<\s*/', trim($line)) ?: [];
            if (count($parts) < 2 || $parts[0] === '') {
                continue;
            }
            // apk glues the installed version onto the name (zlib-1.2.13-r0).
            // Splitting on the last dash-then-digit separates them; a package
            // name may itself contain dashes, so counting them would not.
            $left = trim($parts[0]);
            $name = $left;
            $current = '';
            if (preg_match('/^(.+)-(\d[^-]*-r\d+)$/', $left, $m) === 1) {
                $name = $m[1];
                $current = $m[2];
            }
            $out[] = ['name' => $name, 'current' => $current, 'version' => trim($parts[1]), 'security' => false];
        }

        return $out;
    }

    private function run(Server $server, string $script, int $timeout): ?string
    {
        $key = (string) $server->host_key;
        if ($key === '') {
            return null;
        }

        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, $script, timeout: $timeout);
        if (! $result['ok'] && $result['out'] === '') {
            return null;
        }

        return substr($result['out'], 0, 256 * 1024);
    }

    /** @return array<string,string> */
    private function sections(string $raw): array
    {
        $out = [];
        $current = null;
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            if (str_starts_with($line, '##LL:')) {
                $current = substr($line, 5);
                $out[$current] = '';

                continue;
            }
            if ($current !== null) {
                $out[$current] .= $line."\n";
            }
        }

        return $out;
    }
}
