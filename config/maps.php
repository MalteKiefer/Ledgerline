<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Explore tour auto-routing (OSRM)
    |--------------------------------------------------------------------------
    |
    | When the user enables "Follow paths / Auto-route" while planning a tour in
    | the Explore module, the clicked waypoints are POSTed to /maps/route, which
    | snaps them onto real paths/roads via an OSRM-compatible routing upstream
    | and returns the snapped geometry + distance/duration. This is a
    | user-initiated, opt-in boundary crossing: the planned-route geometry leaves
    | the zero-knowledge boundary (it reaches the router) ONLY while auto-route is
    | switched on — exactly the same class of egress as the gallery place-picker
    | geocoding. The coordinates are never logged or persisted server-side.
    |
    | route_upstream — the OSRM-compatible base URL. The public OSRM demo server
    | is the default; it can be self-hosted (docker + osrm-backend) to keep every
    | routing request in-boundary. Leave EMPTY to disable auto-routing entirely —
    | the planner then falls back to straight lines and never egresses.
    | The URL still passes through the SSRF guard (OutboundUrl) regardless.
    |
    | route_profile — the OSRM routing profile. "foot" (walking/hiking) is the
    | Explore default; "bike" or "car"/"driving" work if the upstream serves them.
    |
    */

    'route_upstream' => rtrim((string) env('MAPS_ROUTE_UPSTREAM', 'https://router.project-osrm.org'), '/'),

    'route_profile' => env('MAPS_ROUTE_PROFILE', 'foot'),

];
