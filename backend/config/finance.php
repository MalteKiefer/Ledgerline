<?php

declare(strict_types=1);

return [
    // Daily FX-rate source for the fuzzy receipt↔booking amount suggestions. Rates are
    // fetched once a day (nightly) by `finance:fetch-fx` and cached; the request carries no
    // user data (it only GETs public rates) and passes the SSRF guard. Host from config,
    // never user input. Default = frankfurter.app (ECB data, no API key).
    'fx_url' => env('FINANCE_FX_URL', 'https://api.frankfurter.dev/v1/latest'),

    // Currencies to fetch (base is always EUR; stored inverted as X→EUR).
    'fx_symbols' => ['USD', 'GBP', 'CHF', 'JPY', 'CAD', 'AUD', 'SEK', 'NOK', 'DKK', 'PLN'],

    // Fallback X→EUR rates used only until the first successful fetch (or if it fails).
    // Deliberately approximate — suggestions only, never auto-attach.
    'fx_default' => [
        'EUR' => 1.0,
        'USD' => 0.92,
        'GBP' => 1.16,
        'CHF' => 1.04,
    ],
];
