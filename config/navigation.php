<?php

// Single source of truth for the app navigation, consumed by both the desktop
// top bar (x-nav) and the mobile bottom tab bar (x-mobile-nav). The app is
// finance-only: every destination is a section of the Finance SPA, opened via a
// URL fragment (#<section>) that the invoices Alpine component restores on load.
//
//   label    — translation key
//   route    — route name for route() (always finance.index)
//   fragment — URL hash selecting the Finance section
//   pattern  — routeIs() pattern for the active state
//   icon     — x-icon name (monochrome set)
//   module   — module gate key

return [
    // The most-used Finance sections: direct links on desktop, the primary slots
    // of the mobile bottom-tab bar.
    'primary' => [
        ['label' => 'invoices.tab_dashboard', 'route' => 'finance.index', 'fragment' => '#dashboard', 'pattern' => 'finance.*', 'icon' => 'home', 'module' => 'finance'],
        ['label' => 'invoices.tab_invoices', 'route' => 'finance.index', 'fragment' => '#invoices', 'pattern' => 'finance.*', 'icon' => 'document-text', 'module' => 'finance'],
        ['label' => 'invoices.tab_payments', 'route' => 'finance.index', 'fragment' => '#payments', 'pattern' => 'finance.*', 'icon' => 'credit-card', 'module' => 'finance'],
        ['label' => 'invoices.tab_receipts', 'route' => 'finance.index', 'fragment' => '#receipts', 'pattern' => 'finance.*', 'icon' => 'receipt-percent', 'module' => 'finance'],
    ],
    // The rest: a "More" dropdown on desktop, the "More" sheet on mobile.
    'more' => [
        ['label' => 'invoices.tab_projects', 'route' => 'finance.index', 'fragment' => '#projects', 'pattern' => 'finance.*', 'icon' => 'folder', 'module' => 'finance'],
        ['label' => 'invoices.tab_partners', 'route' => 'finance.index', 'fragment' => '#partners', 'pattern' => 'finance.*', 'icon' => 'user-group', 'module' => 'finance'],
        ['label' => 'invoices.tab_stats', 'route' => 'finance.index', 'fragment' => '#stats', 'pattern' => 'finance.*', 'icon' => 'chart-bar', 'module' => 'finance'],
    ],
];
