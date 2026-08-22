<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * The figures that only matter for what this machine actually is.
 *
 * A mail server is judged by its queue and how much of its post is spam; a
 * Proxmox host by its guests; a database server by its connections. Showing
 * all of that on every server would bury each of them, so only the sections
 * for the roles the host has are collected.
 *
 * One round trip regardless of how many roles apply: each one costs a full SSH
 * handshake, and a panel that opens four connections to draw itself is slow for
 * no reason.
 */
final class RoleInspector
{
    private const TIMEOUT = 40;

    /**
     * Fixed scripts, one per role. Nothing from a request ever reaches them —
     * the role comes from the stored snapshot, not from the caller.
     *
     * @var array<string,string>
     */
    private const SECTIONS = [
        'mail' => <<<'SH'
        echo "##LL:mail_queue"; (command -v postqueue >/dev/null 2>&1 && postqueue -p 2>&1 | tail -1) || (command -v mailq >/dev/null 2>&1 && mailq 2>&1 | tail -1) || echo "__absent__"
        echo "##LL:mail_rspamd"; command -v rspamc >/dev/null 2>&1 && rspamc stat 2>&1 | head -16 || echo "__absent__"
        echo "##LL:mail_dovecot"; command -v doveadm >/dev/null 2>&1 && doveadm who 2>&1 | tail -n +2 | head -20 || echo "__absent__"
        SH,
        'virtualisation' => <<<'SH'
        echo "##LL:pve_vm"; command -v qm >/dev/null 2>&1 && qm list 2>&1 | tail -n +2 | head -40 || echo "__absent__"
        echo "##LL:pve_ct"; command -v pct >/dev/null 2>&1 && pct list 2>&1 | tail -n +2 | head -40 || echo "__absent__"
        echo "##LL:libvirt"; command -v virsh >/dev/null 2>&1 && virsh list --all 2>/dev/null | tail -n +3 | head -40 || echo "__absent__"
        SH,
        'database' => <<<'SH'
        echo "##LL:pg"; if command -v psql >/dev/null 2>&1; then su - postgres -c "psql -tAF'|' -c \"select datname, pg_database_size(datname), numbackends from pg_stat_database where datname not like 'template%' order by 2 desc limit 12\"" 2>/dev/null || echo "__noaccess__"; else echo "__absent__"; fi
        echo "##LL:mysql"; command -v mysql >/dev/null 2>&1 && mysql -N -B -e "select table_schema, sum(data_length+index_length) from information_schema.tables group by 1 order by 2 desc limit 12" 2>/dev/null || echo "__absent__"
        echo "##LL:redis"; (command -v redis-cli >/dev/null 2>&1 && redis-cli info memory 2>/dev/null | grep -E "^used_memory_human|^maxmemory_human") || (command -v valkey-cli >/dev/null 2>&1 && valkey-cli info memory 2>/dev/null | grep -E "^used_memory_human|^maxmemory_human") || echo "__absent__"
        SH,
        'web' => <<<'SH'
        echo "##LL:web_sites"; if command -v caddy >/dev/null 2>&1 && [ -f /etc/caddy/Caddyfile ]; then grep -oE '^[a-z0-9.*-]+\.[a-z]{2,}' /etc/caddy/Caddyfile 2>/dev/null | sort -u | head -40; elif command -v nginx >/dev/null 2>&1; then nginx -T 2>/dev/null | grep -oP '(?<=server_name )[^;]+' | tr ' ' '\n' | grep -v '^$' | sort -u | head -40; elif command -v apache2ctl >/dev/null 2>&1; then apache2ctl -S 2>/dev/null | grep -oE 'namevhost [^ ]+' | awk '{print $2}' | sort -u | head -40; else echo "__absent__"; fi
        SH,
    ];

    public function __construct(private ServerProbe $probe) {}

    /**
     * Everything worth showing for the roles this host has.
     *
     * @param  list<string>  $roles
     * @return array{ok:bool,mail:array<string,mixed>|null,guests:list<array<string,string>>|null,databases:list<array<string,mixed>>|null,sites:list<string>|null,unreadable:list<string>,error:string|null}
     */
    public function inspect(Server $server, array $roles): array
    {
        $parts = [];
        foreach ($roles as $role) {
            if (isset(self::SECTIONS[$role])) {
                $parts[] = self::SECTIONS[$role];
            }
        }

        $empty = ['ok' => true, 'mail' => null, 'guests' => null, 'databases' => null, 'sites' => null, 'unreadable' => [], 'error' => null];
        if ($parts === []) {
            return $empty;
        }

        $key = (string) $server->host_key;
        if ($key === '') {
            return ['ok' => false, 'mail' => null, 'guests' => null, 'databases' => null, 'sites' => null, 'unreadable' => [], 'error' => 'no_host_key'];
        }

        $script = implode("\n", $parts)."\necho \"##LL:end\"";
        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, $script, timeout: self::TIMEOUT);
        if (! $result['ok'] && $result['out'] === '') {
            return ['ok' => false, 'mail' => null, 'guests' => null, 'databases' => null, 'sites' => null, 'unreadable' => [], 'error' => 'unreachable'];
        }

        $s = $this->sections(substr($result['out'], 0, 256 * 1024));

        // A role whose tools are not on the host at all is not the same as a
        // role with nothing to report: on a Docker host, Postgres and Caddy run
        // inside containers, psql and caddy are absent from the host, and an
        // empty list there would read as "no databases" when it means "nobody
        // could look". Say which ones those were and show no list for them.
        $unreadable = [];

        $guests = in_array('virtualisation', $roles, true) ? $this->guests($s) : null;
        if ($guests === [] && $this->allAbsent($s, ['pve_vm', 'pve_ct', 'libvirt'])) {
            $guests = null;
            $unreadable[] = 'virtualisation';
        }

        $databases = in_array('database', $roles, true) ? $this->databases($s) : null;
        if ($databases === [] && $this->allAbsent($s, ['pg', 'mysql', 'redis'])) {
            $databases = null;
            $unreadable[] = 'database';
        }

        $sites = in_array('web', $roles, true) ? $this->sites($s['web_sites'] ?? '') : null;
        if ($sites === [] && $this->allAbsent($s, ['web_sites'])) {
            $sites = null;
            $unreadable[] = 'web';
        }

        return [
            'ok' => true,
            'mail' => in_array('mail', $roles, true) ? $this->mail($s) : null,
            'guests' => $guests,
            'databases' => $databases,
            'sites' => $sites,
            'unreadable' => $unreadable,
            'error' => null,
        ];
    }

    /**
     * True when every one of these sections said the tool was not installed.
     *
     * @param  array<string,string>  $s
     * @param  list<string>  $keys
     */
    private function allAbsent(array $s, array $keys): bool
    {
        foreach ($keys as $key) {
            $raw = trim($s[$key] ?? '');
            if ($raw !== '' && ! str_contains($raw, '__absent__') && ! str_contains($raw, '__noaccess__')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string,string>  $s
     * @return array<string,mixed>
     */
    private function mail(array $s): array
    {
        $queueRaw = trim($s['mail_queue'] ?? '');

        // "Mail queue is empty" and "13953 Kbytes in 3855 Requests" are the two
        // things Postfix says. Zero is a fine answer; not knowing is not.
        $queued = null;
        if (str_contains(strtolower($queueRaw), 'empty')) {
            $queued = 0;
        } elseif (preg_match('/in (\d+) Request/i', $queueRaw, $m) === 1) {
            $queued = (int) $m[1];
        }

        $stat = [];
        foreach ($this->lines($s['mail_rspamd'] ?? '') as $line) {
            if (preg_match('/^(Messages scanned|Messages treated as spam|Messages treated as ham|Messages learned|Messages with action reject|Messages with action greylist):\s*(\d+)/i', $line, $m) === 1) {
                // Lowercase first, then strip the noun, then the spaces:
                // doing it in any other order leaves the word 'messages' stuck
                // to the front of every key.
                $label = str_replace(' ', '_', str_replace('messages ', '', strtolower($m[1])));
                $stat[$label] = (int) $m[2];
            }
        }

        $users = [];
        foreach ($this->lines($s['mail_dovecot'] ?? '') as $line) {
            if (str_contains($line, '__absent__')) {
                continue;
            }
            $fields = preg_split('/\s+/', trim($line)) ?: [];
            if (count($fields) >= 3 && str_contains($fields[0], '@')) {
                $users[] = ['user' => $fields[0], 'service' => $fields[2]];
            }
        }

        return [
            'queued' => $queued,
            'queue_raw' => $queueRaw === '__absent__' ? null : $queueRaw,
            'rspamd' => $stat === [] ? null : $stat,
            'sessions' => $users,
        ];
    }

    /**
     * Proxmox guests, or libvirt domains where that is what the host runs.
     *
     * @param  array<string,string>  $s
     * @return list<array<string,string>>
     */
    private function guests(array $s): array
    {
        $out = [];

        foreach (['pve_vm' => 'qemu', 'pve_ct' => 'lxc'] as $section => $kind) {
            foreach ($this->lines($s[$section] ?? '') as $line) {
                if (str_contains($line, '__absent__')) {
                    continue;
                }
                $f = preg_split('/\s+/', trim($line)) ?: [];
                if (count($f) < 3 || ! ctype_digit($f[0])) {
                    continue;
                }
                $out[] = ['kind' => $kind, 'id' => $f[0], 'name' => $f[1], 'status' => $f[2]];
            }
        }

        foreach ($this->lines($s['libvirt'] ?? '') as $line) {
            if (str_contains($line, '__absent__')) {
                continue;
            }
            $f = preg_split('/\s+/', trim($line), 3) ?: [];
            if (count($f) < 3) {
                continue;
            }
            $out[] = ['kind' => 'libvirt', 'id' => $f[0], 'name' => $f[1], 'status' => trim($f[2])];
        }

        return $out;
    }

    /**
     * @param  array<string,string>  $s
     * @return list<array<string,mixed>>
     */
    private function databases(array $s): array
    {
        $out = [];

        foreach ($this->lines($s['pg'] ?? '') as $line) {
            if (str_contains($line, '__absent__') || str_contains($line, '__noaccess__')) {
                continue;
            }
            $f = explode('|', trim($line));
            if (count($f) < 3 || ! is_numeric($f[1])) {
                continue;
            }
            $out[] = ['engine' => 'postgres', 'name' => $f[0], 'size_b' => (int) $f[1], 'connections' => (int) $f[2]];
        }

        foreach ($this->lines($s['mysql'] ?? '') as $line) {
            if (str_contains($line, '__absent__')) {
                continue;
            }
            $f = preg_split('/\t|\s{2,}/', trim($line)) ?: [];
            if (count($f) < 2 || ! is_numeric(trim($f[1]))) {
                continue;
            }
            $out[] = ['engine' => 'mysql', 'name' => trim($f[0]), 'size_b' => (int) trim($f[1]), 'connections' => null];
        }

        foreach ($this->lines($s['redis'] ?? '') as $line) {
            if (str_starts_with($line, 'used_memory_human:')) {
                $out[] = ['engine' => 'redis', 'name' => 'redis', 'size_b' => null, 'used' => trim(substr($line, 18)), 'connections' => null];
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function sites(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            if (str_contains($line, '__absent__') || $line === '_') {
                continue;
            }
            $out[] = $line;
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private function lines(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            if (trim($line) !== '') {
                $out[] = trim($line);
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
