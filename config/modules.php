<?php

declare(strict_types=1);

// Toggleable application modules. An admin can enable/disable each module per user
// or per group; the effective set is resolved in App\Models\User::allowedModules()
// (admins always get all; per-user override wins; else the union of the user's
// groups; else — nothing configured — all). Enforced by the `module` middleware,
// reflected in the nav, and exposed to native clients at GET /api/v1/me (user.modules).
//
//   label — translation key (nav label)
//   route — the module's web entry route name (gated by `module:<key>`)

return [
    'list' => [
        'finance' => ['label' => 'messages.nav.finance', 'route' => 'finance.index'],
        'files' => ['label' => 'messages.nav.files', 'route' => 'files.index'],
        'contacts' => ['label' => 'messages.nav.contacts', 'route' => 'contacts.index'],
        'notes' => ['label' => 'messages.nav.notes', 'route' => 'notes.index'],
        'calendar' => ['label' => 'messages.nav.calendar', 'route' => 'calendar.index'],
        'mail' => ['label' => 'messages.nav.mail', 'route' => 'mail.index'],
    ],
];
