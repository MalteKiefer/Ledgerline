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
            // Pin the verified IP so a DNS-rebind can't swap it at connect time.
            $addr = str_contains($allowed[0], ':') ? "[{$allowed[0]}]" : $allowed[0];
            $port = (int) (parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));
            $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$addr}"]];
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
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = gethostbynamel($host);
        $ips = is_array($ips) ? $ips : [];

        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = (string) $record['ipv6'];
                }
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

        // Always refuse link-local (covers the 169.254.169.254 metadata service).
        if (self::isLinkLocal($ip)) {
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

    private static function isLinkLocal(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return str_starts_with($ip, '169.254.');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            // fe80::/10 → first hextet fe8x..febx.
            $head = substr(strtolower($ip), 0, 3);

            return in_array($head, ['fe8', 'fe9', 'fea', 'feb'], true);
        }

        return false;
    }
}
