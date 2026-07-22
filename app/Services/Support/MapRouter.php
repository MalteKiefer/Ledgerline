<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Support\OutboundUrl;
use Throwable;

/**
 * Server-side proxy for the Explore tour-planner auto-routing feature. Given an
 * ordered list of [lat, lng] waypoints it asks an OSRM-compatible upstream
 * (config maps.route_upstream) to snap them onto real paths/roads and returns
 * the snapped geometry plus total distance/duration.
 *
 * ZERO-KNOWLEDGE / SSRF NOTE — this is a user-initiated, opt-in egress: it is
 * only reached when the user turns on "Follow paths / Auto-route" while planning
 * a tour, and it reveals the planned-route geometry to the router only for that
 * one request. When route_upstream is the public OSRM demo the waypoints leave
 * the ZK boundary (same class as the gallery place-picker geocoding); point it
 * at a self-hosted OSRM to keep every lookup in-boundary. The coordinates are
 * NEVER logged or persisted here — only relayed. Requests go through the SSRF
 * guard (OutboundUrl) regardless. Any failure (upstream unset/unreachable, no
 * route, malformed response) resolves to null so the caller falls back cleanly
 * to straight lines — never a crash.
 */
class MapRouter
{
    /**
     * Snap an ordered waypoint list to a route.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints  ordered [lat, lng] pairs
     * @return array{geometry: list<array{0: float, 1: float}>, distanceM: float, durationS: float}|null
     */
    public function route(array $waypoints): ?array
    {
        $base = $this->base();
        if ($base === '' || count($waypoints) < 2) {
            return null;
        }

        // OSRM expects lng,lat pairs joined by ';'.
        $coords = implode(';', array_map(
            static fn (array $p): string => rtrim(sprintf('%.6f', $p[1]), '0').','.rtrim(sprintf('%.6f', $p[0]), '0'),
            $waypoints,
        ));

        try {
            $url = $base.'/route/v1/'.rawurlencode($this->profile()).'/'.$coords;
            $response = OutboundUrl::client($base, 8)
                ->withHeaders(['User-Agent' => 'Ledgerline (self-hosted personal cloud)'])
                ->get($url, [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'continue_straight' => 'false',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();
            if (! is_array($json) || ($json['code'] ?? null) !== 'Ok') {
                return null;
            }

            $routes = $json['routes'] ?? null;
            $route = is_array($routes) && isset($routes[0]) && is_array($routes[0]) ? $routes[0] : null;
            if ($route === null) {
                return null;
            }

            $geometry = $this->coords($route);
            if (count($geometry) < 2) {
                return null;
            }

            $distance = $route['distance'] ?? null;
            $duration = $route['duration'] ?? null;

            return [
                'geometry' => $geometry,
                'distanceM' => is_numeric($distance) ? (float) $distance : 0.0,
                'durationS' => is_numeric($duration) ? (float) $duration : 0.0,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Extract the GeoJSON LineString coordinates from an OSRM route, converting
     * each [lng, lat] pair to [lat, lng] and dropping anything malformed.
     *
     * @param  array<mixed>  $route
     * @return list<array{0: float, 1: float}>
     */
    private function coords(array $route): array
    {
        $geometry = $route['geometry'] ?? null;
        $coordinates = is_array($geometry) ? ($geometry['coordinates'] ?? null) : null;
        if (! is_array($coordinates)) {
            return [];
        }

        $out = [];
        foreach ($coordinates as $pair) {
            if (! is_array($pair) || ! isset($pair[0], $pair[1]) || ! is_numeric($pair[0]) || ! is_numeric($pair[1])) {
                continue;
            }
            $lng = (float) $pair[0];
            $lat = (float) $pair[1];
            if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
                continue;
            }
            $out[] = [$lat, $lng];
        }

        return $out;
    }

    private function base(): string
    {
        $url = config('maps.route_upstream', '');

        return is_string($url) ? rtrim($url, '/') : '';
    }

    private function profile(): string
    {
        $profile = config('maps.route_profile', 'foot');

        return is_string($profile) && $profile !== '' ? $profile : 'foot';
    }
}
