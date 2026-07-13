<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\AppSettings;
use App\Services\Support\NominatimClient;
use Throwable;

/**
 * Reverse-geocodes coordinates to a human-readable address via a Nominatim-
 * compatible endpoint (config gallery.geocoder_url — the OSM public server by
 * default, or a self-hosted instance). Triggered by the viewer's place-picker,
 * and by upload only when gallery.geocode_on_upload is enabled (off by default).
 * The resolved place is handed straight back to the browser (which seals it into
 * an opaque blob) and is NEVER cached server-side — caching the resolved address
 * at rest would be a plaintext-location leak. Only a rate-limit timestamp (no
 * location content) is kept in the cache.
 */
class ReverseGeocoder
{
    public function __construct(private readonly NominatimClient $nominatim) {}

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
        $json = $this->nominatim->get('reverse', [
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
}
