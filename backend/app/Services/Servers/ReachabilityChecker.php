<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Support\BinaryProcess;
use App\Support\OutboundUrl;

/**
 * Cheap, frequent liveness checks: is the host answering, and are the ports the
 * operator cares about open.
 *
 * Deliberately separate from ServerProbe. The probe opens an SSH session and
 * runs a script — too expensive to repeat every few minutes, and it tells you
 * nothing between runs. This opens a socket and closes it.
 *
 * TCP is the backbone, not ICMP. Two reasons. Under `cap_drop: [ALL]` a file
 * capability cannot grant CAP_NET_RAW, so a raw-socket ping is out; whether the
 * unprivileged ICMP datagram socket works depends on a sysctl
 * (net.ipv4.ping_group_range) that is not ours to set. And a TCP connect proves
 * more: ICMP says the IP stack answers, a handshake on port 22 says sshd is
 * actually serving. So ICMP is attempted and recorded when it works, but a
 * connect to the SSH port always runs, and that is the line the UI treats as
 * "reachable".
 */
class ReachabilityChecker
{
    /** Long enough for a slow transatlantic link, short enough to never pile up. */
    private const TCP_TIMEOUT = 4.0;

    private const PING_TIMEOUT = 6;

    /**
     * Run every check for one host.
     *
     * @param  list<int>  $ports  TCP ports to test, SSH port included by the caller
     * @return list<array{kind:string,port:int|null,ok:bool,latency_ms:int|null,error:string|null}>
     */
    public function check(string $host, array $ports): array
    {
        // Same refusal as every other outbound path. Returned as a result, not
        // an exception: a host that has become unsafe is a finding to record.
        if (! OutboundUrl::hostAllowed($host)) {
            return [['kind' => 'tcp', 'port' => null, 'ok' => false, 'latency_ms' => null, 'error' => 'unsafe_host']];
        }

        $out = [];
        $icmp = $this->ping($host);
        if ($icmp !== null) {
            $out[] = $icmp;
        }
        foreach (array_values(array_unique($ports)) as $port) {
            $out[] = $this->tcp($host, $port);
        }

        return $out;
    }

    /**
     * One ICMP echo. Returns null — no row at all — when ICMP is simply not
     * available to this container, rather than writing a permanent stream of
     * identical failures that would read as an outage.
     *
     * @return array{kind:string,port:null,ok:bool,latency_ms:int|null,error:string|null}|null
     */
    private function ping(string $host): ?array
    {
        if (! BinaryProcess::available('ping')) {
            return null;
        }

        // -c1 one packet, -W2 wait at most two seconds for it. Array argv, so
        // the host — which the user typed — is never parsed by a shell.
        $res = BinaryProcess::runCapture(['ping', '-n', '-c', '1', '-W', '2', $host], self::PING_TIMEOUT);
        $text = $res['out'].$res['err'];

        // Not permitted here at all: say nothing rather than cry wolf.
        if (stripos($text, 'operation not permitted') !== false || stripos($text, 'socket: permission') !== false) {
            return null;
        }

        if ($res['ok'] && preg_match('/time[=<]\s*([\d.]+)\s*ms/i', $text, $m) === 1) {
            return ['kind' => 'icmp', 'port' => null, 'ok' => true, 'latency_ms' => (int) round((float) $m[1]), 'error' => null];
        }

        return ['kind' => 'icmp', 'port' => null, 'ok' => false, 'latency_ms' => null, 'error' => 'no_reply'];
    }

    /**
     * A TCP handshake and nothing more — connect, measure, close. We never send
     * a byte, so this cannot be mistaken for an attempt to speak the protocol.
     *
     * @return array{kind:string,port:int,ok:bool,latency_ms:int|null,error:string|null}
     */
    private function tcp(string $host, int $port): array
    {
        $started = microtime(true);
        $errno = 0;
        $errstr = '';
        // @ because a refused connection is an expected outcome here, not a
        // warning worth surfacing in the log on every closed port.
        $sock = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errno,
            $errstr,
            self::TCP_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        $ms = (int) round((microtime(true) - $started) * 1000);

        if ($sock === false) {
            return [
                'kind' => 'tcp',
                'port' => $port,
                'ok' => false,
                'latency_ms' => null,
                // Distinguishing refused from timed out matters: refused means
                // the host is up and the service is not, timed out means we do
                // not know which.
                'error' => $this->classify($errno, $errstr),
            ];
        }
        fclose($sock);

        return ['kind' => 'tcp', 'port' => $port, 'ok' => true, 'latency_ms' => $ms, 'error' => null];
    }

    private function classify(?int $errno, ?string $errstr): string
    {
        $s = strtolower($errstr ?? '');
        if (str_contains($s, 'refused')) {
            return 'refused';
        }
        if (str_contains($s, 'timed out') || $errno === 110) {
            return 'timeout';
        }
        if (str_contains($s, 'unreachable') || str_contains($s, 'not known') || str_contains($s, 'resolve')) {
            return 'unreachable';
        }

        return 'failed';
    }
}
