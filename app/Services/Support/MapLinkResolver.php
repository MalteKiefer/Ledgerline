<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Support\OutboundUrl;
use Throwable;

/**
 * Resolves a Google-Maps *short* link (maps.app.goo.gl / goo.gl / g.co) to the
 * { lat, lng } it points at, by following its redirect chain and extracting the
 * coordinates from the final URL (or, as a fallback, the page body).
 *
 * A user-initiated, opt-in boundary crossing of the same class as the gallery
 * geocoder: only reached when the user pastes a Google-Maps link into the
 * Explore search box. The short link is the ONLY thing sent outbound, and only
 * to Google hosts (host allow-list below, re-checked on every redirect hop).
 * Every hop goes through {@see OutboundUrl} (link-local/metadata refused, IP
 * pinned against DNS-rebinding, redirects NOT auto-followed — we walk them
 * manually so each Location is re-validated). The link and the resolved
 * coordinates are NEVER logged or persisted. Returns null on any failure so the
 * caller answers a clean 200 { lat: null, lng: null }.
 */
class MapLinkResolver
{
    private const MAX_HOPS = 6;

    /**
     * Resolve a short Google-Maps link to coordinates, or null.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function resolve(string $url): ?array
    {
        if (! $this->hostAllowed($url)) {
            return null;
        }

        try {
            $current = $url;
            for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
                // The coordinates may already be in the URL after a hop or two.
                $coords = self::extractFromUrl($current);
                if ($coords !== null) {
                    return $coords;
                }

                $response = OutboundUrl::client($current, 6)
                    ->withHeaders(['User-Agent' => 'Ledgerline (self-hosted personal cloud)'])
                    ->get($current);

                $status = $response->status();
                if ($status >= 300 && $status < 400) {
                    $location = $response->header('Location');
                    if ($location === '') {
                        return null;
                    }
                    $next = $this->absolutize($current, $location);
                    if ($next === null || ! $this->hostAllowed($next)) {
                        return null;
                    }
                    $current = $next;

                    continue;
                }

                if ($response->successful()) {
                    // Final page — try the URL once more, then scan the body.
                    return self::extractFromUrl($current) ?? self::extractFromBody($response->body());
                }

                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Whether the URL's host is a permitted Google target. Only Google's own
     * short-link and maps hosts are allowed — as the initial link AND at every
     * redirect hop (a redirect off Google is refused).
     */
    private function hostAllowed(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }
        $host = strtolower($host);

        $exact = ['maps.app.goo.gl', 'goo.gl', 'g.co', 'google.com', 'maps.google.com'];
        if (in_array($host, $exact, true)) {
            return true;
        }

        // *.google.com (consent/www/maps subdomains) and google country TLDs
        // (google.de, google.co.uk, …). Nothing else.
        return str_ends_with($host, '.google.com')
            || preg_match('/^(?:[a-z0-9-]+\.)?google\.[a-z]{2}(?:\.[a-z]{2})?$/', $host) === 1;
    }

    /** Resolve a possibly-relative Location against the current URL. */
    private function absolutize(string $base, string $location): ?string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }
        $scheme = parse_url($base, PHP_URL_SCHEME);
        $host = parse_url($base, PHP_URL_HOST);
        if (! is_string($scheme) || ! is_string($host)) {
            return null;
        }
        $prefix = $scheme.'://'.$host;

        return str_starts_with($location, '/') ? $prefix.$location : $prefix.'/'.$location;
    }

    /**
     * Extract { lat, lng } from a Google-Maps URL string (mirrors the client
     * parseGoogleMapsUrl): the !3d!4d place pin, then the @lat,lng centre, then
     * the q=/ll=/center= params.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function extractFromUrl(string $url): ?array
    {
        if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $url, $m) === 1) {
            return self::valid((float) $m[1], (float) $m[2]);
        }
        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $url, $m) === 1) {
            return self::valid((float) $m[1], (float) $m[2]);
        }
        if (preg_match('/[?&](?:q|ll|center|query|destination|daddr)=(-?\d+(?:\.\d+)?)(?:,|%2[Cc])(-?\d+(?:\.\d+)?)/', $url, $m) === 1) {
            return self::valid((float) $m[1], (float) $m[2]);
        }

        return null;
    }

    /**
     * Scan a fetched page body for the first coordinate pair we recognise.
     *
     * @return array{lat: float, lng: float}|null
     */
    private static function extractFromBody(string $body): ?array
    {
        // Cap the scan so a huge page can't burn CPU.
        $body = substr($body, 0, 500_000);
        if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $body, $m) === 1) {
            return self::valid((float) $m[1], (float) $m[2]);
        }
        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $body, $m) === 1) {
            return self::valid((float) $m[1], (float) $m[2]);
        }

        return null;
    }

    /**
     * A valid WGS84 pair, or null.
     *
     * @return array{lat: float, lng: float}|null
     */
    private static function valid(float $lat, float $lng): ?array
    {
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }
}
