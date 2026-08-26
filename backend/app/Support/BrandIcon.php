<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * A logo for a domain, as a data URI.
 *
 * Extracted from PasswordIconController, which had grown this whole ladder for
 * bank and partner logos: BIMI first, then the site's own favicons, then two
 * well-known favicon services. Mail sender avatars want exactly the same
 * ladder, and a second copy of it would drift.
 *
 * BIMI is the interesting rung: it is the standard by which a company publishes
 * a logo *for mail*, in DNS, signed off by the domain owner. When it is there it
 * is the right answer and not a guess.
 *
 * Every fetch goes through OutboundUrl (link-local and metadata refused, IP
 * pinned, no redirects), is size-capped, and is only trusted if the bytes really
 * are an image. Nothing is stored server-side; the caller decides what to cache.
 */
final class BrandIcon
{
    private const MAX_BYTES = 262144; // 256 KiB

    /** A domain name, lowercased, or null if it is not one. */
    public static function normaliseDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));

        return preg_match('/^(?=.{1,253}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$/', $domain) === 1
            ? $domain
            : null;
    }

    /** The first candidate that yields real image bytes, as `data:…;base64,…`. */
    public static function forDomain(string $domain): ?string
    {
        $clean = self::normaliseDomain($domain);
        if ($clean === null) {
            return null;
        }

        foreach (self::candidates($clean) as $url) {
            $icon = self::tryFetch($url);
            if ($icon !== null) {
                return $icon;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function candidates(string $domain): array
    {
        $urls = [];
        // BIMI: a DNS TXT record at default._bimi.<domain> carries l=<svg url>.
        try {
            foreach (@dns_get_record('default._bimi.'.$domain, DNS_TXT) ?: [] as $rec) {
                $txt = is_string($rec['txt'] ?? null) ? $rec['txt'] : '';
                if (preg_match('/\bl=\s*(https:\/\/[^\s;"]+)/i', $txt, $m) === 1) {
                    $urls[] = $m[1];
                    break;
                }
            }
        } catch (Throwable) {
            // No BIMI — fall through to favicons.
        }

        $urls[] = 'https://'.$domain.'/favicon.ico';
        $urls[] = 'https://'.$domain.'/apple-touch-icon.png';

        // Many sites do not serve /favicon.ico at the apex — it lives on www, is
        // declared only via <link rel=icon>, or sits behind a redirect the SSRF
        // client will not follow. Try www, then the well-known services (fixed
        // hosts, still SSRF-guarded) so a logo resolves for almost any domain.
        $bare = str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;
        if ($bare !== $domain) {
            $urls[] = 'https://'.$bare.'/favicon.ico';
        } else {
            $urls[] = 'https://www.'.$domain.'/favicon.ico';
        }
        $urls[] = 'https://icons.duckduckgo.com/ip3/'.$bare.'.ico';
        $urls[] = 'https://www.google.com/s2/favicons?sz=128&domain='.$bare;

        return $urls;
    }

    public static function tryFetch(string $url): ?string
    {
        try {
            if (! OutboundUrl::safe($url)) {
                return null;
            }
            $res = OutboundUrl::client($url, 8)->get($url);
            if (! $res->ok()) {
                return null;
            }
            $body = (string) $res->body();
            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }

            $mime = self::imageMime($body, (string) $res->header('Content-Type'));

            return $mime === null ? null : 'data:'.$mime.';base64,'.base64_encode($body);
        } catch (Throwable) {
            return null;
        }
    }

    /** Confirm the bytes really are an image (sniff magic) and name the MIME. */
    public static function imageMime(string $body, string $contentType): ?string
    {
        if (str_starts_with($body, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($body, 'GIF8')) {
            return 'image/gif';
        }
        if (str_starts_with($body, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($body, "\x00\x00\x01\x00")) {
            return 'image/x-icon';
        }
        if (str_starts_with($body, 'RIFF') && str_contains(substr($body, 0, 16), 'WEBP')) {
            return 'image/webp';
        }
        if (stripos($body, '<svg') !== false && str_contains(strtolower($contentType), 'svg')) {
            // Only trust SVG when the server declares it too. It is rendered in
            // an <img>, so no script runs, but this stops an HTML error page
            // that happens to contain an inline <svg> from passing as an icon.
            return 'image/svg+xml';
        }

        return null;
    }

    /** Static only. */
    private function __construct() {}
}
