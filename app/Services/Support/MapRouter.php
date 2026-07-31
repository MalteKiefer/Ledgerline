<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Support\OutboundUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Server-side proxy for the Explore tour-planner auto-routing feature. Given an
 * ordered list of [lat, lng] waypoints it asks a routing upstream
 * (config maps.route_upstream) to snap them onto real paths/roads and returns
 * the snapped geometry plus total distance/duration — and, when the upstream is
 * a self-hosted GraphHopper (maps.route_engine="graphhopper"), an elevation
 * profile, ascent/descent and an OSM surface breakdown too.
 *
 * The default engine is OSRM (unchanged behaviour): geometry/distance/duration
 * only; the elevation/ascent/descent/surfaces fields resolve to null so the
 * client falls back to a flat, surface-less route. GraphHopper is opt-in and
 * self-hosted.
 *
 * ZERO-KNOWLEDGE / SSRF NOTE — this is a user-initiated, opt-in egress: it is
 * only reached when the user turns on "Follow paths / Auto-route" while planning
 * a tour, and it reveals the planned-route geometry to the router only for that
 * one request. When route_upstream is the public OSRM demo the waypoints leave
 * the ZK boundary (same class as the gallery place-picker geocoding); point it
 * at a self-hosted OSRM or GraphHopper to keep every lookup in-boundary. The
 * upstream host comes from config (maps.route_upstream) — NEVER from user input.
 * The coordinates are NEVER logged or persisted here — only relayed. Requests go
 * through the SSRF guard (OutboundUrl) with redirects disabled regardless of
 * engine. Any failure (upstream unset/unreachable, no route, malformed response)
 * resolves to the null-shape so the caller falls back cleanly to straight lines —
 * never a crash.
 */
class MapRouter
{
    /** Downsample the elevation profile to at most this many points. */
    private const ELEVATION_POINTS = 256;

    /**
     * Snap an ordered waypoint list to a route.
     *
     * The response is backward-compatible: OSRM leaves the elevation/ascent/
     * descent/surfaces fields null; GraphHopper populates them.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints  ordered [lat, lng] pairs
     * @return array{
     *     geometry: list<array{0: float, 1: float}>,
     *     distanceM: float|null,
     *     durationS: float|null,
     *     elevation: list<array{distM: float, eleM: float}>|null,
     *     ascentM: float|null,
     *     descentM: float|null,
     *     surfaces: list<array{surface: string, distM: float}>|null
     * }|null
     */
    public function route(array $waypoints): ?array
    {
        $base = $this->base();
        if ($base === '' || count($waypoints) < 2) {
            return null;
        }

        try {
            return $this->engine() === 'graphhopper'
                ? $this->routeGraphHopper($base, $waypoints)
                : $this->routeOsrm($base, $waypoints);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * OSRM engine (default, unchanged): geometry/distance/duration; the
     * elevation/ascent/descent/surfaces fields are null.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return array{geometry: list<array{0: float, 1: float}>, distanceM: float|null, durationS: float|null, elevation: null, ascentM: null, descentM: null, surfaces: null}|null
     */
    private function routeOsrm(string $base, array $waypoints): ?array
    {
        // OSRM expects lng,lat pairs joined by ';'.
        $coords = implode(';', array_map(
            static fn (array $p): string => rtrim(sprintf('%.6f', $p[1]), '0').','.rtrim(sprintf('%.6f', $p[0]), '0'),
            $waypoints,
        ));

        $url = $base.'/route/v1/'.rawurlencode($this->profile()).'/'.$coords;
        $response = $this->client($base)->get($url, [
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

        $geometry = $this->osrmCoords($route);
        if (count($geometry) < 2) {
            return null;
        }

        $distance = $route['distance'] ?? null;
        $duration = $route['duration'] ?? null;

        return [
            'geometry' => $geometry,
            'distanceM' => is_numeric($distance) ? (float) $distance : 0.0,
            'durationS' => is_numeric($duration) ? (float) $duration : 0.0,
            'elevation' => null,
            'ascentM' => null,
            'descentM' => null,
            'surfaces' => null,
        ];
    }

    /**
     * Self-hosted GraphHopper engine (opt-in): geometry + distance + duration,
     * plus a downsampled elevation profile, ascent/descent, and an aggregated OSM
     * surface breakdown.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return array{
     *     geometry: list<array{0: float, 1: float}>,
     *     distanceM: float|null,
     *     durationS: float|null,
     *     elevation: list<array{distM: float, eleM: float}>|null,
     *     ascentM: float|null,
     *     descentM: float|null,
     *     surfaces: list<array{surface: string, distM: float}>|null
     * }|null
     */
    private function routeGraphHopper(string $base, array $waypoints): ?array
    {
        $query = [
            'profile' => $this->profile(),
            'points_encoded' => 'false',
            'elevation' => 'true',
            'details' => ['surface', 'road_class'],
        ];
        // Repeated "point=lat,lng" params — GraphHopper takes lat,lng order.
        $points = array_map(
            static fn (array $p): string => rtrim(sprintf('%.6f', $p[0]), '0').','.rtrim(sprintf('%.6f', $p[1]), '0'),
            $waypoints,
        );

        // Build the query string manually so the repeated `point` key survives
        // (http_build_query would index them point[0]=…). Still through the
        // SSRF-guarded, redirect-free client keyed on the config base URL.
        $pairs = [];
        foreach ($query as $key => $value) {
            foreach ((array) $value as $v) {
                $pairs[] = rawurlencode($key).'='.rawurlencode((string) $v);
            }
        }
        foreach ($points as $point) {
            $pairs[] = 'point='.rawurlencode($point);
        }
        $url = $base.'/route?'.implode('&', $pairs);

        $response = $this->client($base)->get($url);
        if (! $response->successful()) {
            return null;
        }

        return $this->parseGraphHopper($response);
    }

    /**
     * Parse a GraphHopper /route response (paths[0]) into the extended shape.
     * Returns null on any structural problem so the caller falls back cleanly.
     *
     * @return array{
     *     geometry: list<array{0: float, 1: float}>,
     *     distanceM: float|null,
     *     durationS: float|null,
     *     elevation: list<array{distM: float, eleM: float}>|null,
     *     ascentM: float|null,
     *     descentM: float|null,
     *     surfaces: list<array{surface: string, distM: float}>|null
     * }|null
     */
    private function parseGraphHopper(Response $response): ?array
    {
        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        $paths = $json['paths'] ?? null;
        $path = is_array($paths) && isset($paths[0]) && is_array($paths[0]) ? $paths[0] : null;
        if ($path === null) {
            return null;
        }

        // points.coordinates = [[lng, lat, ele], …] (elevation=true).
        $points = is_array($path['points'] ?? null) ? $path['points'] : [];
        $coordinates = is_array($points['coordinates'] ?? null) ? $points['coordinates'] : [];

        $geometry = [];
        $eles = [];          // per-vertex elevation in metres (or null)
        $cumDist = [0.0];    // cumulative haversine distance per vertex
        foreach ($coordinates as $pair) {
            if (! is_array($pair) || ! isset($pair[0], $pair[1]) || ! is_numeric($pair[0]) || ! is_numeric($pair[1])) {
                continue;
            }
            $lng = (float) $pair[0];
            $lat = (float) $pair[1];
            if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
                continue;
            }
            if ($geometry !== []) {
                $prev = $geometry[count($geometry) - 1];
                $cumDist[] = $cumDist[count($cumDist) - 1] + $this->haversine($prev[0], $prev[1], $lat, $lng);
            }
            $geometry[] = [$lat, $lng];
            $eles[] = isset($pair[2]) && is_numeric($pair[2]) ? (float) $pair[2] : null;
        }

        if (count($geometry) < 2) {
            return null;
        }

        $distance = $path['distance'] ?? null;
        $time = $path['time'] ?? null;

        return [
            'geometry' => $geometry,
            'distanceM' => is_numeric($distance) ? (float) $distance : null,
            'durationS' => is_numeric($time) ? (float) $time / 1000.0 : null,
            'elevation' => $this->elevationProfile($cumDist, $eles),
            'ascentM' => $this->ascentDescent($path, $eles, true),
            'descentM' => $this->ascentDescent($path, $eles, false),
            'surfaces' => $this->surfaces($path, $cumDist),
        ];
    }

    /**
     * Build a downsampled cumulative-distance vs elevation profile. Vertices with
     * no elevation are skipped. Returns null when there is no usable elevation.
     *
     * @param  list<float>  $cumDist
     * @param  list<float|null>  $eles
     * @return list<array{distM: float, eleM: float}>|null
     */
    private function elevationProfile(array $cumDist, array $eles): ?array
    {
        $pairs = [];
        foreach ($eles as $i => $ele) {
            if ($ele !== null && isset($cumDist[$i])) {
                $pairs[] = ['distM' => round($cumDist[$i], 1), 'eleM' => round($ele, 1)];
            }
        }
        $n = count($pairs);
        if ($n < 2) {
            return null;
        }
        if ($n <= self::ELEVATION_POINTS) {
            return $pairs;
        }

        // Evenly sample ELEVATION_POINTS vertices, always keeping first and last.
        $out = [];
        $step = ($n - 1) / (self::ELEVATION_POINTS - 1);
        for ($i = 0; $i < self::ELEVATION_POINTS; $i++) {
            $out[] = $pairs[(int) round($i * $step)];
        }

        return $out;
    }

    /**
     * Total ascent (or descent) in metres. Prefers GraphHopper's own ascend/
     * descend fields; otherwise sums the positive/negative elevation deltas along
     * the path. Returns null when neither is available.
     *
     * @param  array<mixed>  $path
     * @param  list<float|null>  $eles
     */
    private function ascentDescent(array $path, array $eles, bool $ascent): ?float
    {
        $key = $ascent ? 'ascend' : 'descend';
        $reported = $path[$key] ?? null;
        if (is_numeric($reported)) {
            // Pass GraphHopper's own figure through unrounded.
            return (float) $reported;
        }

        $sum = 0.0;
        $seen = false;
        $prev = null;
        foreach ($eles as $ele) {
            if ($ele === null) {
                continue;
            }
            if ($prev !== null) {
                $delta = $ele - $prev;
                if ($ascent && $delta > 0) {
                    $sum += $delta;
                    $seen = true;
                } elseif (! $ascent && $delta < 0) {
                    $sum += -$delta;
                    $seen = true;
                }
            }
            $prev = $ele;
        }

        return $seen ? round($sum, 1) : null;
    }

    /**
     * Aggregate distance per OSM surface value from GraphHopper's
     * details.surface = [[fromIdx, toIdx, "asphalt"], …], summing the segment
     * lengths between the referenced point indices. Sorted descending by distance.
     * Returns null when there is no surface detail.
     *
     * @param  array<mixed>  $path
     * @param  list<float>  $cumDist  cumulative distance per geometry vertex
     * @return list<array{surface: string, distM: float}>|null
     */
    private function surfaces(array $path, array $cumDist): ?array
    {
        $details = is_array($path['details'] ?? null) ? $path['details'] : [];
        $surface = is_array($details['surface'] ?? null) ? $details['surface'] : null;
        if ($surface === null) {
            return null;
        }

        $last = count($cumDist) - 1;
        /** @var array<string, float> $byValue */
        $byValue = [];
        foreach ($surface as $seg) {
            if (! is_array($seg) || ! isset($seg[0], $seg[1], $seg[2])) {
                continue;
            }
            if (! is_numeric($seg[0]) || ! is_numeric($seg[1]) || ! is_string($seg[2])) {
                continue;
            }
            $from = (int) $seg[0];
            $to = (int) $seg[1];
            if ($from < 0 || $to > $last || $to <= $from) {
                continue;
            }
            $value = trim($seg[2]);
            if ($value === '') {
                $value = 'unknown';
            }
            $dist = $cumDist[$to] - $cumDist[$from];
            $byValue[$value] = ($byValue[$value] ?? 0.0) + $dist;
        }

        if ($byValue === []) {
            return null;
        }

        arsort($byValue);

        $out = [];
        foreach ($byValue as $value => $dist) {
            $out[] = ['surface' => (string) $value, 'distM' => round($dist, 1)];
        }

        return $out;
    }

    /**
     * Extract the GeoJSON LineString coordinates from an OSRM route, converting
     * each [lng, lat] pair to [lat, lng] and dropping anything malformed.
     *
     * @param  array<mixed>  $route
     * @return list<array{0: float, 1: float}>
     */
    private function osrmCoords(array $route): array
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

    /** Great-circle distance between two [lat, lng] points, in metres. */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * SSRF-guarded, redirect-free client for the config upstream. UA identifies
     * us to public demo servers.
     */
    private function client(string $base): PendingRequest
    {
        return OutboundUrl::client($base, 8)
            ->withHeaders(['User-Agent' => 'Ledgerline (self-hosted personal cloud)']);
    }

    private function engine(): string
    {
        $engine = config('maps.route_engine', 'osrm');
        $engine = is_string($engine) ? strtolower($engine) : 'osrm';

        return $engine === 'graphhopper' ? 'graphhopper' : 'osrm';
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
