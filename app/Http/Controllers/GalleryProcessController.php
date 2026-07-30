<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Files\ReverseGeocoder;
use App\Services\Support\NominatimClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gallery geocoding helpers. Forward-geocode a place query (bulk location picker)
 * and reverse-geocode a coordinate (viewer location display). Both pass through
 * the server only (the client CSP forbids third-party calls) and are never
 * persisted. The former ZK image-transform endpoints (process/analyze/embed-text)
 * were removed with the plaintext-relational Gallery pivot — GalleryController now
 * derives renditions/metadata server-side from the stored photo rows.
 */
class GalleryProcessController extends Controller
{
    /**
     * Forward-geocode a free-text place query to candidate coordinates for the
     * bulk location picker. The query and results pass through the server only
     * (client CSP forbids third-party calls) and are never persisted.
     */
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
     * Reverse-geocode a coordinate to a place name for the mobile viewer's location
     * display. Resolution stays in the ZK boundary when a self-hosted Photon is set
     * (config gallery.photon_url), falling back to the configured Nominatim otherwise;
     * the coordinate is snapped to a coarse grid inside ReverseGeocoder before egress.
     * The resolved address is returned to the caller only and NEVER cached server-side —
     * caching a plaintext location at rest would be a location leak. The mobile client
     * caches the result encrypted (sealed with the vault key) on the device.
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
            // Cast to an object so an EMPTY address serialises as {} (JSON object),
            // not [] (JSON array) — PHP json_encode turns an empty PHP array into a
            // JSON array, which breaks a strictly-typed client (iOS/Android). Now the
            // shape is always an object (possibly empty).
            'address' => (object) $result['address'],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
