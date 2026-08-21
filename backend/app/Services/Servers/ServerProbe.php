<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Support\BinaryProcess;
use App\Support\DiskTempFile;
use App\Support\OutboundUrl;
use phpseclib3\Crypt\PublicKeyLoader;
use Throwable;

/**
 * Collects a snapshot of a remote host over SSH — no agent on the target.
 *
 * Uses the OpenSSH client rather than a PHP SSH library. That is not a
 * preference: a correctly hardened sshd may offer only post-quantum key
 * exchange (mlkem768x25519-sha256, sntrup761x25519-sha512@openssh.com), which
 * no pure-PHP implementation speaks — the negotiation fails before a host key is
 * ever seen. OpenSSH speaks them, and it is the better-tested implementation of
 * the two. The cost is a subprocess, run the way every other binary in this app
 * is run: array argv through BinaryProcess, so there is no shell to inject into.
 *
 * Everything it runs on the target is read-only and needs no privilege: it reads
 * /proc and /etc/os-release rather than parsing human-formatted tool output,
 * because those are stable across distributions. One connection, ONE command
 * carrying the whole probe script (sections separated by markers).
 *
 * The probe script is a public constant on purpose: an operator who restricts
 * the key on the target with `command="/usr/local/bin/ll-facts"` installs
 * exactly this script, so the wire format has a single definition. With that
 * restriction a stolen key can do nothing but print the snapshot below.
 *
 * Never call this from a web request except for the explicit connection test.
 * A hanging connect would pin an Octane worker for the length of the timeout;
 * collection belongs in the queue.
 *
 * Not final so a test can subclass it and return a canned ProbeResult — the
 * alternative is an interface whose only production implementation is this class.
 */
class ServerProbe
{
    /**
     * Seconds to establish the session, and to wait for the probe output. The
     * interactive pair is what a user waits on behind a "test connection"
     * button, so it is deliberately tight; the background pair belongs to the
     * queue worker and may take its time on a slow link.
     */
    private const CONNECT_TIMEOUT = 8;

    private const EXEC_TIMEOUT = 25;

    private const CONNECT_TIMEOUT_INTERACTIVE = 5;

    private const EXEC_TIMEOUT_INTERACTIVE = 10;

    /** Refuse absurd output rather than buffering an unbounded response. */
    private const MAX_OUTPUT = 512 * 1024;

    /**
     * Host key types we are willing to pin, best first. Ed25519 is short, modern
     * and what every current sshd generates; RSA is the fallback for older
     * hosts. Plain DSA is deliberately absent — OpenSSH itself dropped it.
     */
    private const HOST_KEY_TYPES = ['ssh-ed25519', 'ecdsa-sha2-nistp256', 'rsa-sha2-512', 'ssh-rsa'];

    /**
     * POSIX sh, no bashisms. Every command is read-only, tolerates absence
     * (`2>/dev/null`) and is bounded, so a missing tool degrades one section
     * instead of failing the run. The update count reads the LOCAL package cache
     * only — the probe never makes the target fetch anything from the network.
     */
    public const PROBE = 'echo "##LL:hostname"; hostname 2>/dev/null
echo "##LL:os"; cat /etc/os-release 2>/dev/null
echo "##LL:kernel"; uname -s -r -m 2>/dev/null
echo "##LL:uptime"; cat /proc/uptime 2>/dev/null
echo "##LL:load"; cat /proc/loadavg 2>/dev/null
echo "##LL:mem"; cat /proc/meminfo 2>/dev/null
echo "##LL:disk"; df -P -k 2>/dev/null
echo "##LL:cpu"; nproc 2>/dev/null; grep -m1 "^model name" /proc/cpuinfo 2>/dev/null
echo "##LL:cpustat"; grep -m1 "^cpu " /proc/stat 2>/dev/null; sleep 1; grep -m1 "^cpu " /proc/stat 2>/dev/null
echo "##LL:reboot"; if [ -f /var/run/reboot-required ] || [ -f /run/reboot-required ]; then echo yes; else echo no; fi
echo "##LL:failed"; systemctl --failed --no-legend --plain 2>/dev/null | head -20
echo "##LL:ports"; ss -H -ltn 2>/dev/null | head -60
echo "##LL:containers"; docker ps --format "{{.Names}}|{{.Status}}" 2>/dev/null | head -60
echo "##LL:updates"; apt-get -s -o Debug::NoLocking=1 upgrade 2>/dev/null | grep -c "^Inst " || apk version -l "<" 2>/dev/null | tail -n +2 | wc -l
echo "##LL:ip"; ip -o -4 addr show scope global 2>/dev/null | awk \'{print $2" "$4}\' | head -10
echo "##LL:virt"; systemd-detect-virt 2>/dev/null
echo "##LL:boot"; grep -m1 "^btime" /proc/stat 2>/dev/null
echo "##LL:sessions"; who 2>/dev/null | head -10
echo "##LL:procs"; ps -eo rss=,comm= 2>/dev/null | sort -rn | head -6
echo "##LL:temp"; cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null
echo "##LL:end"';

    /**
     * Connect, run the probe, parse it. Returns a result rather than throwing:
     * a failed run is a normal, recordable outcome, not an exception.
     *
     * @param  bool  $interactive  a user is waiting on the response — tight timeouts
     */
    public function run(ServerTarget $target, bool $interactive = false): ProbeResult
    {
        $started = microtime(true);

        // Same guard as every other outbound path: a host that resolves to
        // link-local or a cloud-metadata address is refused before we connect.
        if (! OutboundUrl::hostAllowed($target->host)) {
            return new ProbeResult(false, error: 'unsafe_host');
        }
        if (! BinaryProcess::available('ssh') || ! BinaryProcess::available('ssh-keyscan')) {
            return new ProbeResult(false, error: 'ssh_missing');
        }

        // The host key comes first: it decides whether we are willing to hand
        // this host a credential at all.
        $hostKey = $target->hostKey !== '' ? $target->hostKey : $this->scanHostKey($target, $interactive);
        $fingerprint = $hostKey === null ? null : $this->fingerprint($hostKey);
        if ($hostKey === null || $fingerprint === null) {
            return new ProbeResult(false, error: 'no_host_key', durationMs: $this->elapsed($started));
        }
        if ($target->fingerprint !== '' && ! hash_equals($target->fingerprint, $fingerprint)) {
            return new ProbeResult(false, error: 'fingerprint_mismatch', fingerprint: $fingerprint, durationMs: $this->elapsed($started));
        }

        $pem = $this->usableKey($target);
        if ($pem === null) {
            return new ProbeResult(false, error: 'no_credentials', fingerprint: $fingerprint, hostKey: $hostKey, durationMs: $this->elapsed($started));
        }

        // Both files are RAII: the destructor unlinks them when this method
        // returns, so a private key never outlives the probe on disk.
        $keyFile = DiskTempFile::create('ll-serverkey');
        $knownHosts = DiskTempFile::create('ll-knownhosts');
        try {
            file_put_contents($keyFile->path(), $pem);
            chmod($keyFile->path(), 0600);
            file_put_contents($knownHosts->path(), $this->knownHostsLine($target, $hostKey));

            // The script goes in on stdin, not as the remote command: ssh hands a
            // command string to the ACCOUNT'S LOGIN SHELL, and that may be fish,
            // csh or anything else that does not read POSIX sh. Feeding `sh -s`
            // means the shell we chose is the only one that ever parses it.
            $result = BinaryProcess::runCapture(
                $this->sshArgv($target, $keyFile->path(), $knownHosts->path(), $interactive),
                $interactive ? self::EXEC_TIMEOUT_INTERACTIVE : self::EXEC_TIMEOUT,
                input: self::PROBE,
            );

            $text = $result['out'];
            if (strlen($text) > self::MAX_OUTPUT) {
                $text = substr($text, 0, self::MAX_OUTPUT);
            }

            if (! str_contains($text, '##LL:')) {
                return new ProbeResult(
                    false,
                    error: $this->failureReason($result['err']),
                    fingerprint: $fingerprint,
                    hostKey: $hostKey,
                    durationMs: $this->elapsed($started),
                );
            }

            return new ProbeResult(
                true,
                facts: (new FactParser)->parse($text),
                fingerprint: $fingerprint,
                hostKey: $hostKey,
                durationMs: $this->elapsed($started),
            );
        } catch (Throwable $e) {
            return new ProbeResult(false, error: $this->shorten($e->getMessage()), fingerprint: $fingerprint, hostKey: $hostKey, durationMs: $this->elapsed($started));
        }
    }

    /** @return array<int, string> */
    private function sshArgv(ServerTarget $target, string $keyPath, string $knownHostsPath, bool $interactive): array
    {
        return [
            'ssh',
            // No terminal, no prompting, no agent, no user config: this must
            // either work from what we passed or fail, never hang on a question.
            '-T',
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=yes',
            '-o', 'UserKnownHostsFile='.$knownHostsPath,
            '-o', 'GlobalKnownHostsFile=/dev/null',
            '-o', 'IdentitiesOnly=yes',
            '-o', 'IdentityAgent=none',
            '-o', 'PasswordAuthentication=no',
            '-o', 'KbdInteractiveAuthentication=no',
            '-o', 'ClearAllForwardings=yes',
            '-o', 'ConnectTimeout='.($interactive ? self::CONNECT_TIMEOUT_INTERACTIVE : self::CONNECT_TIMEOUT),
            '-o', 'LogLevel=ERROR',
            '-i', $keyPath,
            '-p', (string) $target->port,
            $target->username.'@'.$target->host,
            // Two words the login shell cannot get wrong, whatever it is. `sh`
            // rather than `bash`: the probe is POSIX and bash is absent on a
            // default Alpine, so requiring it would fail on exactly the small
            // hosts most likely to be monitored. A restricted key ignores this
            // and runs its forced command; either way the output must be the
            // marker format the parser understands.
            'sh -s',
        ];
    }

    /**
     * Read the target's host key. Only used when nothing is pinned yet — this is
     * the trust-on-first-use step whose result a human confirms.
     */
    private function scanHostKey(ServerTarget $target, bool $interactive): ?string
    {
        $result = BinaryProcess::runCapture([
            'ssh-keyscan',
            '-T', (string) ($interactive ? self::CONNECT_TIMEOUT_INTERACTIVE : self::CONNECT_TIMEOUT),
            '-p', (string) $target->port,
            $target->host,
        ], $interactive ? self::EXEC_TIMEOUT_INTERACTIVE : self::EXEC_TIMEOUT);

        $offered = [];
        foreach (preg_split('/\r\n|\r|\n/', $result['out']) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            // "<host> <type> <base64>" — the host token is one we supplied.
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            if (count($parts) >= 3 && in_array($parts[1], self::HOST_KEY_TYPES, true)) {
                $offered[$parts[1]] = $parts[1].' '.$parts[2];
            }
        }

        // Prefer in our own order, not the order the server happened to answer in.
        foreach (self::HOST_KEY_TYPES as $type) {
            if (isset($offered[$type])) {
                return $offered[$type];
            }
        }

        return null;
    }

    /**
     * A known_hosts entry for exactly this host and port, which is how the pin is
     * actually enforced: ssh refuses the connection itself rather than us
     * noticing a mismatch afterwards. OpenSSH wants the bracketed form for any
     * non-default port.
     */
    private function knownHostsLine(ServerTarget $target, string $hostKey): string
    {
        $name = $target->port === 22 ? $target->host : '['.$target->host.']:'.$target->port;

        return $name.' '.$hostKey."\n";
    }

    /**
     * The key in a form `ssh -i` accepts: decrypted, since OpenSSH would
     * otherwise prompt for the passphrase and BatchMode would abort. phpseclib
     * is used here purely as a key-format tool, not as a transport.
     */
    private function usableKey(ServerTarget $target): ?string
    {
        if (trim($target->privateKey) === '') {
            return null;
        }
        if ($target->passphrase === '') {
            return rtrim($target->privateKey)."\n";
        }
        try {
            $pem = PublicKeyLoader::loadPrivateKey($target->privateKey, $target->passphrase)->toString('OpenSSH');

            return is_string($pem) ? rtrim($pem)."\n" : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * OpenSSH's own presentation — `SHA256:` + unpadded base64 of the SHA-256
     * over the raw key blob — so the operator can compare it byte for byte
     * against `ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub` on the target.
     */
    private function fingerprint(string $hostKey): ?string
    {
        $parts = explode(' ', trim($hostKey));
        $blob = base64_decode($parts[1] ?? '', true);
        if ($blob === false || $blob === '') {
            return null;
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $blob, true)), '=');
    }

    /**
     * Map ssh's own complaint onto a reason the UI can explain, falling back to a
     * short quote of stderr for anything not seen before. Without this the user
     * gets "it did not work" for every distinct cause.
     */
    private function failureReason(string $stderr): string
    {
        $lower = strtolower($stderr);

        return match (true) {
            str_contains($lower, 'permission denied') => 'auth_failed',
            str_contains($lower, 'host key verification failed') => 'fingerprint_mismatch',
            str_contains($lower, 'connection refused') => 'connection_refused',
            str_contains($lower, 'timed out') => 'timeout',
            str_contains($lower, 'no route to host'), str_contains($lower, 'could not resolve') => 'unreachable',
            str_contains($lower, 'no matching key exchange'), str_contains($lower, 'no matching host key'),
            str_contains($lower, 'no matching cipher'), str_contains($lower, 'no matching mac') => 'no_common_algorithms',
            trim($stderr) === '' => 'unexpected_output',
            default => $this->shorten($stderr),
        };
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    /** Keep a diagnostic short — the message may quote what we sent. */
    private function shorten(string $message): string
    {
        $message = trim($message);
        // ssh usually prints several lines; the first carries the cause.
        // strtok returns false only for an empty subject, which trim() already ruled out.
        $first = strtok($message, "\n");
        $message = is_string($first) ? $first : $message;

        return mb_strlen($message) > 200 ? mb_substr($message, 0, 200) : $message;
    }
}
