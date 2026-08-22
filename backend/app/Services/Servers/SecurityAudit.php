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
     * @return array{ok:bool,firewalls:list<array<string,mixed>>,bans:list<array<string,mixed>>,ssh:array<string,mixed>,updates:array<string,mixed>,error:string|null}
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
        echo "##LL:end"
        SH;

        $out = $this->run($server, $script);
        if ($out === null) {
            return ['ok' => false, 'firewalls' => [], 'bans' => [], 'ssh' => [], 'updates' => [], 'error' => 'unreachable'];
        }

        $s = $this->sections($out);

        return [
            'ok' => true,
            'firewalls' => $this->firewalls($s),
            'bans' => $this->bans($s),
            'ssh' => $this->sshd($s['sshd'] ?? ''),
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
