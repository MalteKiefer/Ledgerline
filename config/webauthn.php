<?php

declare(strict_types=1);

$host = parse_url((string) config('app.url'), PHP_URL_HOST);

return [
    // Relying Party ID = the registrable domain the app is served on. Behind the
    // NetBird/HTTPS proxy this MUST be the public host (e.g. home.pinlo.me) and the
    // origin must match exactly, over HTTPS, or the browser rejects the ceremony.
    'rp_id' => env('WEBAUTHN_RP_ID', is_string($host) && $host !== '' ? $host : 'localhost'),
    'rp_name' => env('WEBAUTHN_RP_NAME', is_string(config('app.name')) ? config('app.name') : 'Ledgerline'),
    // Allowed origins for the ceremony (exact match). Defaults to APP_URL.
    'origins' => array_values(array_filter([
        is_string(config('app.url')) ? config('app.url') : null,
        env('WEBAUTHN_EXTRA_ORIGIN'),
    ])),
];
