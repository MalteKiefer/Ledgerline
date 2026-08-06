<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Files\ReverseGeocoder;
use App\Services\Support\NominatimClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Module-independent geocoding endpoints, shared by Explore (place/POI search),
 * Contacts (address lookup) and Calendar (event location). Split out of the removed
 * gallery module — the outbound geocode is user-initiated, SSRF-guarded and never
 * cached server-side (caching a plaintext location at rest would be a location leak).
 */
class GeoController extends Controller
{
    /** Forward geocode: free-text place/POI → candidate coordinates. */
    public function geocode(Request $request, NominatimClient $nominatim): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'max:256']]);

        $json = $nominatim->get('search', [
            'q' => $request->string('q')->value(),
            'format' => 'jsonv2',
            'limit' => 6,
            'addressdetails' => 0,
        ]);

        $results = collect(is_array($json) ? $json : [])
            ->map(function ($r): array {
                $r = is_array($r) ? $r : [];
                $display = $r['display_name'] ?? '';
                $lat = $r['lat'] ?? null;
                $lon = $r['lon'] ?? null;

                return [
                    'display' => is_scalar($display) ? (string) $display : '',
                    'lat' => is_numeric($lat) ? (float) $lat : null,
                    'lng' => is_numeric($lon) ? (float) $lon : null,
                ];
            })
            ->filter(fn (array $r): bool => $r['display'] !== '' && $r['lat'] !== null && $r['lng'] !== null)
            ->values()
            ->all();

        return response()->json(['results' => $results])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Reverse geocode a coordinate to a place name. Resolution stays in the ZK
     * boundary when a self-hosted Photon is set (config geo.photon_url), falling back
     * to the configured Nominatim otherwise; the coordinate is snapped to a coarse grid
     * inside ReverseGeocoder before egress. The address is returned to the caller only
     * and NEVER cached server-side.
     */
    public function reverse(Request $request, ReverseGeocoder $geocoder): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $geocoder->lookupDetailed($request->float('lat'), $request->float('lng'));

        return response()->json([
            'place' => $result['display'],
            // Empty address serialises as {} (JSON object), not [] — a strictly-typed
            // client (iOS/Android) breaks on an array where an object is expected.
            'address' => (object) $result['address'],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
