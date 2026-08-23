<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * Which overlay network a host is on, and how it is doing.
 *
 * Four of the five providers here can be asked in a machine format -- NetBird,
 * Tailscale and ZeroTier speak JSON, WireGuard has a stable tab-separated dump.
 * None of them is parsed out of the text meant for a person, for the same reason
 * `lsblk -P` and `systemctl show` are used elsewhere: a field that holds a space
 * is not a parsing problem when the format says where the field ends.
 *
 * OpenVPN is the exception and has no status command at all, so it is reported
 * from what does exist: its units, its configuration files and its interfaces.
 */
final class VpnInspector
{
    private const TIMEOUT = 30;

    /**
     * One round trip for everything. Each section says plainly when its tool is
     * absent, because "not installed" and "installed but idle" are different
     * answers and an empty section cannot tell them apart.
     */
    private const SCRIPT = <<<'SH'
    printf "\n##LL:netbird\n"; if command -v netbird >/dev/null 2>&1; then netbird status --json 2>/dev/null || echo "__error__"; else echo "__absent__"; fi
    printf "\n##LL:netbird_unit\n"; systemctl show netbird --property=LoadState --property=ActiveState --property=SubState 2>/dev/null || echo "__absent__"
    printf "\n##LL:tailscale\n"; if command -v tailscale >/dev/null 2>&1; then tailscale status --json 2>/dev/null || echo "__error__"; else echo "__absent__"; fi
    printf "\n##LL:tailscale_unit\n"; systemctl show tailscaled --property=LoadState --property=ActiveState --property=SubState 2>/dev/null || echo "__absent__"
    printf "\n##LL:zt_info\n"; if command -v zerotier-cli >/dev/null 2>&1; then zerotier-cli -j info 2>/dev/null || echo "__error__"; else echo "__absent__"; fi
    printf "\n##LL:zt_networks\n"; command -v zerotier-cli >/dev/null 2>&1 && zerotier-cli -j listnetworks 2>/dev/null || echo "__absent__"
    printf "\n##LL:zt_peers\n"; command -v zerotier-cli >/dev/null 2>&1 && zerotier-cli -j peers 2>/dev/null || echo "__absent__"
    printf "\n##LL:zt_unit\n"; systemctl show zerotier-one --property=LoadState --property=ActiveState --property=SubState 2>/dev/null || echo "__absent__"
    printf "\n##LL:wg\n"; if command -v wg >/dev/null 2>&1; then wg show all dump 2>/dev/null || echo "__noaccess__"; else echo "__absent__"; fi
    printf "\n##LL:wg_units\n"; systemctl list-units --type=service --all --no-legend --plain 'wg-quick@*' 2>/dev/null | head -20 || echo "__absent__"
    printf "\n##LL:ovpn_units\n"; systemctl list-units --type=service --all --no-legend --plain 'openvpn*' 2>/dev/null | head -20 || echo "__absent__"
    printf "\n##LL:ovpn_conf\n"; ls /etc/openvpn/*.conf /etc/openvpn/client/*.conf /etc/openvpn/server/*.conf 2>/dev/null | head -20
    printf "\n##LL:links\n"; ip -o link 2>/dev/null | awk -F': ' '{print $2}' | head -60
    printf "\n##LL:end\n"
    SH;

    public function __construct(private ServerProbe $probe) {}

    /**
     * @return array{ok:bool,providers:list<array<string,mixed>>,error:string|null}
     */
    public function inspect(Server $server): array
    {
        $key = (string) $server->host_key;
        if ($key === '') {
            return ['ok' => false, 'providers' => [], 'error' => 'no_host_key'];
        }

        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, self::SCRIPT, timeout: self::TIMEOUT);
        if (! $result['ok'] && $result['out'] === '') {
            return ['ok' => false, 'providers' => [], 'error' => 'unreachable'];
        }

        $s = $this->sections(substr($result['out'], 0, 512 * 1024));
        $links = $this->lines($s['links'] ?? '');

        /** @var list<array<string,mixed>> $providers */
        $providers = [];
        foreach ([$this->netbird($s), $this->tailscale($s), $this->zerotier($s), $this->wireguard($s), $this->openvpn($s, $links)] as $provider) {
            if (is_array($provider)) {
                $providers[] = $provider;
            }
        }

        return ['ok' => true, 'providers' => $providers, 'error' => null];
    }

    /**
     * @param  array<string,string>  $s
     * @return array<string,mixed>|null
     */
    private function netbird(array $s): ?array
    {
        $raw = trim($s['netbird'] ?? '');
        $unit = $this->unit($s['netbird_unit'] ?? '');
        if ($this->missing($raw) && $unit === null) {
            return null;
        }

        $data = $this->json($raw);
        $peers = [];
        foreach ($this->arr($this->sub($data, 'peers')['details'] ?? null) as $peer) {
            if (! is_array($peer)) {
                continue;
            }
            $peers[] = [
                'name' => $this->str($peer['fqdn'] ?? null),
                'address' => $this->str($peer['netbirdIp'] ?? null),
                'status' => $this->str($peer['status'] ?? null),
                // "Relayed" against "P2P" is the difference between a hop through
                // someone else's server and a direct link, which is the thing
                // worth seeing at a glance.
                'route' => $this->str($peer['connectionType'] ?? null),
                'relay' => $this->str($peer['relayAddress'] ?? null),
                'last_handshake' => $this->str($peer['lastWireguardHandshake'] ?? null),
                'rx' => $this->int($peer['transferReceived'] ?? null),
                'tx' => $this->int($peer['transferSent'] ?? null),
                'latency_ns' => $this->int($peer['latency'] ?? null),
            ];
        }

        return [
            'id' => 'netbird',
            'name' => 'NetBird',
            'installed' => ! $this->missing($raw),
            'unit' => $unit,
            'connected' => $this->flag($this->sub($data, 'management')['connected'] ?? null),
            'address' => $this->str($data['netbirdIp'] ?? null),
            'hostname' => $this->str($data['fqdn'] ?? null),
            'version' => $this->str($data['daemonVersion'] ?? null),
            'facts' => array_filter([
                'management' => $this->flag($this->sub($data, 'management')['connected'] ?? null) ? 'connected' : null,
                'signal' => $this->flag($this->sub($data, 'signal')['connected'] ?? null) ? 'connected' : null,
                'relays' => $this->relayCount($data),
                'interface' => $this->flag($data['usesKernelInterface'] ?? null) ? 'kernel' : null,
                'port' => $this->int($data['wireguardPort'] ?? null) ?: null,
            ], fn ($v): bool => $v !== null && $v !== ''),
            'peers' => $peers,
            'peers_connected' => $this->int($this->sub($data, 'peers')['connected'] ?? null),
            'peers_total' => $this->int($this->sub($data, 'peers')['total'] ?? null),
        ];
    }

    /** @param array<mixed> $data */
    private function relayCount(array $data): ?string
    {
        $relays = $this->arr($data['relays'] ?? null);
        if ($relays === []) {
            return null;
        }
        $up = 0;
        foreach ($relays as $relay) {
            if (is_array($relay) && $this->flag($relay['available'] ?? null)) {
                $up++;
            }
        }

        return $up.'/'.count($relays);
    }

    /**
     * @param  array<string,string>  $s
     * @return array<string,mixed>|null
     */
    private function tailscale(array $s): ?array
    {
        $raw = trim($s['tailscale'] ?? '');
        $unit = $this->unit($s['tailscale_unit'] ?? '');
        if ($this->missing($raw) && $unit === null) {
            return null;
        }

        $data = $this->json($raw);
        $self = is_array($data['Self'] ?? null) ? $data['Self'] : [];

        $peers = [];
        foreach ($this->arr($data['Peer'] ?? null) as $peer) {
            if (! is_array($peer)) {
                continue;
            }
            $ips = $this->arr($peer['TailscaleIPs'] ?? null);
            $peers[] = [
                'name' => $this->str($peer['HostName'] ?? null),
                'address' => $this->str($ips[0] ?? null),
                'status' => $this->flag($peer['Online'] ?? null) ? 'Connected' : 'Idle',
                'route' => ($peer['Relay'] ?? null) === '' ? 'P2P' : 'Relayed',
                'relay' => $this->str($peer['Relay'] ?? null),
                'last_handshake' => $this->str($peer['LastHandshake'] ?? null),
                'rx' => $this->int($peer['RxBytes'] ?? null),
                'tx' => $this->int($peer['TxBytes'] ?? null),
                'latency_ns' => null,
            ];
        }

        $selfIps = $this->arr($self['TailscaleIPs'] ?? null);

        return [
            'id' => 'tailscale',
            'name' => 'Tailscale',
            'installed' => ! $this->missing($raw),
            'unit' => $unit,
            'connected' => $this->str($data['BackendState'] ?? null) === 'Running',
            'address' => $this->str($selfIps[0] ?? null),
            'hostname' => $this->str($self['HostName'] ?? null),
            'version' => $this->str($data['Version'] ?? null),
            'facts' => array_filter([
                'state' => $this->str($data['BackendState'] ?? null),
                'tailnet' => $this->str($this->sub($data, 'CurrentTailnet')['Name'] ?? null),
                'exit_node' => $this->flag($self['ExitNode'] ?? null) ? 'yes' : null,
            ], fn ($v): bool => $v !== null && $v !== ''),
            'peers' => $peers,
            'peers_connected' => count(array_filter($peers, fn (array $p): bool => $p['status'] === 'Connected')),
            'peers_total' => count($peers),
        ];
    }

    /**
     * @param  array<string,string>  $s
     * @return array<string,mixed>|null
     */
    private function zerotier(array $s): ?array
    {
        $raw = trim($s['zt_info'] ?? '');
        $unit = $this->unit($s['zt_unit'] ?? '');
        if ($this->missing($raw) && $unit === null) {
            return null;
        }

        $info = $this->json($raw);
        $networks = [];
        foreach ($this->arr($this->json(trim($s['zt_networks'] ?? ''))) as $net) {
            if (! is_array($net)) {
                continue;
            }
            $addresses = $this->arr($net['assignedAddresses'] ?? null);
            $networks[] = [
                'id' => $this->str($net['id'] ?? $net['nwid'] ?? null),
                'name' => $this->str($net['name'] ?? null),
                'status' => $this->str($net['status'] ?? null),
                'device' => $this->str($net['portDeviceName'] ?? null),
                'address' => $this->str($addresses[0] ?? null),
            ];
        }

        $peers = [];
        foreach ($this->arr($this->json(trim($s['zt_peers'] ?? ''))) as $peer) {
            if (! is_array($peer)) {
                continue;
            }
            $paths = $this->arr($peer['paths'] ?? null);
            $peers[] = [
                'name' => $this->str($peer['address'] ?? null),
                'address' => is_array($paths[0] ?? null) ? $this->str($paths[0]['address'] ?? null) : null,
                'status' => $paths === [] ? 'Idle' : 'Connected',
                'route' => $this->str($peer['role'] ?? null),
                'relay' => null,
                'last_handshake' => null,
                'rx' => null,
                'tx' => null,
                'latency_ns' => $this->int($peer['latency'] ?? null) * 1000000,
            ];
        }

        return [
            'id' => 'zerotier',
            'name' => 'ZeroTier',
            'installed' => ! $this->missing($raw),
            'unit' => $unit,
            'connected' => $this->flag($info['online'] ?? null),
            'address' => $this->str($info['address'] ?? null),
            'hostname' => null,
            'version' => $this->str($info['version'] ?? null),
            'facts' => array_filter([
                'networks' => $networks === [] ? null : (string) count($networks),
                'planet' => $this->str($info['planetWorldId'] ?? null),
            ], fn ($v): bool => $v !== null && $v !== ''),
            'networks' => $networks,
            'peers' => $peers,
            'peers_connected' => count(array_filter($peers, fn (array $p): bool => $p['status'] === 'Connected')),
            'peers_total' => count($peers),
        ];
    }

    /**
     * WireGuard, from `wg show all dump`.
     *
     * The dump is tab-separated and documented: the first line of each interface
     * is the interface itself, every line after it is a peer. Reading it beats
     * `wg show`, whose output is laid out for a person.
     *
     * @param  array<string,string>  $s
     * @return array<string,mixed>|null
     */
    private function wireguard(array $s): ?array
    {
        $raw = trim($s['wg'] ?? '');
        $units = $this->lines($s['wg_units'] ?? '');
        if ($this->missing($raw) && $units === []) {
            return null;
        }

        // Reading the dump needs privilege; without it we know it is there but
        // not what it is doing, which is not the same as an idle tunnel.
        if (str_contains($raw, '__noaccess__')) {
            return [
                'id' => 'wireguard', 'name' => 'WireGuard', 'installed' => true, 'unit' => null,
                'connected' => false, 'address' => null, 'hostname' => null, 'version' => null,
                'facts' => ['access' => 'denied'], 'interfaces' => [], 'peers' => [],
                'peers_connected' => 0, 'peers_total' => 0,
            ];
        }

        $interfaces = [];
        $peers = [];
        $seen = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if (count($f) < 4) {
                continue;
            }
            $iface = $f[0];
            if (! isset($seen[$iface])) {
                // First line for an interface: private key, public key, port, fwmark.
                $seen[$iface] = true;
                $interfaces[] = ['name' => $iface, 'public_key' => $f[2], 'port' => (int) $f[3]];

                continue;
            }
            // Peer line: public key, preshared, endpoint, allowed ips, handshake, rx, tx, keepalive.
            $handshake = (int) ($f[5] ?? '0');
            $peers[] = [
                'name' => $iface.' · '.substr($f[1], 0, 12).'…',
                'address' => $this->str($f[4] ?? null),
                // A handshake inside the last three minutes is the only honest
                // signal WireGuard gives that a peer is actually reachable.
                'status' => $handshake > 0 && (time() - $handshake) < 180 ? 'Connected' : 'Idle',
                'route' => $this->str($f[3]),
                'relay' => null,
                'last_handshake' => $handshake > 0 ? date(DATE_ATOM, $handshake) : null,
                'rx' => (int) ($f[6] ?? 0),
                'tx' => (int) ($f[7] ?? 0),
                'latency_ns' => null,
            ];
        }

        return [
            'id' => 'wireguard',
            'name' => 'WireGuard',
            'installed' => ! $this->missing($raw),
            'unit' => null,
            'connected' => $interfaces !== [],
            'address' => null,
            'hostname' => null,
            'version' => null,
            'facts' => $interfaces === [] ? [] : ['interfaces' => implode(', ', array_column($interfaces, 'name'))],
            'interfaces' => $interfaces,
            'peers' => $peers,
            'peers_connected' => count(array_filter($peers, fn (array $p): bool => $p['status'] === 'Connected')),
            'peers_total' => count($peers),
        ];
    }

    /**
     * OpenVPN has no status command, so it is reported from what does exist.
     *
     * @param  array<string,string>  $s
     * @param  list<string>  $links
     * @return array<string,mixed>|null
     */
    private function openvpn(array $s, array $links): ?array
    {
        $units = [];
        foreach ($this->lines($s['ovpn_units'] ?? '') as $line) {
            if (str_contains($line, '__absent__')) {
                continue;
            }
            $f = preg_split('/\s+/', trim($line)) ?: [];
            if (count($f) < 4 || ! str_starts_with($f[0], 'openvpn')) {
                continue;
            }
            $units[] = ['name' => $f[0], 'active' => $f[2], 'sub' => $f[3]];
        }

        $configs = [];
        foreach ($this->lines($s['ovpn_conf'] ?? '') as $line) {
            $configs[] = basename($line, '.conf');
        }

        // Debian ships a plain `openvpn.service` that starts nothing: it is a
        // collector for the openvpn@name instances, and it sits at
        // "active (exited)" on every host that merely has the package. Counting
        // it would give a VPN entry to machines that have no tunnel at all.
        $instances = array_values(array_filter($units, fn (array $u): bool => str_contains($u['name'], '@')));
        $tunnels = array_values(array_filter($links, fn (string $l): bool => str_starts_with($l, 'tun') || str_starts_with($l, 'tap')));

        if ($instances === [] && $configs === [] && $tunnels === []) {
            return null;
        }

        // "exited" is a unit that ran and finished, not a tunnel that is up.
        $running = array_values(array_filter($instances, fn (array $u): bool => $u['active'] === 'active' && $u['sub'] === 'running'));

        return [
            'id' => 'openvpn',
            'name' => 'OpenVPN',
            'installed' => true,
            'unit' => $instances[0] ?? null,
            // A tunnel interface is evidence of carried traffic; a running
            // instance is evidence of intent. Either will do, neither is
            // implied by the package being installed.
            'connected' => $running !== [] || $tunnels !== [],
            'address' => null,
            'hostname' => null,
            'version' => null,
            'facts' => array_filter([
                // No status command means no peer list; the tunnel interfaces
                // are the closest thing to evidence that it is carrying traffic.
                'tunnels' => $tunnels === [] ? null : implode(', ', $tunnels),
                'configs' => $configs === [] ? null : implode(', ', $configs),
            ], fn ($v): bool => $v !== null),
            'units' => $instances,
            'peers' => [],
            'peers_connected' => 0,
            'peers_total' => 0,
        ];
    }

    /** @return array{load:string,active:string,sub:string}|null */
    private function unit(string $raw): ?array
    {
        $kv = [];
        foreach ($this->lines($raw) as $line) {
            $pos = strpos($line, '=');
            if ($pos !== false) {
                $kv[substr($line, 0, $pos)] = substr($line, $pos + 1);
            }
        }

        // "not-found" is the honest answer for a unit that was never installed;
        // an inactive one that exists is a different thing entirely.
        $load = $kv['LoadState'] ?? '';
        if ($load === '' || $load === 'not-found') {
            return null;
        }

        return ['load' => $load, 'active' => $kv['ActiveState'] ?? '', 'sub' => $kv['SubState'] ?? ''];
    }

    /** @return array<mixed> */
    private function json(string $raw): array
    {
        if ($raw === '' || str_contains($raw, '__absent__') || str_contains($raw, '__error__')) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** A section that is empty or says so is not evidence the tool is there. */
    private function missing(string $raw): bool
    {
        return $raw === '' || str_contains($raw, '__absent__');
    }

    /**
     * A nested object from decoded JSON.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function sub(array $data, string $key): array
    {
        return isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];
    }

    /** True only for a real yes; anything else is not one. */
    private function flag(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    /** @return array<mixed> */
    private function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function str(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : null;
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
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
