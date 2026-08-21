<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Support\OutboundUrl;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Throwable;

/**
 * Collects a snapshot of a remote host over plain SSH — no agent on the target.
 *
 * Everything it runs is read-only and needs no privilege: it reads /proc and
 * /etc/os-release rather than parsing human-formatted tool output, because those
 * are stable across distributions. One connection, ONE exec of the whole probe
 * script (sections separated by markers), not a session per value.
 *
 * The probe script is a public constant on purpose: an operator who restricts the
 * key on the target with `command="/usr/local/bin/ll-facts"` puts exactly this
 * script there, so the wire format has a single definition. With that restriction
 * a stolen key can do nothing but print the snapshot below.
 *
 * Never call this from a web request. A hanging SSH connect would pin an Octane
 * worker for the length of the timeout; collection belongs in the queue.
 *
 * Not final so a test can subclass it and return a canned ProbeResult — the
 * alternative is an interface whose only production implementation is this class.
 */
class ServerProbe
{
    /**
     * Seconds to establish the TCP/SSH session, and to wait for the probe output
     * once it is up. The interactive pair is what a user waits on behind a
     * "test connection" button, so it is deliberately tight; the background pair
     * belongs to the queue worker and may take its time on a slow link.
     */
    private const CONNECT_TIMEOUT = 8;

    private const EXEC_TIMEOUT = 25;

    private const CONNECT_TIMEOUT_INTERACTIVE = 5;

    private const EXEC_TIMEOUT_INTERACTIVE = 10;

    /** Refuse absurd output rather than buffering an unbounded response. */
    private const MAX_OUTPUT = 512 * 1024;

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
echo "##LL:reboot"; if [ -f /var/run/reboot-required ] || [ -f /run/reboot-required ]; then echo yes; else echo no; fi
echo "##LL:failed"; systemctl --failed --no-legend --plain 2>/dev/null | head -20
echo "##LL:ports"; ss -H -ltn 2>/dev/null | head -60
echo "##LL:containers"; docker ps --format "{{.Names}}|{{.Status}}" 2>/dev/null | head -60
echo "##LL:updates"; apt-get -s -o Debug::NoLocking=1 upgrade 2>/dev/null | grep -c "^Inst " || apk version -l "<" 2>/dev/null | tail -n +2 | wc -l
echo "##LL:end"';

    /**
     * Connect, run the probe, parse it. Returns a result object rather than
     * throwing: a failed run is a normal, recordable outcome, not an exception.
     *
     * @param  array<string, mixed>  $credentials  password | private_key (+ passphrase)
     * @param  string|null  $expectedFingerprint  pinned host key; null = trust on first use
     * @param  bool  $interactive  a user is waiting on the response — use tight timeouts
     */
    public function run(
        string $host,
        int $port,
        string $username,
        string $authType,
        array $credentials,
        ?string $expectedFingerprint = null,
        bool $interactive = false,
    ): ProbeResult {
        $started = microtime(true);

        // Same guard as every other outbound path: a host that resolves to
        // link-local or a cloud-metadata address is refused before we connect.
        if (! OutboundUrl::hostAllowed($host)) {
            return new ProbeResult(false, error: 'unsafe_host');
        }

        $ssh = null;
        $password = '';
        try {
            $ssh = new SSH2($host, $port, $interactive ? self::CONNECT_TIMEOUT_INTERACTIVE : self::CONNECT_TIMEOUT);

            // Pin the host key. Without this the FIRST connection — the one that
            // carries the credentials — is interceptable.
            $fingerprint = $this->fingerprint($ssh);
            if ($fingerprint === null) {
                return new ProbeResult(false, error: 'no_host_key', durationMs: $this->elapsed($started));
            }
            if ($expectedFingerprint !== null && $expectedFingerprint !== ''
                && ! hash_equals($expectedFingerprint, $fingerprint)) {
                return new ProbeResult(false, error: 'fingerprint_mismatch', fingerprint: $fingerprint, durationMs: $this->elapsed($started));
            }

            if ($authType === 'key') {
                $raw = is_string($credentials['private_key'] ?? null) ? $credentials['private_key'] : '';
                $pass = is_string($credentials['passphrase'] ?? null) ? $credentials['passphrase'] : '';
                if ($raw === '') {
                    return new ProbeResult(false, error: 'no_credentials', fingerprint: $fingerprint, durationMs: $this->elapsed($started));
                }
                $key = PublicKeyLoader::loadPrivateKey($raw, $pass);
                $authed = $ssh->login($username, $key);
            } else {
                $password = is_string($credentials['password'] ?? null) ? $credentials['password'] : '';
                if ($password === '') {
                    return new ProbeResult(false, error: 'no_credentials', fingerprint: $fingerprint, durationMs: $this->elapsed($started));
                }
                $authed = $ssh->login($username, $password);
            }

            if ($authed !== true) {
                return new ProbeResult(false, error: 'auth_failed', fingerprint: $fingerprint, durationMs: $this->elapsed($started));
            }

            $ssh->setTimeout($interactive ? self::EXEC_TIMEOUT_INTERACTIVE : self::EXEC_TIMEOUT);
            $out = $ssh->exec(self::PROBE);
            // A restricted key ignores our command and runs its own forced one;
            // either way the output must be the marker format parsed below.
            $text = is_string($out) ? $out : '';
            if (strlen($text) > self::MAX_OUTPUT) {
                $text = substr($text, 0, self::MAX_OUTPUT);
            }
            if (! str_contains($text, '##LL:')) {
                return new ProbeResult(false, error: 'unexpected_output', fingerprint: $fingerprint, durationMs: $this->elapsed($started));
            }

            return new ProbeResult(
                true,
                facts: (new FactParser)->parse($text),
                fingerprint: $fingerprint,
                durationMs: $this->elapsed($started),
            );
        } catch (Throwable $e) {
            return new ProbeResult(false, error: $this->reason($e), durationMs: $this->elapsed($started));
        } finally {
            if ($password !== '') {
                sodium_memzero($password);
            }
            $ssh?->disconnect();
        }
    }

    /**
     * OpenSSH's own presentation — `SHA256:` + unpadded base64 of the SHA-256 over
     * the raw key blob — so the operator can compare it byte for byte against
     * `ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub` on the target.
     */
    private function fingerprint(SSH2 $ssh): ?string
    {
        $key = $ssh->getServerPublicHostKey();
        if (! is_string($key)) {
            return null;
        }
        $parts = explode(' ', $key);
        $blob = base64_decode($parts[1] ?? '', true);
        if ($blob === false || $blob === '') {
            return null;
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $blob, true)), '=');
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    /** A short, credential-free reason — the message may quote what we sent. */
    private function reason(Throwable $e): string
    {
        $msg = trim($e->getMessage());
        $msg = $msg === '' ? $e::class : $msg;

        return mb_strlen($msg) > 200 ? mb_substr($msg, 0, 200) : $msg;
    }
}
