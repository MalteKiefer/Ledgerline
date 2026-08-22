<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * Read and act on the Docker engine of a monitored host.
 *
 * Everything is asked in one round trip and split by markers, because a tab
 * that opens with six sequential SSH calls feels broken even when each one is
 * fast. Container stats come from `docker stats --no-stream`, which is a real
 * sample rather than a counter since boot — the distinction that made the CPU
 * figure honest elsewhere in this module.
 *
 * Acting is deliberately narrower than the CLI: a fixed verb set, container
 * names matched against a strict pattern, and no `exec` or `run`. The terminal
 * exists for anything beyond that, and it asks for the password.
 */
class DockerInspector
{
    /** What may be asked of a container. */
    public const ACTIONS = ['start', 'stop', 'restart', 'pause', 'unpause', 'kill', 'remove'];

    /** What may be pruned. Nothing here touches a running container. */
    public const PRUNE_TARGETS = ['images', 'containers', 'volumes', 'networks', 'builder'];

    /** Container and object names as Docker allows them. */
    private const NAME_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9_.\-]{0,127}$/';

    public function __construct(private ServerProbe $probe) {}

    /**
     * Everything the Docker tab shows, in one round trip.
     *
     * @return array{ok:bool,present:bool,version:string,containers:list<array<string,mixed>>,images:list<array<string,mixed>>,volumes:list<array<string,mixed>>,networks:list<array<string,mixed>>,disk:list<array<string,mixed>>,compose:list<string>,error:string|null}
     */
    public function inspect(Server $server): array
    {
        $script = <<<'SH'
        echo "##LL:version"
        command -v docker >/dev/null 2>&1 && docker version --format '{{.Server.Version}}' 2>&1 || echo "__absent__"
        echo "##LL:ps"
        docker ps -a --format '{{.ID}}\t{{.Names}}\t{{.Image}}\t{{.State}}\t{{.Status}}\t{{.Ports}}\t{{.RunningFor}}' 2>/dev/null | head -300
        echo "##LL:stats"
        docker stats --no-stream --format '{{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.NetIO}}\t{{.BlockIO}}' 2>/dev/null | head -300
        echo "##LL:images"
        docker images --format '{{.Repository}}:{{.Tag}}\t{{.ID}}\t{{.Size}}\t{{.CreatedSince}}' 2>/dev/null | head -300
        echo "##LL:volumes"
        docker volume ls --format '{{.Name}}\t{{.Driver}}' 2>/dev/null | head -300
        echo "##LL:networks"
        docker network ls --format '{{.Name}}\t{{.Driver}}\t{{.Scope}}' 2>/dev/null | head -100
        echo "##LL:df"
        docker system df 2>/dev/null | tail -n +2
        echo "##LL:compose"
        docker compose ls --format json 2>/dev/null | head -20
        echo "##LL:health"
        for c in $(docker ps -q 2>/dev/null | head -100); do
          docker inspect --format '{{.Name}}\t{{if .State.Health}}{{.State.Health.Status}}{{else}}-{{end}}\t{{.RestartCount}}' "$c" 2>/dev/null
        done
        echo "##LL:end"
        SH;

        $out = $this->run($server, $script, 120);
        if ($out === null) {
            return self::empty('unreachable');
        }

        $s = $this->sections($out);
        $version = trim($s['version'] ?? '');
        if ($version === '' || str_contains($version, '__absent__')) {
            return self::empty(null) + ['present' => false];
        }
        // A permission error is not an absent engine, and reporting it as one
        // would send somebody looking for a package that is already installed.
        if (stripos($version, 'permission denied') !== false || stripos($version, 'cannot connect') !== false) {
            return array_replace(self::empty('no_access'), ['present' => true]);
        }

        $stats = $this->parseStats($s['stats'] ?? '');
        $health = $this->parseHealth($s['health'] ?? '');

        return [
            'ok' => true,
            'present' => true,
            'version' => $version,
            'containers' => $this->parseContainers($s['ps'] ?? '', $stats, $health),
            'images' => $this->parseImages($s['images'] ?? ''),
            'volumes' => $this->parseVolumes($s['volumes'] ?? ''),
            'networks' => $this->parseNetworks($s['networks'] ?? ''),
            'disk' => $this->parseDiskUsage($s['df'] ?? ''),
            'compose' => $this->parseCompose($s['compose'] ?? ''),
            'error' => null,
        ];
    }

    /**
     * Act on one container.
     *
     * @return array{ok:bool,output:string,error:string|null}
     */
    public function act(Server $server, string $name, string $action): array
    {
        if (! in_array($action, self::ACTIONS, true) || preg_match(self::NAME_PATTERN, $name) !== 1) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_selection'];
        }

        // `remove` is `rm`, and it refuses a running container rather than
        // taking it down first: stopping is a separate decision and should be
        // a separate click.
        $verb = $action === 'remove' ? 'rm' : $action;

        $out = $this->run($server, 'docker '.$verb.' '.self::shq($name).' 2>&1; echo "##LL:rc=$?"');
        if ($out === null) {
            return ['ok' => false, 'output' => '', 'error' => 'unreachable'];
        }

        return $this->outcome($out);
    }

    /**
     * Reclaim space.
     *
     * Never `-a` and never with volumes unless volumes were asked for: the
     * difference between "unused since nothing references it" and "unused since
     * nothing is running" is a day's worth of data.
     *
     * @return array{ok:bool,output:string,error:string|null}
     */
    public function prune(Server $server, string $target): array
    {
        if (! in_array($target, self::PRUNE_TARGETS, true)) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_selection'];
        }

        $out = $this->run($server, 'docker '.$target.' prune -f 2>&1; echo "##LL:rc=$?"', 600);
        if ($out === null) {
            return ['ok' => false, 'output' => '', 'error' => 'unreachable'];
        }

        return $this->outcome($out);
    }

    /**
     * @param  array<string,array<string,string>>  $stats
     * @param  array<string,array<string,string>>  $health
     * @return list<array<string,mixed>>
     */
    private function parseContainers(string $raw, array $stats, array $health): array
    {
        $rows = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if (count($f) < 5) {
                continue;
            }
            $name = $f[1];
            $rows[] = [
                'id' => substr($f[0], 0, 12),
                'name' => $name,
                'image' => $f[2],
                'state' => $f[3],
                'status' => $f[4],
                'ports' => $this->parsePorts($f[5] ?? ''),
                'created' => $f[6] ?? '',
                'cpu' => $stats[$name]['cpu'] ?? null,
                'mem' => $stats[$name]['mem'] ?? null,
                'mem_pct' => $stats[$name]['mem_pct'] ?? null,
                'net' => $stats[$name]['net'] ?? null,
                'block' => $stats[$name]['block'] ?? null,
                // "-" means the image declares no healthcheck, which is not the
                // same as unhealthy and must not be shown as a warning.
                'health' => $health[$name]['health'] ?? null,
                'restarts' => isset($health[$name]) ? (int) $health[$name]['restarts'] : null,
            ];
        }

        return $rows;
    }

    /**
     * Published ports, one entry per mapping.
     *
     * @return list<string>
     */
    private function parsePorts(string $raw): array
    {
        $out = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out;
    }

    /** @return array<string,array<string,string>> */
    private function parseStats(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if (count($f) < 4) {
                continue;
            }
            $out[$f[0]] = [
                'cpu' => $f[1],
                'mem' => $f[2],
                'mem_pct' => $f[3],
                'net' => $f[4] ?? '',
                'block' => $f[5] ?? '',
            ];
        }

        return $out;
    }

    /** @return array<string,array<string,string>> */
    private function parseHealth(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if (count($f) < 3) {
                continue;
            }
            // docker inspect prints the name with a leading slash.
            $out[ltrim($f[0], '/')] = ['health' => $f[1] === '-' ? '' : $f[1], 'restarts' => $f[2]];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function parseImages(string $raw): array
    {
        $rows = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if (count($f) < 3) {
                continue;
            }
            $rows[] = ['name' => $f[0], 'id' => substr($f[1], 0, 12), 'size' => $f[2], 'created' => $f[3] ?? ''];
        }

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function parseVolumes(string $raw): array
    {
        $rows = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if ($f[0] === '') {
                continue;
            }
            $rows[] = ['name' => $f[0], 'driver' => $f[1] ?? ''];
        }

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function parseNetworks(string $raw): array
    {
        $rows = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if ($f[0] === '') {
                continue;
            }
            $rows[] = ['name' => $f[0], 'driver' => $f[1] ?? '', 'scope' => $f[2] ?? ''];
        }

        return $rows;
    }

    /**
     * `docker system df` as a table.
     *
     * Parsed from the plain output rather than --format: the format string
     * differs per row type, so one template cannot render all four rows.
     *
     * @return list<array<string,mixed>>
     */
    private function parseDiskUsage(string $raw): array
    {
        $rows = [];
        foreach ($this->lines($raw) as $line) {
            // "Images          31        12        14.2GB    8.1GB (57%)"
            if (preg_match('/^(\S+(?:\s\S+)?)\s+(\d+)\s+(\d+)\s+(\S+)\s+(.+)$/', trim($line), $m) !== 1) {
                continue;
            }
            $rows[] = [
                'type' => trim($m[1]),
                'total' => (int) $m[2],
                'active' => (int) $m[3],
                'size' => $m[4],
                'reclaimable' => trim($m[5]),
            ];
        }

        return $rows;
    }

    /** @return list<string> */
    private function parseCompose(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $project) {
            if (is_array($project) && is_scalar($project['Name'] ?? null)) {
                $out[] = (string) $project['Name'];
            }
        }

        return $out;
    }

    /**
     * @return array{ok:bool,present:bool,version:string,containers:list<array<string,mixed>>,images:list<array<string,mixed>>,volumes:list<array<string,mixed>>,networks:list<array<string,mixed>>,disk:list<array<string,mixed>>,compose:list<string>,error:string|null}
     */
    private static function empty(?string $error): array
    {
        return [
            'ok' => $error === null, 'present' => false, 'version' => '',
            'containers' => [], 'images' => [], 'volumes' => [], 'networks' => [],
            'disk' => [], 'compose' => [], 'error' => $error,
        ];
    }

    /** @return list<string> */
    private function lines(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            if (trim($line) !== '') {
                $out[] = rtrim($line);
            }
        }

        return $out;
    }

    /** @return array{ok:bool,output:string,error:string|null} */
    private function outcome(string $out): array
    {
        $rc = 0;
        if (preg_match('/##LL:rc=(\d+)\s*$/', $out, $m) === 1) {
            $rc = (int) $m[1];
            $out = (string) preg_replace('/##LL:rc=\d+\s*$/', '', $out);
        }

        return ['ok' => $rc === 0, 'output' => trim($out), 'error' => $rc === 0 ? null : 'command_failed'];
    }

    /** @return array<string,string> */
    private function sections(string $output): array
    {
        $out = [];
        $current = null;
        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
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

    /** Single-quote for POSIX sh — the script runs on the target, not here. */
    private static function shq(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    private function run(Server $server, string $script, int $timeout = 60): ?string
    {
        $key = (string) $server->host_key;
        if ($key === '') {
            return null;
        }

        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, $script, interactive: true);
        if (! $result['ok'] && $result['out'] === '') {
            return null;
        }

        return substr($result['out'], 0, 512 * 1024);
    }
}
