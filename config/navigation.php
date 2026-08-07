<?php

// The app is finance-only. The Finance page is a single Alpine SPA that renders
// its OWN in-page section tab bar (Dashboard / Invoices / Payments / Receipts /
// Projects / Partners / Stats / Settings, driven by setSection()). There is no
// multi-module top nav: the desktop x-nav keeps only brand + notifications + the
// account menu, and the mobile x-mobile-nav drawer keeps only the account
// actions. Nothing reads any key from this config anymore — the file is retained
// only to document why the global navigation carries no section links.
return [];
