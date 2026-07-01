<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Supported invoice languages
    |--------------------------------------------------------------------------
    |
    | Languages available for the company default and per-invoice override.
    | Keyed by locale code, value is the display label.
    |
    */

    'languages' => [
        'de' => 'Deutsch',
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported currencies
    |--------------------------------------------------------------------------
    |
    | ISO 4217 codes offered for the company default and per-invoice currency.
    |
    */

    'currencies' => ['EUR', 'USD', 'GBP', 'CHF'],

];
