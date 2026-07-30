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
    // The most-used destinations: direct links on desktop, the primary slots
    // of the mobile navigation drawer.
    'primary' => [
        ['label' => 'messages.nav.dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'home', 'module' => 'dashboard'],
        ['label' => 'messages.nav.files', 'route' => 'files.index', 'pattern' => 'files.*', 'icon' => 'files', 'module' => 'files'],
        ['label' => 'messages.nav.gallery', 'route' => 'gallery.index', 'pattern' => 'gallery.*', 'icon' => 'photo', 'module' => 'gallery'],
    ],
    // The rest: a "More" dropdown on desktop, the "More" sheet on mobile.
    'more' => [
        ['label' => 'messages.nav.notes', 'route' => 'notes.index', 'pattern' => 'notes.*', 'icon' => 'pencil', 'module' => 'notes'],
        ['label' => 'messages.nav.todos', 'route' => 'todos.index', 'pattern' => 'todos.*', 'icon' => 'todos', 'module' => 'todos'],
        ['label' => 'messages.nav.bookmarks', 'route' => 'bookmarks.index', 'pattern' => 'bookmarks.*', 'icon' => 'bookmark', 'module' => 'bookmarks'],
        ['label' => 'messages.nav.finance', 'route' => 'finance.index', 'pattern' => 'finance.*', 'icon' => 'banknotes', 'module' => 'finance'],
        ['label' => 'messages.nav.health', 'route' => 'health.index', 'pattern' => 'health.*', 'icon' => 'heart', 'module' => 'health'],
        ['label' => 'messages.nav.explore', 'route' => 'explore', 'pattern' => 'explore', 'icon' => 'map', 'module' => 'explore'],
    ],
];
