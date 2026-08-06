<?php

declare(strict_types=1);

// Geo/geocoding config, shared by Explore (place search), Contacts (address
// geocode) and Calendar (event location). Split out of the removed gallery
// module — these outbound geocoding paths are module-independent. All egress is
// user-initiated, SSRF-guarded (OutboundUrl) and coordinate-grid-snapped before
// leaving the box (see Services\Files\ReverseGeocoder + Services\Support\NominatimClient).
return [
    // Forward geocoder (place/POI text → coordinates). OSM public server by default;
    // point at a self-hosted Nominatim to keep queries in-boundary.
    'geocoder_url' => rtrim((string) env('GEOCODER_URL', 'https://nominatim.openstreetmap.org'), '/'),

    // Minimum spacing between forward-geocode requests (politeness / rate policy).
    'geocode_interval_ms' => (int) env('GEOCODE_INTERVAL_MS', 1100),

    // Reverse geocoder (coordinate → place name). Prefer a self-hosted Photon
    // (in-boundary, no egress); falls back to the configured Nominatim when empty.
    'photon_url' => rtrim((string) env('PHOTON_URL', ''), '/'),

    // Coordinate grid (km) the reverse geocoder snaps to before any egress — coarsens
    // the location so an exact position never leaves the box.
    'geocode_grid_km' => (float) env('GEOCODE_GRID_KM', 0.5),
];
