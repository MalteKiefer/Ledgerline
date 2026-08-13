<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Files\ReverseGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * App-generic forward-geocode (address autocomplete). Both the calendar event
 * editor and the contacts map preview use it, so it is authenticated but NOT
 * behind a single module gate. Requests are server-proxied to OpenStreetMap's
 * Nominatim through ReverseGeocoder (SSRF-guarded via OutboundUrl, rate-limited,
 * cached). The query + coordinates are never logged and the response is
 * no-store so intermediaries never cache a user's location search.
 */
class GeoController extends Controller
{
    /** Max matches returned to the client (Nominatim returns up to 5). */
    private const LIMIT = 8;

    /**
     * GET /geo/search?q= → {results: [{display, lat, lon}]}. A blank/too-short
     * query returns an empty list WITHOUT hitting the upstream (politeness + no
     * pointless lookups). Auth is enforced by the route middleware.
     */
    public function search(Request $request, ReverseGeocoder $geocoder): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        $results = mb_strlen($q) < 3
            ? []
            : array_slice($geocoder->search($q), 0, self::LIMIT);

        return response()->json(['results' => $results])
            ->header('Cache-Control', 'no-store');
    }
}
