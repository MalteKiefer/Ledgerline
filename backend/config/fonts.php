<?php

declare(strict_types=1);

// Self-hosted web fonts (public/fonts/fonts.css). Used for the invoice PDF font
// choice (renders reliably — the PDF is rasterised on our side) and offered in the
// mail editor (where e-mail clients may fall back to a system font). CSS family
// value => display label, sorted alphabetically by label.
return [
    'families' => [
        'Arial, sans-serif' => 'Arial',
        'Georgia, serif' => 'Georgia',
        'Inter, sans-serif' => 'Inter',
        'Lato, sans-serif' => 'Lato',
        'Merriweather, serif' => 'Merriweather',
        'Montserrat, sans-serif' => 'Montserrat',
        "'Open Sans', sans-serif" => 'Open Sans',
        "'Playfair Display', serif" => 'Playfair Display',
        'Roboto, sans-serif' => 'Roboto',
        "'Source Sans 3', sans-serif" => 'Source Sans 3',
    ],
];
