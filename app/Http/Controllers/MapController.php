<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Support\MapRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Explore tour-planner auto-routing endpoint. Given the ordered waypoints the
 * user clicked while planning a tour, this snaps them onto real paths/roads via
 * an OSRM-compatible upstream (see {@see MapRouter}) and returns the snapped
 * geometry + distance/duration.
 *
 * This is a user-initiated, opt-in boundary crossing (identical class to the
 * gallery place-picker geocoding): it is only reached when the user enables
 * "Follow paths / Auto-route", and it reveals the planned-route geometry to the
 * router only for that one request. The coordinates are NEVER logged or
 * persisted server-side. When the upstream is unset/unreachable or no route
 * exists, a clean 200 { geometry: null } is returned so the client falls back to
 * straight lines — never a crash.
 */
class MapController extends Controller
{
    /** Snap an ordered waypoint list to a real path/road route. */
    public function route(Request $request, MapRouter $router): JsonResponse
    {
        $request->validate([
            // "lat,lng;lat,lng;…" — 2..25 waypoints, each within coordinate range.
            'points' => ['required', 'string', 'max:1024'],
        ]);

        $waypoints = $this->parsePoints($request->string('points')->value());
        abort_if(count($waypoints) < 2 || count($waypoints) > 25, 422, 'Between 2 and 25 waypoints are required.');

        $result = $router->route($waypoints);

        return response()->json($result ?? ['geometry' => null])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Parse a "lat,lng;lat,lng;…" string into validated [lat, lng] pairs,
     * dropping any malformed or out-of-range segment.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function parsePoints(string $raw): array
    {
        $out = [];
        foreach (explode(';', $raw) as $segment) {
            $parts = explode(',', trim($segment));
            if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                continue;
            }
            $lat = (float) $parts[0];
            $lng = (float) $parts[1];
            if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
                continue;
            }
            $out[] = [$lat, $lng];
        }

        return $out;
    }
}
