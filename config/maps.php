<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Tile upstream (tileserver-gl sidecar)
    |--------------------------------------------------------------------------
    |
    | Base URL of the raster/vector tile server the Explore map relays through.
    | Same-origin relay: the browser only ever talks to /maps/tiles and /maps/
    | style on this host (the web CSP connect-src 'self' holds); the app fetches
    | the actual tiles from this upstream server-side through the SSRF guard. In
    | the Docker deployment this is the tileserver-gl service on the internal
    | network (docker compose --profile maps up). Leave empty to disable the map
    | relay entirely — the endpoints then 404 instead of crashing.
    |
    */

    'tile_upstream' => env('MAPS_TILE_UPSTREAM', 'http://tiles:8080'),

    /*
    |--------------------------------------------------------------------------
    | Tile cache lifetime (seconds)
    |--------------------------------------------------------------------------
    |
    | How long a relayed tile may be cached by the browser. Tiles are immutable
    | for a given style, so a long lifetime is safe and cuts round-trips.
    |
    */

    'tile_cache_seconds' => (int) env('MAPS_TILE_CACHE_SECONDS', 604800),

    /*
    |--------------------------------------------------------------------------
    | Style name
    |--------------------------------------------------------------------------
    |
    | The tileserver-gl style id whose style.json /maps/style relays (with its
    | tile URLs rewritten to same-origin /maps/tiles/...). Matches the style the
    | mounted .mbtiles serves.
    |
    */

    'style' => env('MAPS_STYLE', 'basic'),

];
