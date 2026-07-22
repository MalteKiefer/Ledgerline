<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\OutboundUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Same-origin map-tile relay for the Explore module. The browser only ever talks
 * to this host (/maps/tiles + /maps/style), so the web CSP connect-src 'self'
 * holds and no external tile host needs whitelisting in the page CSP. The tiles
 * themselves are non-secret (public base-map imagery), but relaying them through
 * the server keeps the client's map requests same-origin and lets a self-hosted
 * tileserver-gl stay on the internal Docker network with no host port.
 *
 * The upstream base URL comes from config maps.tile_upstream and every outbound
 * fetch passes through the SSRF guard (OutboundUrl). z/x/y are validated as
 * bounded integers; the map viewport a coordinate implies is NEVER logged or
 * persisted (same discipline as the reverse-geocode endpoint). If the upstream
 * is unset or unreachable the endpoints return 404 — they never crash.
 */
class MapController extends Controller
{
    /** Maximum web-mercator zoom level a tile request may reference. */
    private const MAX_ZOOM = 22;

    /**
     * Relay a single map tile from the upstream tile server. z/x/y are validated
     * before egress; the coordinate they encode is neither logged nor stored.
     */
    public function tile(Request $request, int $z, int $x, int $y): Response
    {
        if ($z < 0 || $z > self::MAX_ZOOM) {
            abort(404);
        }
        // x/y are bounded by the zoom level: 0 .. (2^z - 1).
        $max = (1 << $z) - 1;
        if ($x < 0 || $x > $max || $y < 0 || $y > $max) {
            abort(404);
        }

        $base = $this->upstream();
        abort_if($base === null, 404);

        $style = $this->styleName();
        $url = $base.'/styles/'.$style.'/'.$z.'/'.$x.'/'.$y.'.png';
        abort_unless(OutboundUrl::safe($url), 404);

        try {
            $response = OutboundUrl::client($base, 10)->get($url);
        } catch (Throwable) {
            abort(404);
        }

        if (! $response->successful()) {
            abort(404);
        }

        $body = $response->body();
        $contentType = $response->header('Content-Type');

        return response($body, 200, [
            'Content-Type' => $contentType !== '' ? $contentType : 'image/png',
            'Content-Length' => (string) strlen($body),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'public, max-age='.$this->cacheSeconds().', immutable',
        ]);
    }

    /**
     * Relay the upstream style.json with its tile source URLs rewritten to the
     * same-origin /maps/tiles/{z}/{x}/{y} relay, so the client never learns (or
     * connects to) the upstream tile host directly.
     */
    public function style(Request $request): JsonResponse
    {
        $base = $this->upstream();
        abort_if($base === null, 404);

        $style = $this->styleName();
        $url = $base.'/styles/'.$style.'/style.json';
        abort_unless(OutboundUrl::safe($url), 404);

        try {
            $response = OutboundUrl::client($base, 10)->get($url);
        } catch (Throwable) {
            abort(404);
        }

        if (! $response->successful()) {
            abort(404);
        }

        $json = $response->json();
        abort_unless(is_array($json), 404);

        // Literal {z}/{x}/{y} placeholders (MapLibre/Leaflet template) — build the
        // origin manually so url() does not percent-encode the braces.
        $sameOrigin = rtrim(url('/'), '/').'/maps/tiles/{z}/{x}/{y}';
        $json = $this->rewriteTileUrls($json, $sameOrigin);

        return response()->json($json)
            ->header('Cache-Control', 'public, max-age='.$this->cacheSeconds());
    }

    /**
     * Point every raster/vector source's `tiles` array at the same-origin relay
     * template so the browser fetches through /maps/tiles instead of upstream.
     *
     * @param  array<mixed>  $style
     * @return array<mixed>
     */
    private function rewriteTileUrls(array $style, string $template): array
    {
        $sources = $style['sources'] ?? null;
        if (! is_array($sources)) {
            return $style;
        }

        foreach ($sources as $name => $source) {
            if (is_array($source) && isset($source['tiles'])) {
                $source['tiles'] = [$template];
                // Drop any upstream-absolute reference so nothing leaks the host.
                unset($source['url']);
                $sources[$name] = $source;
            }
        }
        $style['sources'] = $sources;

        return $style;
    }

    /** Configured upstream base URL, or null when the relay is disabled. */
    private function upstream(): ?string
    {
        $url = config('maps.tile_upstream');
        $url = is_string($url) ? trim($url) : '';

        return $url !== '' ? rtrim($url, '/') : null;
    }

    private function styleName(): string
    {
        $style = config('maps.style', 'basic');

        return is_string($style) && $style !== '' ? $style : 'basic';
    }

    private function cacheSeconds(): int
    {
        $seconds = config('maps.tile_cache_seconds', 604800);

        return is_numeric($seconds) ? (int) $seconds : 604800;
    }
}
