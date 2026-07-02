<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            return $cached;
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
        try {
            $this->throttle();

            $response = Http::withHeaders(['User-Agent' => 'Ledgerline ERP (self-hosted)'])
                ->timeout(5)
                ->get(self::HOST, [
                    'lat' => $lat,
                    'lon' => $lon,
                    'format' => 'jsonv2',
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                return ['display' => null, 'address' => []];
            }

            return [
                'display' => $response->json('display_name') ?: null,
                'address' => array_map('strval', $response->json('address') ?: []),
            ];
        } catch (Throwable) {
            return ['display' => null, 'address' => []];
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
            return $cached;
        }

        try {
            $this->throttle();

            $response = Http::withHeaders(['User-Agent' => 'Ledgerline ERP (self-hosted)'])
                ->timeout(5)
                ->get(self::SEARCH_HOST, [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 5,
                    'addressdetails' => 0,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $results = collect($response->json() ?: [])
                ->map(static fn (array $row): array => [
                    'display' => (string) ($row['display_name'] ?? ''),
                    'lat' => (float) ($row['lat'] ?? 0),
                    'lon' => (float) ($row['lon'] ?? 0),
                ])
                ->filter(static fn (array $r): bool => $r['display'] !== '')
                ->values()
                ->all();

            Cache::put($key, $results, now()->addDays(7));

            return $results;
        } catch (Throwable) {
            return [];
        }
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
            $km = (float) (CompanyProfile::current()->gallery_geocode_grid_km
                ?? config('gallery.geocode_grid_km', 0.5));
        } catch (Throwable) {
            $km = (float) config('gallery.geocode_grid_km', 0.5);
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
        $interval = (int) config('gallery.geocode_interval_ms', 1100);
        if ($interval <= 0) {
            return;
        }

        $lock = Cache::lock('geocode:nominatim:lock', 15);

        try {
            $lock->block(30);

            $last = (float) Cache::get('geocode:nominatim:last', 0.0);
            $waitMs = $interval - (int) ((microtime(true) - $last) * 1000);
            if ($waitMs > 0 && $waitMs <= $interval) {
                usleep($waitMs * 1000);
            }

            Cache::put('geocode:nominatim:last', microtime(true), now()->addMinutes(5));
        } catch (Throwable) {
            // Could not acquire the lock in time; proceed without spacing rather
            // than fail the whole metadata read.
        } finally {
            optional($lock)->release();
        }
    }
}
