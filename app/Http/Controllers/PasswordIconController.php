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
            // no BIMI — fall through to favicons
        }
        $urls[] = 'https://'.$domain.'/favicon.ico';
        $urls[] = 'https://'.$domain.'/apple-touch-icon.png';

        return $urls;
    }

    private function tryFetch(string $url): ?string
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
