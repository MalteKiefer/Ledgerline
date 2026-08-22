<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * Read and change ownership, mode and ACLs of a file on a monitored host.
 *
 * Not over SFTP: the protocol can set a numeric owner but knows nothing about
 * names, groups by name, or POSIX ACLs. So this uses the same shell path the
 * rest of the module uses, with the same discipline — a fixed set of verbs,
 * every value validated as what it claims to be, and the script assembled here
 * rather than taken from the request.
 *
 * ACLs are reported as absent-because-unreadable rather than as "no ACLs" when
 * the tools are missing. A host without the acl package is not a host without
 * access control, and saying so would be the same lie as calling an unreadable
 * firewall an empty one.
 */
class FilePermissions
{
    /** Account and group names. Deliberately narrow: this reaches a command line. */
    private const NAME_PATTERN = '/^[a-zA-Z0-9._][a-zA-Z0-9._-]{0,31}\$?$/';

    /** One ACL entry, as setfacl spells them: u:name:rwx, g:name:r-x, m::rwx. */
    private const ACL_PATTERN = '/^(u|g|m|o|d:u|d:g|d:m|d:o):[a-zA-Z0-9._-]*:[rwxX-]{0,4}$/';

    public function __construct(private ServerProbe $probe) {}

    /**
     * Everything that governs access to one path.
     *
     * @return array{ok:bool,mode:string,owner:string,group:string,uid:int,gid:int,type:string,acl:list<string>,acl_supported:bool,users:list<string>,groups:list<string>,error:string|null}
     */
    public function read(Server $server, string $path): array
    {
        $q = self::sq($path);
        $script = <<<SH
        echo "##LL:stat"; stat -c '%a %U %G %u %g %F' -- {$q} 2>&1
        echo "##LL:acl"; if command -v getfacl >/dev/null 2>&1; then getfacl -cE -- {$q} 2>/dev/null; else echo "__absent__"; fi
        echo "##LL:users"; getent passwd 2>/dev/null | cut -d: -f1 | sort | head -400
        echo "##LL:groups"; getent group 2>/dev/null | cut -d: -f1 | sort | head -400
        echo "##LL:end"
        SH;

        $out = $this->run($server, $script);
        if ($out === null) {
            return self::empty('unreachable');
        }

        $s = $this->sections($out);
        $stat = trim($s['stat'] ?? '');
        if ($stat === '' || str_contains(strtolower($stat), 'no such file')) {
            return self::empty('not_found');
        }
        if (str_contains(strtolower($stat), 'permission denied')) {
            return self::empty('permission_denied');
        }

        $f = preg_split('/\s+/', $stat, 6) ?: [];
        if (count($f) < 5) {
            return self::empty('failed');
        }

        $aclRaw = trim($s['acl'] ?? '');
        // Absent tooling is not an absent ACL. Those are different answers, and
        // collapsing them would misreport a host that has ACLs we cannot see.
        $aclSupported = $aclRaw !== '' && ! str_contains($aclRaw, '__absent__');

        return [
            'ok' => true,
            'mode' => str_pad($f[0], 4, '0', STR_PAD_LEFT),
            'owner' => $f[1],
            'group' => $f[2],
            'uid' => (int) $f[3],
            'gid' => (int) $f[4],
            'type' => trim($f[5] ?? ''),
            'acl' => $aclSupported ? $this->parseAcl($aclRaw) : [],
            'acl_supported' => $aclSupported,
            'users' => $this->lines($s['users'] ?? ''),
            'groups' => $this->lines($s['groups'] ?? ''),
            'error' => null,
        ];
    }

    /**
     * Change mode and/or owner.
     *
     * Recursion is opt-in and named, because `chmod -R` on the wrong directory
     * is the kind of mistake that ends an afternoon.
     *
     * @return array{ok:bool,output:string,error:string|null}
     */
    public function apply(Server $server, string $path, string $mode = '', string $owner = '', string $group = '', bool $recursive = false): array
    {
        if ($mode !== '' && preg_match('/^[0-7]{3,4}$/', $mode) !== 1) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_mode'];
        }
        if ($owner !== '' && preg_match(self::NAME_PATTERN, $owner) !== 1) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_owner'];
        }
        if ($group !== '' && preg_match(self::NAME_PATTERN, $group) !== 1) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_owner'];
        }
        if ($mode === '' && $owner === '' && $group === '') {
            return ['ok' => false, 'output' => '', 'error' => 'nothing_to_do'];
        }

        $q = self::sq($path);
        $r = $recursive ? '-R ' : '';
        // Ownership first, mode second, and the order is not cosmetic: chown
        // clears the setuid and setgid bits, so doing it after chmod silently
        // drops exactly the bits somebody went out of their way to set. Proven
        // against a real host — 4755 went in, 0755 came back.
        $parts = [];
        if ($owner !== '' || $group !== '') {
            // "owner:group", "owner:" or ":group" — chown reads all three.
            $parts[] = 'chown '.$r.self::sq($owner.':'.$group).' -- '.$q.' 2>&1';
        }
        if ($mode !== '') {
            $parts[] = 'chmod '.$r.$mode.' -- '.$q.' 2>&1';
        }

        $out = $this->run($server, implode("\n", $parts).';echo "##LL:rc=$?"');
        if ($out === null) {
            return ['ok' => false, 'output' => '', 'error' => 'unreachable'];
        }

        return $this->outcome($out);
    }

    /**
     * Replace, add or remove an ACL entry.
     *
     * @param  list<string>  $entries
     * @return array{ok:bool,output:string,error:string|null}
     */
    public function setAcl(Server $server, string $path, array $entries, bool $remove = false, bool $recursive = false): array
    {
        if ($entries === []) {
            return ['ok' => false, 'output' => '', 'error' => 'nothing_to_do'];
        }

        $spec = [];
        foreach ($entries as $entry) {
            $entry = trim($entry);
            if (preg_match(self::ACL_PATTERN, $entry) !== 1) {
                return ['ok' => false, 'output' => '', 'error' => 'invalid_acl'];
            }
            // Removing takes the entry without its permission field.
            $spec[] = $remove ? preg_replace('/:[rwxX-]{0,4}$/', '', $entry) : $entry;
        }

        $flag = $remove ? '-x' : '-m';
        $r = $recursive ? '-R ' : '';
        $script = 'if command -v setfacl >/dev/null 2>&1; then setfacl '.$r.$flag.' '
            .self::sq(implode(',', array_filter($spec))).' -- '.self::sq($path).' 2>&1; '
            .'else echo "__absent__"; fi; echo "##LL:rc=$?"';

        $out = $this->run($server, $script);
        if ($out === null) {
            return ['ok' => false, 'output' => '', 'error' => 'unreachable'];
        }
        if (str_contains($out, '__absent__')) {
            return ['ok' => false, 'output' => '', 'error' => 'acl_unsupported'];
        }

        return $this->outcome($out);
    }

    /**
     * getfacl's entries, comments already stripped by -c.
     *
     * @return list<string>
     */
    private function parseAcl(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $out[] = $line;
        }

        return $out;
    }

    /** @return list<string> */
    private function lines(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    /**
     * @return array{ok:bool,mode:string,owner:string,group:string,uid:int,gid:int,type:string,acl:list<string>,acl_supported:bool,users:list<string>,groups:list<string>,error:string|null}
     */
    private static function empty(string $error): array
    {
        return [
            'ok' => false, 'mode' => '', 'owner' => '', 'group' => '', 'uid' => 0, 'gid' => 0,
            'type' => '', 'acl' => [], 'acl_supported' => false, 'users' => [], 'groups' => [], 'error' => $error,
        ];
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

        return substr($result['out'], 0, 256 * 1024);
    }
}
