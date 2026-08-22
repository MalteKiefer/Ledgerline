<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * What is actually guarding a host.
 *
 * Deliberately reports several firewalls rather than one: a real machine often
 * runs nftables underneath with iptables-nft on top, ufw or firewalld driving
 * either, and fail2ban or CrowdSec adding bans of their own. Naming only the
 * first one found would hide the layer that is really deciding.
 *
 * Read-only and unprivileged in intent, but honest about the limit: listing
 * firewall rules needs root on most systems, so an unprivileged monitoring
 * account will see that a firewall exists and not what is in it. That is
 * reported as "cannot read" rather than as "no rules", because the two mean
 * opposite things.
 */
class SecurityAudit
{
    public function __construct(private ServerProbe $probe) {}

    /**
     * @return array<string,mixed>
     */
    public function audit(Server $server): array
    {
        $script = <<<'SH'
        echo "##LL:nft"; command -v nft >/dev/null 2>&1 && (nft list ruleset 2>&1 | head -40) || echo "__absent__"
        echo "##LL:iptables"; command -v iptables >/dev/null 2>&1 && (iptables -S 2>&1 | head -40) || echo "__absent__"
        echo "##LL:ip6tables"; command -v ip6tables >/dev/null 2>&1 && (ip6tables -S 2>&1 | head -20) || echo "__absent__"
        echo "##LL:ufw"; command -v ufw >/dev/null 2>&1 && (ufw status verbose 2>&1 | head -30) || echo "__absent__"
        echo "##LL:firewalld"; command -v firewall-cmd >/dev/null 2>&1 && (firewall-cmd --state 2>&1; firewall-cmd --list-all 2>&1 | head -25) || echo "__absent__"
        echo "##LL:fail2ban"; command -v fail2ban-client >/dev/null 2>&1 && (fail2ban-client status 2>&1 | head -20) || echo "__absent__"
        echo "##LL:crowdsec"; command -v cscli >/dev/null 2>&1 && (cscli metrics 2>&1 | head -5; cscli decisions list -o human 2>&1 | head -20; cscli bouncers list -o human 2>&1 | head -12) || echo "__absent__"
        echo "##LL:selinux"; command -v getenforce >/dev/null 2>&1 && getenforce 2>&1 || echo "__absent__"
        echo "##LL:apparmor"; command -v aa-status >/dev/null 2>&1 && (aa-status --enabled 2>/dev/null && echo enabled || echo disabled) || echo "__absent__"
        echo "##LL:sshd"; sshd -T 2>/dev/null | grep -E "^(permitrootlogin|passwordauthentication|pubkeyauthentication|permitemptypasswords|x11forwarding|port|kbdinteractiveauthentication) " | head -12
        echo "##LL:unattended"; systemctl is-enabled unattended-upgrades 2>/dev/null || systemctl is-enabled dnf-automatic.timer 2>/dev/null || echo "__absent__"
        echo "##LL:reboot"; if [ -f /var/run/reboot-required ] || [ -f /run/reboot-required ]; then echo yes; else echo no; fi

        # What the internet can reach. Listening sockets bound to a wildcard
        # address are the ones worth naming: a service on 127.0.0.1 is not
        # exposed, and listing it alongside one that is would hide the
        # difference that matters.
        echo "##LL:listen"
        (ss -tulpnH 2>/dev/null || netstat -tulpn 2>/dev/null | tail -n +3) | head -200

        # The address the host believes it has, from its own routing table.
        # Asking an outside service would tell a third party which machine is
        # being looked at, so that is a separate, opt-in step.
        echo "##LL:pubip"
        ip -o addr show scope global 2>/dev/null | awk '{print $4}' | head -20

        echo "##LL:sshdfull"
        sshd -T 2>/dev/null | grep -Ei '^(permitrootlogin|passwordauthentication|pubkeyauthentication|permitemptypasswords|x11forwarding|port|listenaddress|kbdinteractiveauthentication|maxauthtries|maxsessions|logingracetime|allowtcpforwarding|allowagentforwarding|gatewayports|permittunnel|clientaliveinterval|clientalivecountmax|allowusers|allowgroups|denyusers|denygroups|ciphers|macs|kexalgorithms|hostkeyalgorithms|usepam|strictmodes|ignorerhosts|hostbasedauthentication|printmotd|banner|logleve)' | head -60

        echo "##LL:sshkeys"
        for f in /etc/ssh/ssh_host_*_key.pub; do [ -f "$f" ] && ssh-keygen -lf "$f" 2>/dev/null; done | head -10

        echo "##LL:authkeys"
        for h in $(getent passwd | awk -F: '$3>=0 {print $6}' | sort -u | head -40); do
          k="$h/.ssh/authorized_keys"
          if [ -r "$k" ]; then printf '%s\t%s\n' "$k" "$(grep -cve '^\s*$' -e '^\s*#' "$k" 2>/dev/null)"; fi
        done | head -40

        echo "##LL:web"
        for w in nginx apache2 httpd caddy traefik lighttpd; do
          if command -v "$w" >/dev/null 2>&1; then
            printf '%s\t%s\t%s\n' "$w" "$($w -v 2>&1 | head -1 | tr -d '\t')" "$(systemctl is-active "$w" 2>/dev/null || echo unknown)"
          fi
        done

        echo "##LL:certs"
        for d in /etc/letsencrypt/live /etc/ssl/certs/localhost; do
          [ -d "$d" ] && for c in "$d"/*/fullchain.pem "$d"/*.pem; do
            [ -f "$c" ] && printf '%s\t%s\n' "$c" "$(openssl x509 -enddate -noout -in "$c" 2>/dev/null | cut -d= -f2)"
          done
        done 2>/dev/null | head -20

        # Kernel settings that decide whether a machine forwards, redirects or
        # answers things it should not.
        echo "##LL:sysctl"
        for k in net.ipv4.ip_forward net.ipv4.conf.all.accept_redirects net.ipv4.conf.all.send_redirects net.ipv4.conf.all.rp_filter net.ipv4.tcp_syncookies kernel.randomize_va_space kernel.kptr_restrict kernel.dmesg_restrict net.ipv4.conf.all.accept_source_route; do
          printf '%s\t%s\n' "$k" "$(sysctl -n "$k" 2>/dev/null || echo '-')"
        done

        echo "##LL:accounts"
        awk -F: '($2 == "" ) {print "empty:" $1}' /etc/shadow 2>/dev/null | head -10
        awk -F: '($3 == 0) {print "uid0:" $1}' /etc/passwd 2>/dev/null | head -10
        echo "##LL:sudoers"
        grep -rhE '^[^#].*NOPASSWD' /etc/sudoers /etc/sudoers.d/ 2>/dev/null | head -20

        echo "##LL:end"
        SH;

        $out = $this->run($server, $script);
        if ($out === null) {
            return [
                'ok' => false, 'firewalls' => [], 'bans' => [], 'ssh' => [], 'ssh_host_keys' => [],
                'ssh_authorized' => [], 'ssh_findings' => [], 'listening' => [], 'exposed' => [],
                'addresses' => [], 'web' => [], 'certificates' => [], 'sysctl' => [], 'accounts' => [],
                'sudoers_nopasswd' => [], 'updates' => [], 'error' => 'unreachable',
            ];
        }

        $s = $this->sections($out);

        $listening = $this->listening($s['listen'] ?? '');
        $ssh = $this->sshd($s['sshdfull'] ?? ($s['sshd'] ?? ''));

        return [
            'ok' => true,
            'firewalls' => $this->firewalls($s),
            'bans' => $this->bans($s),
            'ssh' => $ssh,
            'ssh_host_keys' => $this->hostKeys($s['sshkeys'] ?? ''),
            'ssh_authorized' => $this->authorizedKeys($s['authkeys'] ?? ''),
            'ssh_findings' => $this->sshFindings($ssh),
            'listening' => $listening,
            'exposed' => array_values(array_filter($listening, static fn (array $l): bool => $l['exposed'])),
            'addresses' => $this->addresses($s['pubip'] ?? ''),
            'web' => $this->webServers($s['web'] ?? ''),
            'certificates' => $this->certificates($s['certs'] ?? ''),
            'sysctl' => $this->sysctl($s['sysctl'] ?? ''),
            'accounts' => $this->accounts($s['accounts'] ?? ''),
            'sudoers_nopasswd' => $this->lines($s['sudoers'] ?? ''),
            'updates' => [
                'unattended' => $this->presence($s['unattended'] ?? '') === 'enabled',
                'reboot_required' => trim($s['reboot'] ?? '') === 'yes',
            ],
            'error' => null,
        ];
    }

    /**
     * Each packet filter that is present, and whether we could actually read it.
     *
     * @param  array<string,string>  $s
     * @return list<array{name:string,present:bool,readable:bool,active:bool|null,summary:string,detail:string}>
     */
    private function firewalls(array $s): array
    {
        $out = [];

        foreach ([
            'nftables' => 'nft',
            'iptables' => 'iptables',
            'ip6tables' => 'ip6tables',
            'ufw' => 'ufw',
            'firewalld' => 'firewalld',
        ] as $name => $key) {
            $raw = trim($s[$key] ?? '');
            if ($raw === '' || $raw === '__absent__') {
                continue;
            }

            $denied = $this->permissionDenied($raw);
            $out[] = [
                'name' => $name,
                'present' => true,
                'readable' => ! $denied,
                'active' => $denied ? null : $this->firewallActive($name, $raw),
                'summary' => $denied ? 'permission' : $this->firewallSummary($name, $raw),
                // Empty rather than a permission error repeated in a code block.
                'detail' => $denied ? '' : $raw,
            ];
        }

        foreach (['selinux' => 'selinux', 'apparmor' => 'apparmor'] as $name => $key) {
            $raw = trim($s[$key] ?? '');
            if ($raw === '' || $raw === '__absent__') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'present' => true,
                'readable' => true,
                'active' => ! in_array(strtolower($raw), ['disabled', 'permissive'], true),
                'summary' => strtolower($raw),
                'detail' => '',
            ];
        }

        return $out;
    }

    /**
     * fail2ban and CrowdSec: both ban addresses, and a host may run either or
     * both, so neither is treated as "the" answer.
     *
     * @param  array<string,string>  $s
     * @return list<array{name:string,present:bool,readable:bool,summary:string,detail:string}>
     */
    private function bans(array $s): array
    {
        $out = [];
        foreach (['fail2ban' => 'fail2ban', 'crowdsec' => 'crowdsec'] as $name => $key) {
            $raw = trim($s[$key] ?? '');
            if ($raw === '' || $raw === '__absent__') {
                continue;
            }
            $denied = $this->permissionDenied($raw);
            $out[] = [
                'name' => $name,
                'present' => true,
                'readable' => ! $denied,
                'summary' => $denied ? 'permission' : $this->banSummary($name, $raw),
                'detail' => $denied ? '' : $raw,
            ];
        }

        return $out;
    }

    private function banSummary(string $name, string $raw): string
    {
        if ($name === 'fail2ban' && preg_match('/Jail list:\s*(.+)/i', $raw, $m) === 1) {
            $jails = array_filter(array_map('trim', explode(',', $m[1])));

            return count($jails).' jails: '.implode(', ', array_slice($jails, 0, 6));
        }
        // cscli prints a table; the row count is the useful number.
        if ($name === 'crowdsec') {
            $decisions = substr_count($raw, '│') > 0 ? max(0, substr_count($raw, "\n") - 4) : 0;

            return $decisions > 0 ? $decisions.' decisions' : 'running';
        }

        return 'running';
    }

    private function firewallActive(string $name, string $raw): ?bool
    {
        $lower = strtolower($raw);

        return match ($name) {
            'ufw' => str_contains($lower, 'status: active'),
            'firewalld' => str_contains($lower, 'running'),
            // A ruleset with only default-accept policies is present but not
            // filtering anything, which is worth distinguishing from active.
            'iptables', 'ip6tables' => ! preg_match('/^-P (INPUT|FORWARD) ACCEPT$/m', $raw) || substr_count($raw, "\n") > 3,
            'nftables' => trim($raw) !== '',
            default => null,
        };
    }

    private function firewallSummary(string $name, string $raw): string
    {
        return match ($name) {
            'ufw' => preg_match('/Status:\s*(\w+)/i', $raw, $m) === 1 ? strtolower($m[1]) : 'unknown',
            'firewalld' => str_contains(strtolower($raw), 'running') ? 'running' : 'not running',
            'iptables', 'ip6tables' => substr_count(trim($raw), "\n") + 1 .' rules',
            'nftables' => substr_count($raw, 'chain ').' chains',
            default => '',
        };
    }

    /**
     * The sshd settings worth a second look, read from its own resolved config
     * rather than from sshd_config — an Include or a Match block means the file
     * and the running configuration can disagree.
     *
     * @return array<string,string>
     */
    private function sshd(string $raw): array
    {
        $out = [];
        foreach (explode("\n", trim($raw)) as $line) {
            $f = preg_split('/\s+/', trim($line), 2) ?: [];
            if (count($f) === 2 && $f[0] !== '') {
                $out[$f[0]] = $f[1];
            }
        }

        return $out;
    }

    /**
     * Listening sockets, and which of them the outside can reach.
     *
     * A wildcard bind is the line worth reading: a service on 127.0.0.1 is not
     * exposed, and listing both the same way hides exactly the difference that
     * decides whether something is reachable from the internet.
     *
     * @return list<array{proto:string,address:string,port:int,process:string,exposed:bool}>
     */
    private function listening(string $raw): array
    {
        $rows = [];
        foreach ($this->lines($raw) as $line) {
            $f = preg_split('/\s+/', trim($line)) ?: [];
            if (count($f) < 5) {
                continue;
            }

            // ss: "tcp LISTEN 0 128 0.0.0.0:22 0.0.0.0:* users:(("sshd",pid=1))"
            $proto = strtolower($f[0]);
            if (! str_starts_with($proto, 'tcp') && ! str_starts_with($proto, 'udp')) {
                continue;
            }

            $local = '';
            foreach ($f as $part) {
                if (preg_match('/^(.*):(\d+)$/', $part, $m) === 1 && ! str_contains($part, '*')) {
                    $local = $part;

                    break;
                }
            }
            if ($local === '' || preg_match('/^(.*):(\d+)$/', $local, $m) !== 1) {
                continue;
            }

            $address = trim($m[1], '[]');
            $process = '';
            if (preg_match('/"([^"]+)"/', $line, $pm) === 1) {
                $process = $pm[1];
            }

            $rows[] = [
                'proto' => $proto,
                'address' => $address === '' ? '*' : $address,
                'port' => (int) $m[2],
                'process' => $process,
                'exposed' => in_array($address, ['0.0.0.0', '::', '*', ''], true),
            ];
        }

        // One line per socket, but the same port on v4 and v6 is one service.
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = $row['proto'].':'.$row['port'].':'.($row['exposed'] ? 'x' : $row['address']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Settings worth a second look, and why.
     *
     * Only where one value is plainly worse than another. A non-standard port
     * is not insecure, it is non-standard, and flagging it would teach the
     * reader to ignore the list.
     *
     * @param  array<string,string>  $ssh
     * @return list<array{key:string,level:string,note:string}>
     */
    private function sshFindings(array $ssh): array
    {
        $out = [];
        $check = static function (string $key, string $bad, string $level, string $note) use ($ssh, &$out): void {
            if (($ssh[$key] ?? '') === $bad) {
                $out[] = ['key' => $key, 'level' => $level, 'note' => $note];
            }
        };

        $check('permitrootlogin', 'yes', 'danger', 'root_login');
        $check('passwordauthentication', 'yes', 'warn', 'password_auth');
        $check('permitemptypasswords', 'yes', 'danger', 'empty_passwords');
        $check('hostbasedauthentication', 'yes', 'warn', 'hostbased');
        $check('ignorerhosts', 'no', 'warn', 'rhosts');
        $check('x11forwarding', 'yes', 'warn', 'x11');
        $check('allowtcpforwarding', 'yes', 'info', 'tcp_forwarding');
        $check('permittunnel', 'yes', 'warn', 'tunnel');
        $check('gatewayports', 'yes', 'warn', 'gatewayports');

        $tries = (int) ($ssh['maxauthtries'] ?? 0);
        if ($tries > 6) {
            $out[] = ['key' => 'maxauthtries', 'level' => 'info', 'note' => 'many_tries'];
        }

        // No restriction at all means every account with a shell can log in,
        // which is worth saying out loud on a host that only needs one.
        if (($ssh['allowusers'] ?? '') === '' && ($ssh['allowgroups'] ?? '') === '') {
            $out[] = ['key' => 'allowusers', 'level' => 'info', 'note' => 'no_allowlist'];
        }

        return $out;
    }

    /**
     * The host's own key fingerprints, as ssh-keygen prints them.
     *
     * @return list<array{bits:int,fingerprint:string,type:string}>
     */
    private function hostKeys(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            // "256 SHA256:abc... root@host (ED25519)"
            if (preg_match('/^(\d+)\s+(\S+)\s+.*\((\w+)\)/', trim($line), $m) !== 1) {
                continue;
            }
            $out[] = ['bits' => (int) $m[1], 'fingerprint' => $m[2], 'type' => $m[3]];
        }

        return $out;
    }

    /**
     * How many keys can log into each account.
     *
     * @return list<array{path:string,keys:int}>
     */
    private function authorizedKeys(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if (count($f) < 2 || ! is_numeric(trim($f[1]))) {
                continue;
            }
            $out[] = ['path' => $f[0], 'keys' => (int) trim($f[1])];
        }

        return $out;
    }

    /**
     * Web servers present, with version and whether they are running.
     *
     * @return list<array{name:string,version:string,active:string}>
     */
    private function webServers(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if ($f[0] === '') {
                continue;
            }
            $out[] = ['name' => $f[0], 'version' => trim($f[1] ?? ''), 'active' => trim($f[2] ?? '')];
        }

        return $out;
    }

    /**
     * Certificate expiry dates found in the usual places.
     *
     * @return list<array{path:string,expires:string}>
     */
    private function certificates(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if (count($f) < 2 || trim($f[1]) === '') {
                continue;
            }
            $out[] = ['path' => $f[0], 'expires' => trim($f[1])];
        }

        return $out;
    }

    /**
     * Kernel settings that decide what the machine answers.
     *
     * @return array<string,string>
     */
    private function sysctl(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if (count($f) < 2) {
                continue;
            }
            $out[$f[0]] = trim($f[1]);
        }

        return $out;
    }

    /**
     * Accounts worth knowing about: no password at all, or a second uid 0.
     *
     * @return array{empty_password:list<string>,uid_zero:list<string>}
     */
    private function accounts(string $raw): array
    {
        $empty = [];
        $root = [];
        foreach ($this->lines($raw) as $line) {
            if (str_starts_with($line, 'empty:')) {
                $empty[] = substr($line, 6);
            }
            if (str_starts_with($line, 'uid0:')) {
                $root[] = substr($line, 5);
            }
        }

        return ['empty_password' => $empty, 'uid_zero' => $root];
    }

    /**
     * Global addresses the host holds, as it sees them.
     *
     * @return list<string>
     */
    private function addresses(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            // "192.0.2.5/24" — keep the prefix, it says how big the subnet is,
            // but only for something that actually parses as an address.
            $addr = trim($line);
            if (filter_var(strtok($addr, '/') ?: '', FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $out[] = $addr;
        }

        return $out;
    }

    /** @return list<string> */
    private function lines(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            if (trim($line) !== '' && ! str_contains($line, '__absent__')) {
                $out[] = rtrim($line);
            }
        }

        return $out;
    }

    private function permissionDenied(string $raw): bool
    {
        $lower = strtolower($raw);

        return str_contains($lower, 'permission denied')
            || str_contains($lower, 'operation not permitted')
            || str_contains($lower, 'must be root')
            || str_contains($lower, 'you need to be root')
            || str_contains($lower, 'root privileges');
    }

    private function presence(string $raw): string
    {
        $v = trim($raw);

        return $v === '' || $v === '__absent__' ? 'absent' : strtolower($v);
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

    private function run(Server $server, string $script): ?string
    {
        $key = (string) $server->host_key;
        if ($key === '') {
            return null;
        }

        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, $script, interactive: true);
        if (! $result['ok'] && $result['out'] === '') {
            return null;
        }

        $text = $result['out'];

        return strlen($text) > 512 * 1024 ? substr($text, 0, 512 * 1024) : $text;
    }
}
