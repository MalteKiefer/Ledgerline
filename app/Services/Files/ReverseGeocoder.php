<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\AppSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reverse-geocodes coordinates to a human-readable address via OpenStreetMap's
 * Nominatim service. Runs only inside the transient zero-knowledge
 * /gallery/process window: the resolved place is handed straight back to the
 * browser (which seals it into an opaque blob) and is NEVER cached server-side —
 * caching the resolved address at rest would be a plaintext-location leak. Only
 * a Nominatim rate-limit timestamp (no location content) is kept in the cache.
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
     * parts (road, city, state, postcode, country, …). The result is returned to
     * the caller only and never persisted server-side.
     *
     * @return array{display: ?string, address: array<string, string>}
     */
    public function lookupDetailed(float $lat, float $lon): array
    {
        // Snap to a coarse grid so the coordinates sent to OSM are blurred.
        [$lat, $lon] = $this->snapToGrid($lat, $lon);

        return $this->request($lat, $lon);
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
            'display' => ($json['display_name'] ?? null) ?: null,
            'address' => array_map('strval', $json['address'] ?? []),
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

            $response = Http::withHeaders(['User-Agent' => 'Ledgerline ERP (self-hosted)'])
                ->timeout(5)
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
     * Snap coordinates to the configured grid (in km) so photos taken close
     * together resolve to the same cached place instead of each hitting OSM.
     *
     * @return array{0: float, 1: float}
     */
    private function snapToGrid(float $lat, float $lon): array
    {
        try {
            $km = (float) (AppSettings::current()->gallery_geocode_grid_km
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
