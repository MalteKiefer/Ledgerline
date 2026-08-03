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
        'dashboard' => ['label' => 'messages.nav.dashboard', 'route' => 'dashboard'],
        'files' => ['label' => 'messages.nav.files', 'route' => 'files.index'],
        'gallery' => ['label' => 'messages.nav.gallery', 'route' => 'gallery.index'],
        'passwords' => ['label' => 'messages.nav.passwords', 'route' => 'passwords.index'],
        'notes' => ['label' => 'messages.nav.notes', 'route' => 'notes.index'],
        'todos' => ['label' => 'messages.nav.todos', 'route' => 'todos.index'],
        'bookmarks' => ['label' => 'messages.nav.bookmarks', 'route' => 'bookmarks.index'],
        'contacts' => ['label' => 'messages.nav.contacts', 'route' => 'contacts.index'],
        'mail' => ['label' => 'messages.nav.mail', 'route' => 'mail.index'],
        'finance' => ['label' => 'messages.nav.finance', 'route' => 'finance.index'],
        'health' => ['label' => 'messages.nav.health', 'route' => 'health.index'],
        'explore' => ['label' => 'messages.nav.explore', 'route' => 'explore'],
    ],
];
