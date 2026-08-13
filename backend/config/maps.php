<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Explore tour auto-routing (OSRM or self-hosted GraphHopper)
    |--------------------------------------------------------------------------
    |
    | When the user enables "Follow paths / Auto-route" while planning a tour in
    | the Explore module, the clicked waypoints are POSTed to /maps/route, which
    | snaps them onto real paths/roads via a routing upstream and returns the
    | snapped geometry + distance/duration (and, on GraphHopper, elevation +
    | surface breakdown). This is a user-initiated, opt-in boundary crossing: the
    | planned-route geometry leaves the zero-knowledge boundary (it reaches the
    | router) ONLY while auto-route is switched on — exactly the same class of
    | egress as the gallery place-picker geocoding. The coordinates are never
    | logged or persisted server-side, and every request goes through the SSRF
    | guard (OutboundUrl) whichever engine is selected.
    |
    | route_engine — which upstream protocol to speak, "osrm" (default) or
    | "graphhopper". OSRM fills geometry/distance/duration only; a self-hosted
    | GraphHopper additionally returns elevation, ascent/descent and an OSM
    | surface breakdown. Any unrecognised value falls back to "osrm".
    |
    | route_upstream — the routing base URL (this is the config value the SSRF
    | allowlist is built from — NEVER user input). The public OSRM demo server is
    | the default; point it at a self-hosted OSRM or, with route_engine set to
    | "graphhopper", at http://graphhopper:8989 (see docker-compose "maps"
    | profile) to keep every routing request in-boundary. Leave EMPTY to disable
    | auto-routing entirely — the planner then falls back to straight lines and
    | never egresses.
    |
    | route_profile — the routing profile name. For OSRM: "foot" (walking/hiking,
    | the Explore default), "bike" or "car"/"driving" if the upstream serves them.
    | For GraphHopper it is the configured profile (e.g. "hike" or "foot") from the
    | GraphHopper config.yml; set MAPS_ROUTE_PROFILE to match.
    |
    */

    'route_engine' => (function (): string {
        $engine = strtolower(trim((string) env('MAPS_ROUTE_ENGINE', 'osrm')));

        return in_array($engine, ['osrm', 'graphhopper'], true) ? $engine : 'osrm';
    })(),

    'route_upstream' => rtrim((string) env('MAPS_ROUTE_UPSTREAM', 'https://router.project-osrm.org'), '/'),

    'route_profile' => env('MAPS_ROUTE_PROFILE', 'foot'),

];
