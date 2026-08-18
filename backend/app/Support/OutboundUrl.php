<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SSRF guard for server-issued outbound HTTP requests to user-configured
 * targets (Paperless, NTFY, webhooks).
 *
 * This is a single-tenant, self-hosted application, so pointing at a LAN or
 * loopback service (e.g. a Paperless instance on the same host) is legitimate
 * and allowed by default. Two things are never legitimate and are always
 * refused: a non-http(s) scheme, and any address in the link-local range
 * 169.254.0.0/16 or fe80::/10 — which is how the cloud metadata endpoint
 * (169.254.169.254) is reached. Blocking of all private/loopback ranges can be
 * turned on with security.block_private_hosts for hardened deployments.
 */
final class OutboundUrl
{
    public static function safe(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return false;
        }

        $ips = self::resolve($host);
        if ($ips === []) {
            // Cannot resolve the host: an unresolvable name is not link-local
            // and the real request would simply fail to connect. Allow it in
            // the default posture (a host reachable only inside a Docker network
            // may not resolve at validation time); refuse only when the
            // hardened all-private-blocked mode is on.
            return ! config('security.block_private_hosts', false);
        }

        foreach ($ips as $ip) {
            if (! self::ipAllowed($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A redirect-free HTTP client for a user-configured target, with the
     * resolved (and verified-safe) IP PINNED to the connection. This closes the
     * validate-then-reconnect (DNS-rebinding / TOCTOU) bypass: the host is
     * resolved once here and curl connects to exactly that address, so a
     * short-TTL record can't answer a safe IP to the guard and a private/
     * metadata IP to the real request. Fails closed when the host cannot be
     * resolved to a verified-safe address.
     */
    public static function client(string $url, int $timeout = 15): PendingRequest
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new RuntimeException('Refusing to fetch an unsafe URL.');
        }

        $options = ['allow_redirects' => false];
        $ips = self::resolve($host);

        if ($ips !== []) {
            $allowed = array_values(array_filter($ips, fn ($ip) => self::ipAllowed($ip)));
            if ($allowed === []) {
                // Resolves only to refused addresses (metadata/link-local, or
                // private in hardened mode) — never connect.
                throw new RuntimeException('Refusing to fetch an unsafe URL.');
            }
            // Pin the verified IP so a DNS-rebind can't swap it at connect time,
            // and force the resolved address family so curl can't fall back to an
            // unverified A/AAAA record of the other family.
            $isV6 = str_contains($allowed[0], ':');
            $addr = $isV6 ? "[{$allowed[0]}]" : $allowed[0];
            $port = (int) (parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));
            $options['curl'] = [
                CURLOPT_RESOLVE => ["{$host}:{$port}:{$addr}"],
                CURLOPT_IPRESOLVE => $isV6 ? CURL_IPRESOLVE_V6 : CURL_IPRESOLVE_V4,
            ];
        } elseif (config('security.block_private_hosts', false)) {
            // Hardened posture: an unresolvable host cannot be verified — refuse.
            throw new RuntimeException('Refusing to fetch an unresolvable URL.');
        }
        // Default posture: an unresolvable host (e.g. a Docker-internal service
        // not resolvable at request time) is left unpinned, as before.

        return Http::withOptions($options)->timeout($timeout);
    }

    /**
     * Whether a bare host (IMAP/SMTP target — no URL/scheme) is an allowed
     * outbound destination: every resolved address must clear the same checks
     * as safe() (link-local/metadata always refused; private refused only in
     * hardened mode). Unresolvable hosts are allowed in the default posture so
     * a LAN/Docker mail server that doesn't resolve at save time still works.
     */
    public static function hostAllowed(string $host): bool
    {
        $host = trim($host);
        if ($host === '') {
            return false;
        }
        $ips = self::resolve($host);
        if ($ips === []) {
            return ! config('security.block_private_hosts', false);
        }
        foreach ($ips as $ip) {
            if (! self::ipAllowed($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a bare host with the same bounded resolver used by every outbound
     * guard. Discovery UI may report the result, but must never bypass the
     * guard before opening an HTTP or socket connection.
     *
     * @return list<string>
     */
    public static function resolveHost(string $host): array
    {
        return self::resolve($host);
    }

    /** Standard mail ports (SMTP 25/465/587, IMAP 143/993). */
    private const MAIL_PORTS = [25, 143, 465, 587, 993];

    /**
     * Whether $port is a standard mail port. The raw IMAP/SMTP socket callers use
     * this to refuse a "mail host" secretly pointed at an internal HTTP/Redis/…
     * port (an SSRF pivot) — the owner-configured host is otherwise trusted.
     */
    public static function mailPortAllowed(int $port): bool
    {
        return in_array($port, self::MAIL_PORTS, true);
    }

    /**
     * Resolve $host to a single verified-safe IP for a RAW socket connection,
     * pinning it against DNS-rebinding / TOCTOU: the name is resolved ONCE here
     * and the caller connects to exactly this address, with TLS peer_name/SNI set
     * to the original hostname so certificate verification still binds to the
     * name. This is the socket-world equivalent of client()'s CURLOPT_RESOLVE pin,
     * for the raw IMAP/SMTP clients that curl can't pin for us.
     *
     * Fails closed: unlike safe()/hostAllowed() (which tolerate an unresolvable
     * host in the default posture, since a Docker-internal HTTP service may not
     * resolve at save time), a socket cannot be re-pinned later, so a host that
     * does not resolve to an allowed address is refused outright. link-local/
     * metadata is always refused; private is refused only in the hardened posture.
     *
     * @return array{ip: string, host: string, ipv6: bool}
     *
     * @throws RuntimeException when no resolved address is allowed
     */
    public static function resolvedSocketTarget(string $host): array
    {
        $host = trim($host);
        if ($host === '') {
            throw new RuntimeException('Refusing to connect to an empty host.');
        }

        $allowed = array_values(array_filter(
            self::resolve($host),
            static fn (string $ip): bool => self::ipAllowed($ip),
        ));
        if ($allowed === []) {
            throw new RuntimeException('Refusing to connect: host did not resolve to an allowed address.');
        }

        $ip = $allowed[0];

        return ['ip' => $ip, 'host' => $host, 'ipv6' => str_contains($ip, ':')];
    }

    /**
     * Resolve $host to its A/AAAA addresses via `getent hosts` under a hard
     * process timeout — deliberately NOT PHP's own gethostbynamel()/
     * dns_get_record(), which have no timeout of their own and block the
     * calling PHP process for as long as the OS resolver takes (which can be
     * far longer than any curl/HTTP timeout configured downstream, since that
     * timeout only starts once resolve() has already returned). Under Octane
     * this method runs inline in a request-serving worker — with only a
     * handful of workers total, one call stuck resolving a slow/unresponsive
     * hostname can starve the whole pool for unrelated requests, not just this
     * one. BinaryProcess enforces a wall-clock kill, bounding the worst case to
     * a few seconds instead of an open-ended stall; array-argv, so $host is
     * never shell-interpreted. A timeout or lookup failure both degrade to []
     * (the existing "unresolvable" handling in safe()/client()/hostAllowed()).
     *
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $out = BinaryProcess::run(['getent', 'hosts', $host], 3);
        if ($out === null) {
            return [];
        }

        $ips = [];
        foreach (explode("\n", trim($out)) as $line) {
            $first = preg_split('/\s+/', trim($line))[0] ?? '';
            if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP) !== false) {
                $ips[] = $first;
            }
        }

        return array_values(array_unique($ips));
    }

    private static function ipAllowed(string $ip): bool
    {
        // An IPv4-mapped/compatible IPv6 literal (::ffff:169.254.169.254,
        // ::a9fe:a9fe, ::169.254.169.254, …) must be judged by the IPv4 it
        // embeds — otherwise the checks below are trivially bypassed to reach
        // loopback / the cloud metadata endpoint.
        $ip = self::embeddedIpv4($ip) ?? $ip;

        // Always refuse cloud-metadata / unusable addresses (link-local incl. the
        // 169.254.169.254 IMDS, the AWS IPv6 IMDS fd00:ec2::254, 0.0.0.0/8, ::).
        if (self::isAlwaysRefused($ip)) {
            return false;
        }

        if (config('security.block_private_hosts', false)) {
            return filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        return true;
    }

    /**
     * If $ip is an IPv4-mapped or IPv4-compatible IPv6 address, return the
     * embedded dotted IPv4; otherwise null. Operates on the canonical packed
     * form so every textual representation is covered.
     */
    private static function embeddedIpv4(string $ip): ?string
    {
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }
        if (substr($packed, 0, 10) !== str_repeat("\0", 10)) {
            return null;
        }
        $marker = substr($packed, 10, 2);
        if ($marker !== "\xff\xff" && $marker !== "\0\0") {
            return null;
        }
        $v4 = @inet_ntop(substr($packed, 12, 4));

        return ($v4 !== false && filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) ? $v4 : null;
    }

    /**
     * Addresses that are never a legitimate outbound destination and are refused
     * regardless of the block_private_hosts posture: link-local (both families,
     * covering the 169.254.169.254 cloud-metadata service), the AWS IPv6
     * instance-metadata endpoint fd00:ec2::254 (the only ULA address blocked —
     * the rest of fc00::/7 is legitimate LAN in a self-hosted deployment),
     * 0.0.0.0/8 "this network", and the :: unspecified address. Loopback and
     * private LAN stay ALLOWED by default (an on-host Paperless/ntfy is a valid
     * target); enable block_private_hosts to refuse those too.
     */
    private static function isAlwaysRefused(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            // 169.254.0.0/16 link-local (metadata) and 0.0.0.0/8 this-network.
            return str_starts_with($ip, '169.254.') || str_starts_with($ip, '0.');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            // Normalise to the canonical form so every textual spelling is caught.
            $packed = @inet_pton($ip);
            $norm = $packed !== false ? strtolower((string) @inet_ntop($packed)) : strtolower($ip);

            // fe80::/10 → first hextet fe8x..febx.
            if (in_array(substr($norm, 0, 3), ['fe8', 'fe9', 'fea', 'feb'], true)) {
                return true;
            }

            // AWS IPv6 instance-metadata endpoint + the unspecified address.
            return $norm === 'fd00:ec2::254' || $norm === '::';
        }

        return false;
    }
}
