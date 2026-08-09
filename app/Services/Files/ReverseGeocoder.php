<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\AppSettings;
use App\Support\OutboundUrl;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Reverse-geocodes coordinates to a human-readable address via OpenStreetMap's
 * Nominatim service. Results are cached; requests only ever go to the fixed
 * Nominatim host.
 */
class ReverseGeocoder
{
    private const HOST = 'https://nominatim.openstreetmap.org/reverse';

    private const SEARCH_HOST = 'https://nominatim.openstreetmap.org/search';

    public function lookup(float $lat, float $lon): ?string
    {
        return $this->lookupDetailed($lat, $lon)['display'];
    }

    /**
     * Reverse-geocode to both the full display name and the structured address
     * parts (road, city, state, postcode, country, …).
     *
     * @return array{display: ?string, address: array<string, string>}
     */
    public function lookupDetailed(float $lat, float $lon): array
    {
        // Snap to a grid so nearby coordinates share one lookup/result.
        [$lat, $lon] = $this->snapToGrid($lat, $lon);
        $key = 'geocode:'.round($lat, 5).','.round($lon, 5);

        // Reuse a previously resolved place. Failed lookups are NOT cached, so
        // re-reading metadata retries them and fills in places that were empty.
        $cached = Cache::get($key);
        if (is_array($cached) && ($cached['display'] ?? null) !== null) {
            return ['display' => $this->nstr($cached['display'] ?? null), 'address' => $this->strMap($cached['address'] ?? null)];
        }

        $result = $this->request($lat, $lon);

        if ($result['display'] !== null) {
            Cache::put($key, $result, now()->addDays(30));
        }

        return $result;
    }

    /**
     * @return array{display: ?string, address: array<string, string>}
     */
    private function request(float $lat, float $lon): array
    {
        $json = $this->nominatim(self::HOST, [
            'lat' => $lat,
            'lon' => $lon,
            'format' => 'jsonv2',
            'zoom' => 18,
            'addressdetails' => 1,
        ]);

        if ($json === null) {
            return ['display' => null, 'address' => []];
        }

        return [
            'display' => $this->nstr($json['display_name'] ?? null),
            'address' => $this->strMap($json['address'] ?? null),
        ];
    }

    /**
     * Perform a throttled Nominatim request with the shared User-Agent and
     * timeout, returning the decoded JSON body or null on any failure.
     *
     * @param  array<string, mixed>  $query
     * @return array<mixed>|null
     */
    private function nominatim(string $path, array $query): ?array
    {
        try {
            $this->throttle();

            // Route through the app-wide SSRF guard (redirect-free + resolved-IP pin)
            // for consistency with every other outbound client. The host is a fixed
            // constant today, so behaviour is unchanged; the guard applies if it ever
            // becomes config/DB-driven.
            $response = OutboundUrl::client($path, 5)
                ->withHeaders(['User-Agent' => 'Ledgerline ERP (self-hosted)'])
                ->get($path, $query);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Forward-geocode a free-text query (address / place) to candidate matches.
     * Results are cached per query; requests only go to the fixed Nominatim host.
     *
     * @return list<array{display: string, lat: float, lon: float}>
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $key = 'geocode:search:'.md5(mb_strtolower($query));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $this->normalizeSearch($cached);
        }

        $json = $this->nominatim(self::SEARCH_HOST, [
            'q' => $query,
            'format' => 'jsonv2',
            'limit' => 5,
            'addressdetails' => 0,
        ]);

        if ($json === null) {
            return [];
        }

        $results = $this->normalizeSearch($json);

        Cache::put($key, $results, now()->addDays(7));

        return $results;
    }

    /**
     * @return list<array{display: string, lat: float, lon: float}>
     */
    private function normalizeSearch(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $display = $this->str($row['display_name'] ?? ($row['display'] ?? null));
            if ($display === '') {
                continue;
            }
            $out[] = ['display' => $display, 'lat' => $this->toFloat($row['lat'] ?? null), 'lon' => $this->toFloat($row['lon'] ?? null)];
        }

        return $out;
    }

    /**
     * Snap coordinates to the configured grid (in km) so photos taken close
     * together resolve to the same cached place instead of each hitting OSM.
     *
     * @return array{0: float, 1: float}
     */
    private function snapToGrid(float $lat, float $lon): array
    {
        try {
            $km = $this->toFloat(AppSettings::current()->gallery_geocode_grid_km ?? config('gallery.geocode_grid_km', 0.5), 0.5);
        } catch (Throwable) {
            $km = $this->toFloat(config('gallery.geocode_grid_km', 0.5), 0.5);
        }

        if ($km <= 0) {
            return [$lat, $lon];
        }

        // ~111 km per degree of latitude; good enough for a caching grid.
        $step = $km / 111.0;

        return [round($lat / $step) * $step, round($lon / $step) * $step];
    }

    /**
     * Space requests across all workers so Nominatim's one-per-second policy is
     * respected during bulk imports. A short lock serialises workers; the stored
     * timestamp enforces the interval.
     */
    private function throttle(): void
    {
        $interval = $this->toInt(config('gallery.geocode_interval_ms', 1100), 1100);
        if ($interval <= 0) {
            return;
        }

        $lock = Cache::lock('geocode:nominatim:lock', 15);

        try {
            $lock->block(30);

            $last = $this->toFloat(Cache::get('geocode:nominatim:last', 0.0), 0.0);
            $waitMs = $interval - (int) ((microtime(true) - $last) * 1000);
            if ($waitMs > 0 && $waitMs <= $interval) {
                usleep($waitMs * 1000);
            }

            Cache::put('geocode:nominatim:last', microtime(true), now()->addMinutes(5));
        } catch (Throwable) {
            // Could not acquire the lock in time; proceed without spacing rather
            // than fail the whole metadata read.
        } finally {
            $lock->release();
        }
    }

    private function nstr(mixed $v): ?string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';

        return $s !== '' ? $s : null;
    }

    private function str(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }

    private function toFloat(mixed $v, float $default = 0.0): float
    {
        return is_numeric($v) ? (float) $v : $default;
    }

    private function toInt(mixed $v, int $default = 0): int
    {
        return is_numeric($v) ? (int) $v : $default;
    }

    /**
     * @return array<string, string>
     */
    private function strMap(mixed $v): array
    {
        if (! is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $k => $val) {
            if (is_scalar($val)) {
                $out[(string) $k] = (string) $val;
            }
        }

        return $out;
    }
}
