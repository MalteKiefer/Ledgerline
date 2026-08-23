<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * Which hosting control panel runs a machine, if any.
 *
 * A panel owns the host: it writes the web server configuration, the mail
 * users, the certificates and the firewall. Knowing one is there changes what
 * every other tab means — a service edited by hand on a Plesk box is undone by
 * the panel at its next write.
 *
 * Two kinds of answer. The named panels are recognised by their own binary or
 * install path and asked for a version; that is a fact. Everything else is a
 * *candidate*: something is listening on a port panels commonly use, and it is
 * labelled as a guess rather than dressed up as a detection — a port is not an
 * identity.
 *
 * Nothing here prints a credential. `bt default` and `plesk bin admin
 * --get-login-link` both hand out working access, so neither is run: a panel
 * inventory that leaks the way in is worse than no inventory.
 */
final class PanelInspector
{
    private const TIMEOUT = 40;

    /**
     * One round trip. Each section says plainly when its panel is absent,
     * because an empty section cannot tell "not installed" from "installed but
     * unreadable", and those two deserve opposite reactions.
     */
    private const SCRIPT = <<<'SH'
    printf "\n##LL:plesk\n"; command -v plesk >/dev/null 2>&1 && plesk version 2>/dev/null | head -6 || echo "__absent__"
    printf "\n##LL:plesk_counts\n"; if command -v plesk >/dev/null 2>&1; then printf "domains=%s\n" "$(plesk bin domain --list 2>/dev/null | grep -c .)"; printf "subscriptions=%s\n" "$(plesk bin subscription --list 2>/dev/null | grep -c .)"; printf "customers=%s\n" "$(plesk bin customer --list 2>/dev/null | grep -c .)"; else echo "__absent__"; fi
    printf "\n##LL:cpanel\n"; [ -x /usr/local/cpanel/cpanel ] && /usr/local/cpanel/cpanel -V 2>/dev/null || echo "__absent__"
    printf "\n##LL:cpanel_users\n"; [ -d /var/cpanel/users ] && ls -1 /var/cpanel/users 2>/dev/null | grep -c . || echo "__absent__"
    printf "\n##LL:directadmin\n"; [ -x /usr/local/directadmin/directadmin ] && /usr/local/directadmin/directadmin v 2>/dev/null | head -3 || echo "__absent__"
    printf "\n##LL:directadmin_users\n"; [ -d /usr/local/directadmin/data/users ] && ls -1 /usr/local/directadmin/data/users 2>/dev/null | grep -c . || echo "__absent__"
    printf "\n##LL:ispconfig\n"; [ -f /usr/local/ispconfig/interface/lib/config.inc.php ] && grep -m1 "ISPC_APP_VERSION" /usr/local/ispconfig/interface/lib/config.inc.php 2>/dev/null || echo "__absent__"
    printf "\n##LL:webmin\n"; [ -f /etc/webmin/version ] && cat /etc/webmin/version 2>/dev/null || echo "__absent__"
    printf "\n##LL:webmin_conf\n"; [ -f /etc/webmin/miniserv.conf ] && grep -E "^(port|ssl)=" /etc/webmin/miniserv.conf 2>/dev/null || echo "__absent__"
    printf "\n##LL:virtualmin\n"; [ -f /etc/webmin/virtual-server/version ] && cat /etc/webmin/virtual-server/version 2>/dev/null || echo "__absent__"
    printf "\n##LL:virtualmin_domains\n"; [ -d /etc/webmin/virtual-server/domains ] && ls -1 /etc/webmin/virtual-server/domains 2>/dev/null | grep -c . || echo "__absent__"
    printf "\n##LL:cyberpanel\n"; if [ -d /usr/local/CyberCP ]; then cat /usr/local/CyberCP/version.txt 2>/dev/null || echo "installed"; else echo "__absent__"; fi
    printf "\n##LL:hestia\n"; [ -f /usr/local/hestia/conf/hestia.conf ] && grep -E "^VERSION=" /usr/local/hestia/conf/hestia.conf 2>/dev/null || echo "__absent__"
    printf "\n##LL:hestia_users\n"; [ -d /usr/local/hestia/data/users ] && ls -1 /usr/local/hestia/data/users 2>/dev/null | grep -c . || echo "__absent__"
    printf "\n##LL:vesta\n"; [ -f /usr/local/vesta/conf/vesta.conf ] && grep -E "^VERSION=" /usr/local/vesta/conf/vesta.conf 2>/dev/null || echo "__absent__"
    printf "\n##LL:aapanel\n"; if [ -d /www/server/panel ]; then cat /www/server/panel/config/version.pl 2>/dev/null || echo "installed"; else echo "__absent__"; fi
    printf "\n##LL:aapanel_port\n"; [ -f /www/server/panel/data/port.pl ] && cat /www/server/panel/data/port.pl 2>/dev/null || echo "__absent__"
    printf "\n##LL:cloudpanel\n"; if command -v clpctl >/dev/null 2>&1; then clpctl --version 2>/dev/null | head -2 || echo "installed"; elif [ -d /home/clp ]; then echo "installed"; else echo "__absent__"; fi
    printf "\n##LL:froxlor\n"; F=""; for d in /var/www/froxlor /var/www/html/froxlor /usr/share/froxlor; do if [ -d "$d" ]; then F="$d"; fi; done; if [ -n "$F" ]; then echo "$F"; else echo "__absent__"; fi
    printf "\n##LL:keyhelp\n"; if [ -d /usr/local/keyhelp ]; then cat /usr/local/keyhelp/www/keyhelp/version 2>/dev/null || echo "installed"; else echo "__absent__"; fi
    printf "\n##LL:cockpit\n"; command -v cockpit-bridge >/dev/null 2>&1 && cockpit-bridge --version 2>/dev/null | head -2 || echo "__absent__"
    printf "\n##LL:runcloud\n"; if [ -d /etc/runcloud ]; then echo "installed"; else echo "__absent__"; fi
    printf "\n##LL:serverpilot\n"; if [ -d /etc/serverpilot ]; then echo "installed"; else echo "__absent__"; fi
    printf "\n##LL:units\n"; systemctl list-units --type=service --all --no-legend --plain 2>/dev/null | awk '{print $1" "$3" "$4}' | grep -Ei 'psa|sw-cp|cpanel|cpsrvd|directadmin|ispconfig|webmin|usermin|lscpd|lshttpd|hestia|vesta|aapanel|cockpit|froxlor|keyhelp|runcloud|serverpilot' | head -30 || echo "__absent__"
    printf "\n##LL:containers\n"; command -v docker >/dev/null 2>&1 && docker ps --format '{{.Names}}\t{{.Image}}\t{{.Ports}}' 2>/dev/null | grep -Ei 'portainer|coolify|caprover|cloudron|yunohost|easypanel|dokploy|proxy-manager' | head -20 || echo "__absent__"
    printf "\n##LL:listen\n"; if command -v ss >/dev/null 2>&1; then ss -H -ltnp 2>/dev/null; else echo "__absent__"; fi
    SH;

    public function __construct(private ServerProbe $probe) {}

    /**
     * Every panel this host has, plus what merely looks like one.
     *
     * @return array{ok:bool,panels:list<array<string,mixed>>,candidates:list<array<string,string|int|null>>,error:string|null}
     */
    public function inspect(Server $server): array
    {
        $key = (string) $server->host_key;
        if ($key === '') {
            return ['ok' => false, 'panels' => [], 'candidates' => [], 'error' => 'no_host_key'];
        }

        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, self::SCRIPT, timeout: self::TIMEOUT);
        if (! $result['ok'] && $result['out'] === '') {
            return ['ok' => false, 'panels' => [], 'candidates' => [], 'error' => 'unreachable'];
        }

        $s = $this->sections(substr($result['out'], 0, 512 * 1024));
        $units = $this->units($s['units'] ?? '');
        $listen = $this->listeners($s['listen'] ?? '');

        $panels = array_merge(
            $this->detect($s, $units, $listen),
            $this->fromContainers($s['containers'] ?? ''),
        );

        return [
            'ok' => true,
            'panels' => $panels,
            'candidates' => $this->candidates($listen, $panels, (int) $server->port),
            'error' => null,
        ];
    }

    /**
     * The named panels, in the order somebody would care about them: the ones
     * that own the whole machine before the ones that manage a corner of it.
     *
     * @param  array<string,string>  $s
     * @param  array<string,array{state:string,sub:string}>  $units
     * @param  list<array{port:int,address:string,process:string|null}>  $listen
     * @return list<array<string,mixed>>
     */
    private function detect(array $s, array $units, array $listen): array
    {
        $out = [];

        if (! $this->missing($s['plesk'] ?? '')) {
            $out[] = $this->panel('plesk', 'Plesk', [
                'version' => $this->firstMatch($s['plesk'] ?? '', '/Product version:\s*(.+)$/mi') ?? $this->firstLine($s['plesk'] ?? ''),
                'facts' => array_filter([
                    'os' => $this->firstMatch($s['plesk'] ?? '', '/OS version:\s*(.+)$/mi'),
                    'build' => $this->firstMatch($s['plesk'] ?? '', '/Build date:\s*(.+)$/mi'),
                    'revision' => $this->firstMatch($s['plesk'] ?? '', '/Revision:\s*(.+)$/mi'),
                ]),
                'counts' => $this->numbers($this->pairs($s['plesk_counts'] ?? '')),
                'unit' => $this->pickUnit($units, ['psa.service', 'sw-cp-server.service']),
                'ports' => $this->portsOf($listen, [8443, 8880]),
                'path' => '/usr/local/psa',
            ], $units);
        }

        if (! $this->missing($s['cpanel'] ?? '')) {
            $out[] = $this->panel('cpanel', 'cPanel / WHM', [
                'version' => $this->firstLine($s['cpanel'] ?? ''),
                'counts' => $this->countOf('accounts', $s['cpanel_users'] ?? ''),
                'unit' => $this->pickUnit($units, ['cpanel.service', 'cpsrvd.service']),
                'ports' => $this->portsOf($listen, [2082, 2083, 2086, 2087, 2095, 2096]),
                'path' => '/usr/local/cpanel',
            ], $units);
        }

        if (! $this->missing($s['directadmin'] ?? '')) {
            $out[] = $this->panel('directadmin', 'DirectAdmin', [
                'version' => $this->firstLine($s['directadmin'] ?? ''),
                'counts' => $this->countOf('users', $s['directadmin_users'] ?? ''),
                'unit' => $this->pickUnit($units, ['directadmin.service']),
                'ports' => $this->portsOf($listen, [2222]),
                'path' => '/usr/local/directadmin',
            ], $units);
        }

        if (! $this->missing($s['ispconfig'] ?? '')) {
            $out[] = $this->panel('ispconfig', 'ISPConfig', [
                'version' => $this->firstMatch($s['ispconfig'] ?? '', "/ISPC_APP_VERSION'?\s*,\s*'([^']+)'/"),
                'unit' => null,
                'ports' => $this->portsOf($listen, [8080, 8081]),
                'path' => '/usr/local/ispconfig',
            ], $units);
        }

        // Virtualmin is a Webmin module, so one install is reported as one
        // panel: saying "two panels" for a single interface would be wrong.
        $hasVirtualmin = ! $this->missing($s['virtualmin'] ?? '');
        if ($hasVirtualmin || ! $this->missing($s['webmin'] ?? '')) {
            $conf = $this->pairs($s['webmin_conf'] ?? '');
            $port = isset($conf['port']) && is_numeric($conf['port']) ? (int) $conf['port'] : 0;

            $out[] = $this->panel(
                $hasVirtualmin ? 'virtualmin' : 'webmin',
                $hasVirtualmin ? 'Virtualmin' : 'Webmin',
                [
                    'version' => $this->firstLine($hasVirtualmin ? ($s['virtualmin'] ?? '') : ($s['webmin'] ?? '')),
                    'facts' => array_filter([
                        'webmin' => $hasVirtualmin ? $this->firstLine($s['webmin'] ?? '') : null,
                        'tls' => isset($conf['ssl']) ? ($conf['ssl'] === '1' ? 'on' : 'off') : null,
                    ]),
                    'counts' => $hasVirtualmin ? $this->countOf('domains', $s['virtualmin_domains'] ?? '') : [],
                    'unit' => $this->pickUnit($units, ['webmin.service']),
                    'ports' => array_values(array_unique(array_merge(
                        $port > 0 ? [$port] : [],
                        $this->portsOf($listen, [10000, 20000]),
                    ))),
                    'path' => '/etc/webmin',
                ],
                $units,
            );
        }

        if (! $this->missing($s['cyberpanel'] ?? '')) {
            $out[] = $this->panel('cyberpanel', 'CyberPanel', [
                'version' => $this->firstLine($s['cyberpanel'] ?? ''),
                'unit' => $this->pickUnit($units, ['lscpd.service', 'lshttpd.service']),
                'ports' => $this->portsOf($listen, [8090]),
                'path' => '/usr/local/CyberCP',
            ], $units);
        }

        if (! $this->missing($s['hestia'] ?? '')) {
            $out[] = $this->panel('hestia', 'HestiaCP', [
                'version' => $this->firstMatch($s['hestia'] ?? '', "/^VERSION='?([^'\\s]+)/m"),
                'counts' => $this->countOf('users', $s['hestia_users'] ?? ''),
                'unit' => $this->pickUnit($units, ['hestia.service']),
                'ports' => $this->portsOf($listen, [8083]),
                'path' => '/usr/local/hestia',
            ], $units);
        }

        if (! $this->missing($s['vesta'] ?? '')) {
            $out[] = $this->panel('vesta', 'VestaCP', [
                'version' => $this->firstMatch($s['vesta'] ?? '', "/^VERSION='?([^'\\s]+)/m"),
                'unit' => $this->pickUnit($units, ['vesta.service']),
                'ports' => $this->portsOf($listen, [8083]),
                'path' => '/usr/local/vesta',
            ], $units);
        }

        if (! $this->missing($s['aapanel'] ?? '')) {
            $port = (int) trim($s['aapanel_port'] ?? '');
            $out[] = $this->panel('aapanel', 'aaPanel / BT', [
                'version' => $this->firstLine($s['aapanel'] ?? ''),
                'unit' => $this->pickUnit($units, ['bt.service']),
                'ports' => $port > 0 ? [$port] : $this->portsOf($listen, [7800, 8888]),
                'path' => '/www/server/panel',
                // Its own `bt default` prints a live login. Deliberately not run.
                'note' => 'credentials_not_read',
            ], $units);
        }

        if (! $this->missing($s['cloudpanel'] ?? '')) {
            $out[] = $this->panel('cloudpanel', 'CloudPanel', [
                'version' => $this->firstLine($s['cloudpanel'] ?? ''),
                'unit' => null,
                'ports' => $this->portsOf($listen, [8443]),
                'path' => '/home/clp',
            ], $units);
        }

        if (! $this->missing($s['froxlor'] ?? '')) {
            $out[] = $this->panel('froxlor', 'Froxlor', [
                'version' => null,
                'unit' => null,
                'ports' => [],
                'path' => $this->firstLine($s['froxlor'] ?? ''),
            ], $units);
        }

        if (! $this->missing($s['keyhelp'] ?? '')) {
            $out[] = $this->panel('keyhelp', 'KeyHelp', [
                'version' => $this->firstLine($s['keyhelp'] ?? ''),
                'unit' => $this->pickUnit($units, ['keyhelp.service']),
                'ports' => [],
                'path' => '/usr/local/keyhelp',
            ], $units);
        }

        if (! $this->missing($s['cockpit'] ?? '')) {
            $out[] = $this->panel('cockpit', 'Cockpit', [
                'version' => $this->firstMatch($s['cockpit'] ?? '', '/([0-9][0-9.]*)/'),
                'unit' => $this->pickUnit($units, ['cockpit.service']),
                'ports' => $this->portsOf($listen, [9090]),
                'path' => '/usr/share/cockpit',
            ], $units);
        }

        foreach (['runcloud' => 'RunCloud', 'serverpilot' => 'ServerPilot'] as $id => $name) {
            if (! $this->missing($s[$id] ?? '')) {
                $out[] = $this->panel($id, $name, [
                    'version' => null,
                    'unit' => $this->pickUnit($units, [$id.'.service', $id.'-agent.service']),
                    'ports' => [],
                    'path' => '/etc/'.$id,
                    // An agent for a panel hosted elsewhere; there is no local
                    // interface to open, which is worth saying rather than
                    // showing an address that goes nowhere.
                    'note' => 'agent_only',
                ], $units);
            }
        }

        return $out;
    }

    /**
     * Panels that live in a container rather than on the host.
     *
     * Recognised by image, never by container name: a container called "plesk"
     * running nginx is a web server, and the image is the fact.
     *
     * @return list<array<string,mixed>>
     */
    private function fromContainers(string $raw): array
    {
        if ($this->missing($raw)) {
            return [];
        }

        $known = [
            'portainer' => 'Portainer',
            'coolify' => 'Coolify',
            'caprover' => 'CapRover',
            'cloudron' => 'Cloudron',
            'yunohost' => 'YunoHost',
            'easypanel' => 'Easypanel',
            'dokploy' => 'Dokploy',
            'proxy-manager' => 'Nginx Proxy Manager',
        ];

        $out = [];
        $seen = [];
        foreach ($this->lines($raw) as $line) {
            $parts = explode("\t", $line);
            $image = trim($parts[1] ?? '');
            $repository = strtolower(explode(':', $image)[0]);

            foreach ($known as $needle => $label) {
                if (! str_contains($repository, $needle) || isset($seen[$label])) {
                    continue;
                }
                $seen[$label] = true;
                $out[] = [
                    'id' => 'container:'.$needle,
                    'name' => $label,
                    'installed' => true,
                    'version' => str_contains($image, ':') ? substr($image, (int) strpos($image, ':') + 1) : null,
                    'unit' => null,
                    'unit_state' => null,
                    'running' => true,
                    'ports' => $this->publishedPorts(trim($parts[2] ?? '')),
                    'path' => null,
                    'container' => trim($parts[0] ?? ''),
                    'image' => $image,
                    'facts' => [],
                    'counts' => [],
                    'note' => null,
                ];
            }
        }

        return $out;
    }

    /**
     * Something is listening where a panel usually does, and nothing claimed it.
     *
     * Deliberately called a candidate and not a detection: 8080 is a panel on
     * one host and a staging app on the next, and dressing a port up as an
     * identity is how an inventory starts lying.
     *
     * @param  list<array{port:int,address:string,process:string|null}>  $listen
     * @param  list<array<string,mixed>>  $panels
     * @param  int  $sshPort  The port this connection arrived on. DirectAdmin
     *                        also uses 2222, and reporting the door we came
     *                        through as a possible panel is pure noise.
     * @return list<array<string,string|int|null>>
     */
    private function candidates(array $listen, array $panels, int $sshPort): array
    {
        $hints = [
            2082 => 'cPanel', 2083 => 'cPanel', 2086 => 'WHM', 2087 => 'WHM', 2222 => 'DirectAdmin',
            8443 => 'Plesk / CloudPanel', 8880 => 'Plesk', 8083 => 'HestiaCP / VestaCP', 8090 => 'CyberPanel',
            7080 => 'OpenLiteSpeed', 7800 => 'aaPanel', 8888 => 'aaPanel / BT', 10000 => 'Webmin',
            20000 => 'Usermin', 9090 => 'Cockpit', 9000 => 'Portainer', 9443 => 'Portainer',
            3000 => 'Coolify / Grafana', 8000 => 'Coolify', 81 => 'Nginx Proxy Manager',
        ];

        $taken = [];
        foreach ($panels as $panel) {
            foreach ($this->arr($panel['ports'] ?? null) as $port) {
                if (is_int($port)) {
                    $taken[$port] = true;
                }
            }
        }

        // Processes that are plainly something else. A panel does not run as
        // sshd, and saying "possible DirectAdmin" over an SSH daemon teaches
        // people to ignore this list.
        $notPanels = ['sshd', 'ssh'];

        $out = [];
        $seen = [];
        foreach ($listen as $row) {
            if (! isset($hints[$row['port']]) || isset($taken[$row['port']]) || isset($seen[$row['port']])) {
                continue;
            }
            if ($row['port'] === $sshPort || in_array((string) $row['process'], $notPanels, true)) {
                continue;
            }
            $seen[$row['port']] = true;
            $out[] = [
                'port' => $row['port'],
                'address' => $row['address'],
                'process' => $row['process'],
                'hint' => $hints[$row['port']],
            ];
        }

        return $out;
    }

    /**
     * One panel entry.
     *
     * `running` is three-valued on purpose: false is "the unit is there and it
     * is down", null is "there is no unit to ask", and those are not the same
     * problem.
     *
     * @param  array<string,mixed>  $data
     * @param  array<string,array{state:string,sub:string}>  $units
     * @return array<string,mixed>
     */
    private function panel(string $id, string $name, array $data, array $units): array
    {
        $unit = is_string($data['unit'] ?? null) ? $data['unit'] : null;
        $state = $unit !== null && isset($units[$unit]) ? $units[$unit] : null;

        return [
            'id' => $id,
            'name' => $name,
            'installed' => true,
            'version' => is_string($data['version'] ?? null) ? $data['version'] : null,
            'unit' => $unit,
            'unit_state' => $state !== null ? $state['sub'] : null,
            'running' => $state !== null ? ($state['state'] === 'active') : null,
            'ports' => array_values(array_filter($this->arr($data['ports'] ?? null), 'is_int')),
            'path' => is_string($data['path'] ?? null) ? $data['path'] : null,
            'container' => null,
            'image' => null,
            'facts' => $this->arr($data['facts'] ?? null),
            'counts' => $this->arr($data['counts'] ?? null),
            'note' => is_string($data['note'] ?? null) ? $data['note'] : null,
        ];
    }

    /**
     * The panel-ish units, keyed by name.
     *
     * @return array<string,array{state:string,sub:string}>
     */
    private function units(string $raw): array
    {
        if ($this->missing($raw)) {
            return [];
        }

        $out = [];
        foreach ($this->lines($raw) as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            if (count($parts) >= 3) {
                $out[$parts[0]] = ['state' => $parts[1], 'sub' => $parts[2]];
            }
        }

        return $out;
    }

    /**
     * Everything listening, with the process holding the socket.
     *
     * @return list<array{port:int,address:string,process:string|null}>
     */
    private function listeners(string $raw): array
    {
        if ($this->missing($raw)) {
            return [];
        }

        $out = [];
        foreach ($this->lines($raw) as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            $local = $parts[3] ?? '';
            $pos = strrpos($local, ':');
            if ($pos === false) {
                continue;
            }
            $port = (int) substr($local, $pos + 1);
            if ($port <= 0) {
                continue;
            }
            preg_match('/users:\(\("([^"]+)"/', $line, $m);
            $out[] = [
                'port' => $port,
                'address' => substr($local, 0, $pos),
                'process' => $m[1] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Which of these ports actually answer here.
     *
     * A panel's documented default port means nothing if nothing is listening
     * on it, so only open ports are reported.
     *
     * @param  list<array{port:int,address:string,process:string|null}>  $listen
     * @param  list<int>  $wanted
     * @return list<int>
     */
    private function portsOf(array $listen, array $wanted): array
    {
        $open = [];
        foreach ($listen as $row) {
            if (in_array($row['port'], $wanted, true)) {
                $open[$row['port']] = true;
            }
        }

        return array_values(array_map('intval', array_keys($open)));
    }

    /** @return list<int> */
    private function publishedPorts(string $raw): array
    {
        preg_match_all('/:(\d+)->/', $raw, $m);

        return array_values(array_unique(array_map('intval', $m[1])));
    }

    /**
     * `key=value` lines as a map.
     *
     * @return array<string,string>
     */
    private function pairs(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            $pos = strpos($line, '=');
            if ($pos !== false) {
                $out[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1), " \t'\"");
            }
        }

        return $out;
    }

    /**
     * @param  array<string,string>  $pairs
     * @return array<string,int>
     */
    private function numbers(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $key => $value) {
            if (is_numeric($value)) {
                $out[$key] = (int) $value;
            }
        }

        return $out;
    }

    /**
     * A single count, left out entirely when the directory could not be read —
     * "0 users" and "we could not look" are opposite answers.
     *
     * @return array<string,int>
     */
    private function countOf(string $key, string $raw): array
    {
        $trimmed = trim($raw);

        return $this->missing($raw) || ! is_numeric($trimmed) ? [] : [$key => (int) $trimmed];
    }

    /**
     * @param  array<string,array{state:string,sub:string}>  $units
     * @param  list<string>  $candidates
     */
    private function pickUnit(array $units, array $candidates): ?string
    {
        foreach ($candidates as $unit) {
            if (isset($units[$unit])) {
                return $unit;
            }
        }

        return null;
    }

    private function firstMatch(string $raw, string $pattern): ?string
    {
        return preg_match($pattern, $raw, $m) === 1 ? trim($m[1]) : null;
    }

    private function firstLine(string $raw): ?string
    {
        $lines = $this->lines($raw);
        $first = trim($lines[0] ?? '');

        return $first === '' || $first === 'installed' ? null : $first;
    }

    /** A section that is empty or says so is not evidence the panel is there. */
    private function missing(string $raw): bool
    {
        return trim($raw) === '' || str_contains($raw, '__absent__');
    }

    /** @return array<mixed> */
    private function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
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
