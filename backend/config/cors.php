<?php

declare(strict_types=1);

/*
 * CORS for the API. The web SPA authenticates with a bearer token (not a
 * session cookie), so cross-origin requests carry no credentials and a
 * wildcard origin is safe. This lets the frontend be hosted separately and
 * point at any API base URL (Laravel now, a Go API later) without change.
 */
return [
    'paths' => ['api/*', 'up'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    // Bearer-token auth → no cookies cross-origin, so credentials stay off
    // (which is what allows the wildcard origin above).
    'supports_credentials' => false,
];
