<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\OutboundUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Fetches a login's site icon (BIMI logo, else favicon) for the password
 * manager. This is a deliberate, user-opted boundary crossing: the domain is
 * sent here transiently to fetch the icon, never stored server-side. The fetch
 * goes through the SSRF guard; the result is returned as a data URI which the
 * client caches inside the sealed item, so it never has to ask again.
 */
class PasswordIconController extends Controller
{
    private const MAX_BYTES = 262144; // 256 KiB

    public function fetch(Request $request): JsonResponse
    {
        $domain = strtolower(trim((string) $request->query('domain', '')));
        if (! preg_match('/^(?=.{1,253}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$/', $domain)) {
            return response()->json(['icon' => null]);
        }

        foreach ($this->candidates($domain) as $url) {
            $icon = $this->tryFetch($url);
            if ($icon !== null) {
                return response()->json(['icon' => $icon]);
            }
        }

        return response()->json(['icon' => null]);
    }

    /** @return list<string> */
    private function candidates(string $domain): array
    {
        $urls = [];
        // BIMI: a DNS TXT record at default._bimi.<domain> carries l=<svg url>.
        try {
            foreach (@dns_get_record('default._bimi.'.$domain, DNS_TXT) ?: [] as $rec) {
                $txt = is_string($rec['txt'] ?? null) ? $rec['txt'] : '';
                if (preg_match('/\bl=\s*(https:\/\/[^\s;"]+)/i', $txt, $m)) {
                    $urls[] = $m[1];
                    break;
                }
            }
        } catch (Throwable) {
            // no BIMI — fall through to HTML <link rel="icon"> then bare favicons.
        }
        // Parse the homepage HTML for <link rel="icon|apple-touch-icon"> — most sites
        // (WordPress etc.) serve NO bare /favicon.ico and only declare it in <head>.
        foreach ($this->htmlIcons($domain) as $u) {
            $urls[] = $u;
        }
        $urls[] = 'https://'.$domain.'/favicon.ico';
        $urls[] = 'https://'.$domain.'/apple-touch-icon.png';

        return array_values(array_unique($urls));
    }

    /**
     * Fetch the homepage HTML (following one redirect + trying www.) and extract the
     * declared icon URLs from <link rel="...icon...">. Apple-touch and larger icons
     * first. All SSRF-guarded; returns absolute http(s) URLs.
     *
     * @return list<string>
     */
    private function htmlIcons(string $domain): array
    {
        foreach (['https://'.$domain.'/', 'https://www.'.$domain.'/'] as $start) {
            $page = $this->fetchHtml($start);
            if ($page === null) {
                continue;
            }
            [$html, $base] = $page;
            $icons = self::parseIconLinks($html, $base);
            if ($icons !== []) {
                return $icons;
            }
        }

        return [];
    }

    /**
     * Extract icon URLs from <link rel="...icon..."> in an HTML document, resolved
     * against $base, ranked (apple-touch + bigger declared sizes first), max 6. Pure
     * (no I/O) so it is unit-testable without a network fetch.
     *
     * @return list<string>
     */
    public static function parseIconLinks(string $html, string $base): array
    {
        if (! preg_match_all('/<link\b[^>]*>/i', $html, $tags)) {
            return [];
        }
        $found = [];
        foreach ($tags[0] as $tag) {
            if (! preg_match('/\brel\s*=\s*["\']?[^"\'>]*icon[^"\'>]*["\']?/i', $tag)) {
                continue;
            }
            if (! preg_match('/\bhref\s*=\s*["\']([^"\']+)["\']/i', $tag, $h)) {
                continue;
            }
            $abs = self::resolveUrl(trim($h[1]), $base);
            if ($abs === null) {
                continue;
            }
            $score = stripos($tag, 'apple-touch-icon') !== false ? 1000 : 0;
            if (preg_match('/\bsizes\s*=\s*["\']?(\d+)/i', $tag, $s)) {
                $score += (int) $s[1];
            }
            $found[$abs] = max($found[$abs] ?? 0, $score);
        }
        arsort($found);

        return array_slice(array_keys($found), 0, 6);
    }

    /**
     * SSRF-safe HTML GET that follows a single redirect hop. Returns [html, finalUrl]
     * or null. Caps the download so a huge page can't exhaust memory.
     *
     * @return array{0: string, 1: string}|null
     */
    private function fetchHtml(string $url, int $hop = 0): ?array
    {
        try {
            if ($hop > 2 || ! OutboundUrl::safe($url)) {
                return null;
            }
            $res = OutboundUrl::client($url, 8, 1048576)->get($url);
            $status = $res->status();
            if ($status >= 300 && $status < 400) {
                $loc = (string) $res->header('Location');

                return $loc === '' ? null : $this->fetchHtml(self::resolveUrl($loc, $url) ?? '', $hop + 1);
            }
            if (! $res->ok()) {
                return null;
            }

            return [(string) $res->body(), $url];
        } catch (Throwable) {
            return null;
        }
    }

    /** Resolve a possibly-relative URL against a base; only http(s) results. */
    private static function resolveUrl(string $href, string $base): ?string
    {
        if ($href === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        $scheme = is_string($b['scheme'] ?? null) ? $b['scheme'] : 'https';
        $host = is_string($b['host'] ?? null) ? $b['host'] : '';
        if ($host === '') {
            return null;
        }
        if (str_starts_with($href, '//')) {
            return $scheme.':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $scheme.'://'.$host.$href;
        }

        return $scheme.'://'.$host.'/'.ltrim($href, '/');
    }

    private function tryFetch(string $url): ?string
    {
        try {
            if (! OutboundUrl::safe($url)) {
                return null;
            }
            // Cap the download at the wire, not just after buffering — a hostile icon
            // host could otherwise stream unbounded bytes and exhaust memory before the
            // post-buffer size check below runs.
            $res = OutboundUrl::client($url, 8, self::MAX_BYTES)->get($url);
            if (! $res->ok()) {
                return null;
            }
            $body = (string) $res->body();
            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }

            $mime = $this->imageMime($body, (string) $res->header('Content-Type'));

            return $mime === null ? null : 'data:'.$mime.';base64,'.base64_encode($body);
        } catch (Throwable) {
            return null;
        }
    }

    /** Confirm the bytes are an image (sniff magic) and return the MIME, else null. */
    private function imageMime(string $body, string $contentType): ?string
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
            // Only trust SVG when the server also declares it — SVG is rendered in
            // an <img>, so no script executes, but this avoids treating HTML error
            // pages containing an inline <svg> as an icon.
            return 'image/svg+xml';
        }

        return null;
    }
}
