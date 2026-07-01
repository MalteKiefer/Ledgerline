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

        return Cache::remember($key, now()->addDays(30), function () use ($lat, $lon): array {
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
        });
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
