<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\AppSettings;
use App\Services\Support\NominatimClient;
use App\Support\OutboundUrl;
use Throwable;

/**
 * Reverse-geocodes coordinates to a human-readable address. A self-hosted Photon
 * (config gallery.photon_url) is tried first so covered points never leave the
 * zero-knowledge boundary; anything it does not cover falls back to a Nominatim-
 * compatible endpoint (gallery.geocoder_url, public OSM by default). Triggered by
 * the viewer's place-picker, and by upload only when gallery.geocode_on_upload is
 * enabled (off by default). The resolved place is handed straight back to the
 * browser (which seals it into an opaque blob) and is NEVER cached server-side —
 * caching the resolved address at rest would be a plaintext-location leak. Only a
 * rate-limit timestamp (no location content) is kept in the cache.
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
        // Snap to a coarse grid so the coordinates sent out are blurred.
        [$lat, $lon] = $this->snapToGrid($lat, $lon);

        // In-boundary first; only fall through to OSM when Photon has no match.
        $photonUrl = config('gallery.photon_url', '');
        if (is_string($photonUrl) && $photonUrl !== '') {
            $viaPhoton = $this->viaPhoton($photonUrl, $lat, $lon);
            if ($viaPhoton['display'] !== null) {
                return $viaPhoton;
            }
        }

        return $this->viaNominatim($lat, $lon);
    }

    /**
     * @return array{display: ?string, address: array<string, string>}
     */
    private function viaNominatim(float $lat, float $lon): array
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

        $displayName = $json['display_name'] ?? null;
        $address = $json['address'] ?? null;

        return [
            'display' => (is_string($displayName) && $displayName !== '') ? $displayName : null,
            'address' => is_array($address) ? $this->stringifyMap($address) : [],
        ];
    }

    /**
     * Query a self-hosted Photon (/reverse → GeoJSON). Returns display=null when
     * the point is uncovered (empty features) or on any error, so the caller
     * falls back to Nominatim. No throttle: a self-hosted instance has no policy.
     *
     * @return array{display: ?string, address: array<string, string>}
     */
    private function viaPhoton(string $base, float $lat, float $lon): array
    {
        try {
            $response = OutboundUrl::client($base, 5)
                ->get($base.'/reverse', ['lat' => $lat, 'lon' => $lon, 'limit' => 1]);

            if (! $response->successful()) {
                return ['display' => null, 'address' => []];
            }

            $props = $response->json('features.0.properties');
            if (! is_array($props)) {
                return ['display' => null, 'address' => []];
            }

            return [
                'display' => $this->photonDisplay($props),
                'address' => $this->photonAddress($props),
            ];
        } catch (Throwable) {
            return ['display' => null, 'address' => []];
        }
    }

    /**
     * Build a single display line from Photon's structured GeoJSON properties
     * (it has no display_name field), or null if there is nothing usable.
     *
     * @param  array<mixed>  $p
     */
    private function photonDisplay(array $p): ?string
    {
        $street = $this->str($p['street'] ?? null) ?: $this->str($p['name'] ?? null);
        $line1 = trim($street.' '.$this->str($p['housenumber'] ?? null));
        $city = $this->str($p['city'] ?? null)
            ?: $this->str($p['town'] ?? null)
            ?: $this->str($p['village'] ?? null)
            ?: $this->str($p['district'] ?? null)
            ?: $this->str($p['county'] ?? null);

        $parts = array_values(array_filter([
            $line1,
            trim($this->str($p['postcode'] ?? null).' '.$city),
            $this->str($p['state'] ?? null),
            $this->str($p['country'] ?? null),
        ], static fn (string $s): bool => $s !== ''));

        $display = implode(', ', $parts);

        return $display !== '' ? $display : null;
    }

    /**
     * Map Photon properties to the same Nominatim-style address keys the client
     * already understands.
     *
     * @param  array<mixed>  $p
     * @return array<string, string>
     */
    private function photonAddress(array $p): array
    {
        $city = $this->str($p['city'] ?? null)
            ?: $this->str($p['town'] ?? null)
            ?: $this->str($p['village'] ?? null)
            ?: $this->str($p['district'] ?? null);
        $countryCode = $this->str($p['countrycode'] ?? null);

        return array_filter([
            'road' => $this->str($p['street'] ?? null),
            'house_number' => $this->str($p['housenumber'] ?? null),
            'city' => $city,
            'state' => $this->str($p['state'] ?? null),
            'postcode' => $this->str($p['postcode'] ?? null),
            'country' => $this->str($p['country'] ?? null),
            'country_code' => strtolower($countryCode),
        ], static fn (string $v): bool => $v !== '');
    }

    /**
     * Coerce a decoded-JSON value to a trimmed string, treating anything that
     * is not a string or number (arrays, null, bool) as empty — the same
     * graceful degradation the previous "(string) (… ?? '')" casts provided.
     */
    private function str(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Stringify all values of a decoded-JSON map, dropping non-scalar values
     * (mirrors the previous array_map('strval', …) over Nominatim's address).
     *
     * @param  array<mixed>  $map
     * @return array<string, string>
     */
    private function stringifyMap(array $map): array
    {
        $out = [];
        foreach ($map as $key => $value) {
            $out[(string) $key] = $this->str($value);
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
            $configured = AppSettings::current()->gallery_geocode_grid_km
                ?? config('gallery.geocode_grid_km', 0.5);
        } catch (Throwable) {
            $configured = config('gallery.geocode_grid_km', 0.5);
        }

        // A non-numeric value casts to 0.0 (the old (float) semantics), which the
        // guard below treats as "no snapping" — degrade rather than throw.
        $km = is_numeric($configured) ? (float) $configured : 0.0;

        if ($km <= 0) {
            return [$lat, $lon];
        }

        // ~111 km per degree of latitude; good enough for a caching grid.
        $step = $km / 111.0;

        return [round($lat / $step) * $step, round($lon / $step) * $step];
    }
}
