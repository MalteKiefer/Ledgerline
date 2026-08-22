<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * Read and change the ban lists of fail2ban and CrowdSec.
 *
 * Both are supported side by side rather than one being treated as "the" ban
 * daemon: a real host often runs fail2ban for sshd and CrowdSec for the web
 * tier, and an address banned by one is not known to the other. Every action
 * therefore names which daemon it is for.
 *
 * The addresses come from the request, so they are parsed as addresses before
 * anything is assembled — `filter_var` decides, not a pattern, because an
 * address that only looks right is exactly the input worth refusing.
 */
class BanManager
{
    /** What may be done with an address. */
    public const ACTIONS = ['unban', 'ban', 'allow'];

    /** Which daemon the action is meant for. */
    public const DAEMONS = ['fail2ban', 'crowdsec'];

    /** Jail names as fail2ban writes them. */
    private const JAIL_PATTERN = '/^[A-Za-z0-9._\-]{1,64}$/';

    public function __construct(private ServerProbe $probe) {}

    /**
     * Everything currently banned, per daemon.
     *
     * @return array{ok:bool,fail2ban:list<array{jail:string,ips:list<string>}>,crowdsec:list<array{ip:string,reason:string,expires:string}>,error:string|null}
     */
    public function bans(Server $server): array
    {
        $script = <<<'SH'
        echo "##LL:f2b"
        if command -v fail2ban-client >/dev/null 2>&1; then
          for j in $(fail2ban-client status 2>/dev/null | sed -n 's/.*Jail list:[[:space:]]*//p' | tr ',' ' '); do
            echo "JAIL $j"
            fail2ban-client status "$j" 2>/dev/null | sed -n 's/.*Banned IP list:[[:space:]]*//p'
          done
        else
          echo "__absent__"
        fi
        echo "##LL:csd"
        command -v cscli >/dev/null 2>&1 && cscli decisions list -o json 2>/dev/null || echo "__absent__"
        echo "##LL:end"
        SH;

        $out = $this->run($server, $script);
        if ($out === null) {
            return ['ok' => false, 'fail2ban' => [], 'crowdsec' => [], 'error' => 'unreachable'];
        }

        $s = $this->sections($out);

        return [
            'ok' => true,
            'fail2ban' => $this->parseFail2ban($s['f2b'] ?? ''),
            'crowdsec' => $this->parseCrowdsec($s['csd'] ?? ''),
            'error' => null,
        ];
    }

    /**
     * Unban, ban or allow-list an address.
     *
     * "allow" means different things to the two daemons and is handled
     * differently on purpose: fail2ban has no runtime allow-list, so the
     * address is only unbanned and the caller is told that the permanent entry
     * belongs in jail.local. Claiming otherwise would be a lie the next restart
     * exposes.
     *
     * @return array{ok:bool,output:string,error:string|null}
     */
    public function act(Server $server, string $daemon, string $action, string $ip, string $jail = ''): array
    {
        if (! in_array($daemon, self::DAEMONS, true) || ! in_array($action, self::ACTIONS, true)) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_selection'];
        }
        // An address is either an address or it is not; a pattern that merely
        // looks convincing is the input worth refusing.
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_ip'];
        }
        if ($daemon === 'fail2ban' && preg_match(self::JAIL_PATTERN, $jail) !== 1) {
            return ['ok' => false, 'output' => '', 'error' => 'jail_required'];
        }

        $q = self::sq($ip);
        $script = match (true) {
            $daemon === 'fail2ban' && $action === 'ban' => 'fail2ban-client set '.self::sq($jail).' banip '.$q.' 2>&1',
            // Both unban and allow do the same thing here: no runtime
            // allow-list exists, so the permanent entry is the operator's to
            // write in jail.local, and the caller is told so.
            $daemon === 'fail2ban' => 'fail2ban-client set '.self::sq($jail).' unbanip '.$q.' 2>&1',
            $action === 'unban' => 'cscli decisions delete --ip '.$q.' 2>&1',
            $action === 'ban' => 'cscli decisions add --ip '.$q.' --duration 4h --type ban 2>&1',
            // Newer CrowdSec has allowlists; older ones only understand a
            // bypass decision. Try the real thing first, fall back rather than
            // fail on a version difference.
            default => 'cscli allowlists add ledgerline --value '.$q.' 2>&1 || cscli decisions add --ip '.$q.' --duration 1s --type bypass 2>&1',
        };

        $out = $this->run($server, $script.'; echo "##LL:rc=$?"');
        if ($out === null) {
            return ['ok' => false, 'output' => '', 'error' => 'unreachable'];
        }

        $result = $this->outcome($out);
        if ($result['ok'] && $daemon === 'fail2ban' && $action === 'allow') {
            $result['error'] = 'f2b_allow_is_manual';
        }

        return $result;
    }

    /**
     * "JAIL sshd" followed by the address line for that jail.
     *
     * @return list<array{jail:string,ips:list<string>}>
     */
    private function parseFail2ban(string $raw): array
    {
        if (trim($raw) === '' || str_contains($raw, '__absent__')) {
            return [];
        }

        $found = [];
        $jail = null;
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'JAIL ')) {
                $jail = trim(substr($line, 5));
                $found[$jail] ??= [];

                continue;
            }
            if ($jail === null || $line === '') {
                continue;
            }
            foreach (preg_split('/\s+/', $line) ?: [] as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    $found[$jail][] = $ip;
                }
            }
        }

        $rows = [];
        foreach ($found as $name => $ips) {
            $rows[] = ['jail' => (string) $name, 'ips' => array_values(array_unique($ips))];
        }

        return $rows;
    }

    /**
     * cscli's JSON, reduced to what a reader acts on.
     *
     * @return list<array{ip:string,reason:string,expires:string}>
     */
    private function parseCrowdsec(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '' || str_contains($raw, '__absent__')) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $alert) {
            if (! is_array($alert)) {
                continue;
            }
            $decisions = is_array($alert['decisions'] ?? null) ? $alert['decisions'] : [];
            foreach ($decisions as $d) {
                if (! is_array($d)) {
                    continue;
                }
                $ip = is_scalar($d['value'] ?? null) ? (string) $d['value'] : '';
                if ($ip === '') {
                    continue;
                }
                $rows[] = [
                    'ip' => $ip,
                    'reason' => is_scalar($d['scenario'] ?? null) ? (string) $d['scenario'] : '',
                    'expires' => is_scalar($d['duration'] ?? null) ? (string) $d['duration'] : '',
                ];
            }
        }

        return array_slice($rows, 0, 200);
    }

    /**
     * @return array{ok:bool,output:string,error:string|null}
     */
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
    private static function sq(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
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
