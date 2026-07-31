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

// The app is finance-only and the Finance page is a single Alpine SPA that
// renders its OWN in-page section tab bar (Dashboard / Invoices / Payments /
// Receipts / Projects / Partners / Stats / Settings, driven by setSection()).
// The global top bar therefore carries NO section links — that would duplicate
// the SPA tabs (and a fragment link can't drive the SPA). The top bar keeps only
// brand + notifications + the account menu (profile / settings / logout).
return [
    'primary' => [],
    'more' => [],
];
