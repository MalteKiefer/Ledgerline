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

    'currencies' => [
        'EUR', 'USD', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF',
        'RON', 'BGN', 'ISK', 'TRY', 'CAD', 'AUD', 'NZD', 'JPY', 'CNY', 'HKD',
        'SGD', 'INR', 'AED', 'SAR', 'ZAR', 'BRL', 'MXN',
    ],

    /*
    |--------------------------------------------------------------------------
    | Paper sizes
    |--------------------------------------------------------------------------
    |
    | Page sizes offered for the invoice PDF.
    |
    */

    'paper_sizes' => ['A4', 'A5', 'Letter', 'Legal'],

];
