<?php

// Single source of truth for the app navigation, consumed by both the desktop
// top bar (x-nav) and the mobile bottom tab bar (x-mobile-nav). Each item is a
// static definition; the components resolve url/label/active at render time.
//
//   label   — translation key
//   route   — route name for route()
//   pattern — routeIs() pattern for the active state
//   icon    — x-icon name (monochrome set)

return [
    // The four most-used destinations: direct links on desktop, the four slots
    // of the mobile navigation drawer primary section.
    'primary' => [
        ['label' => 'messages.nav.dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'home'],
        ['label' => 'messages.nav.files', 'route' => 'files.index', 'pattern' => 'files.*', 'icon' => 'files'],
        ['label' => 'messages.nav.gallery', 'route' => 'gallery.index', 'pattern' => 'gallery.*', 'icon' => 'photo'],
        ['label' => 'messages.nav.passwords', 'route' => 'passwords.index', 'pattern' => 'passwords.*', 'icon' => 'key'],
    ],
    // The rest: a "More" dropdown on desktop, the "More" sheet on mobile.
    'more' => [
        ['label' => 'messages.nav.notes', 'route' => 'notes.index', 'pattern' => 'notes.*', 'icon' => 'pencil'],
        ['label' => 'messages.nav.todos', 'route' => 'todos.index', 'pattern' => 'todos.*', 'icon' => 'todos'],
        ['label' => 'messages.nav.bookmarks', 'route' => 'bookmarks.index', 'pattern' => 'bookmarks.*', 'icon' => 'bookmark'],
        ['label' => 'messages.nav.contacts', 'route' => 'contacts.index', 'pattern' => 'contacts.*', 'icon' => 'users'],
        ['label' => 'messages.nav.invoices', 'route' => 'invoices.index', 'pattern' => 'invoices.*', 'icon' => 'document-text'],
        ['label' => 'messages.nav.health', 'route' => 'health.index', 'pattern' => 'health.*', 'icon' => 'heart'],
    ],
];
