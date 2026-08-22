<?php

declare(strict_types=1);

namespace App\Services\Servers;

/**
 * What this machine is for.
 *
 * A mail server and a Proxmox host both report load, memory and disks, and the
 * same panel serves neither of them well. Knowing the role lets the page put
 * the queue length or the guest list where the reader is already looking.
 *
 * Two sources on purpose. A service can be a systemd unit or a container, and
 * on a modern host it is usually the second — reading only units would call a
 * machine running Postfix, Dovecot and Rspamd in containers "just a Docker
 * host", which is true and useless.
 */
final class RoleDetector
{
    /**
     * Which services make which role.
     *
     * Names on the left are matched against systemd unit ids and against
     * container images. The image is the reliable half: a container named
     * `db` says nothing, `postgres:18` says everything.
     *
     * @var array<string,list<string>>
     */
    private const ROLE_SERVICES = [
        'mail' => ['postfix', 'dovecot', 'rspamd', 'opendkim', 'exim4', 'mailcow', 'stalwart', 'maddy'],
        'web' => ['nginx', 'apache2', 'httpd', 'caddy', 'traefik', 'haproxy'],
        'database' => ['postgresql', 'postgres', 'mariadb', 'mysql', 'redis', 'valkey', 'mongod', 'mongo', 'clickhouse'],
        'virtualisation' => ['libvirtd', 'qemu', 'pve'],
        'containers' => ['docker', 'containerd', 'k3s', 'kubelet', 'podman'],
        'storage' => ['smbd', 'nfs-server', 'minio', 'nextcloud', 'seafile'],
        'dns' => ['bind9', 'named', 'unbound', 'pdns', 'coredns', 'adguard', 'pihole'],
        'media' => ['jellyfin', 'plex', 'emby', 'navidrome'],
        'identity' => ['keycloak', 'authelia', 'authentik', 'pocket-id', 'lldap'],
        'monitoring' => ['prometheus', 'grafana', 'loki', 'zabbix', 'netdata', 'uptime-kuma'],
    ];

    /** Distributions that are a role in themselves. */
    private const PLATFORMS = [
        'pve' => 'proxmox',
        'pve-cluster' => 'proxmox',
        'truenas' => 'truenas',
        'opnsense' => 'opnsense',
        'openmediavault' => 'openmediavault',
    ];

    /**
     * @param  list<array{name:string,status:string,image?:string}>  $containers
     * @return array{roles:list<string>,platform:string|null,services:list<array{name:string,installed:bool,active:bool,source:string}>}
     */
    public function detect(string $unitsRaw, string $platformRaw, array $containers): array
    {
        $services = $this->units($unitsRaw);
        $platform = $this->platform($platformRaw);

        // What each container is, taken from its image rather than its name.
        $images = [];
        foreach ($containers as $c) {
            $image = strtolower((string) ($c['image'] ?? ''));
            if ($image !== '') {
                $images[] = $image;
            }
        }

        $roles = [];
        foreach (self::ROLE_SERVICES as $role => $needles) {
            foreach ($needles as $needle) {
                foreach ($services as $service) {
                    if ($service['installed'] && str_starts_with($service['name'], $needle)) {
                        $roles[$role] = true;
                    }
                }
                foreach ($images as $image) {
                    // The repository part only: a tag like `:16-alpine` is not
                    // a place to look for a service name.
                    $repo = strtok($image, ':') ?: $image;
                    if (str_contains($repo, $needle)) {
                        $roles[$role] = true;
                    }
                }
            }
        }

        if ($platform === 'proxmox') {
            $roles['virtualisation'] = true;
        }
        if ($platform === 'truenas' || $platform === 'openmediavault') {
            $roles['storage'] = true;
        }

        return [
            'roles' => array_keys($roles),
            'platform' => $platform,
            // Only what is actually installed: listing thirty absent services
            // would bury the handful that are there.
            'services' => array_values(array_filter($services, static fn (array $s): bool => $s['installed'])),
        ];
    }

    /**
     * `systemctl show` blocks, which say whether a unit exists at all.
     *
     * `LoadState=not-found` is the distinction that matters: "installed but
     * stopped" and "not installed" are different answers, and `is-active`
     * reports both as inactive.
     *
     * @return list<array{name:string,installed:bool,active:bool,source:string}>
     */
    private function units(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\n\s*\n/', trim($raw)) ?: [] as $block) {
            $props = [];
            foreach (preg_split('/\r\n|\r|\n/', trim($block)) ?: [] as $line) {
                $pair = explode('=', trim($line), 2);
                if (count($pair) === 2) {
                    $props[$pair[0]] = trim($pair[1]);
                }
            }
            $id = $props['Id'] ?? '';
            if ($id === '') {
                continue;
            }
            $name = str_ends_with($id, '.service') ? substr($id, 0, -8) : $id;
            $out[] = [
                'name' => $name,
                'installed' => ($props['LoadState'] ?? '') === 'loaded',
                'active' => ($props['ActiveState'] ?? '') === 'active',
                'source' => 'systemd',
            ];
        }

        return $out;
    }

    private function platform(string $raw): ?string
    {
        $text = strtolower(trim($raw));
        if ($text === '') {
            return null;
        }

        foreach (self::PLATFORMS as $needle => $platform) {
            if (str_contains($text, $needle)) {
                return $platform;
            }
        }

        return null;
    }
}
