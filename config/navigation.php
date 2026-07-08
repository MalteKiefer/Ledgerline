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
    // The five most-used destinations: direct links on desktop, the five slots
    // of the mobile bottom bar.
    'primary' => [
        ['label' => 'messages.nav.gallery', 'route' => 'gallery.index', 'pattern' => 'gallery.*', 'icon' => 'photo'],
        ['label' => 'messages.nav.files', 'route' => 'files.index', 'pattern' => 'files.*', 'icon' => 'files'],
    ],
    // The rest: a "More" dropdown on desktop, the "More" sheet on mobile.
    'more' => [
        ['label' => 'messages.nav.notes', 'route' => 'notes.index', 'pattern' => 'notes.*', 'icon' => 'pencil'],
        ['label' => 'messages.nav.todos', 'route' => 'todos.index', 'pattern' => 'todos.*', 'icon' => 'todos'],
        ['label' => 'messages.nav.bookmarks', 'route' => 'bookmarks.index', 'pattern' => 'bookmarks.*', 'icon' => 'bookmark'],
        ['label' => 'messages.nav.downloads', 'route' => 'downloads.index', 'pattern' => 'downloads.*', 'icon' => 'arrow-down-tray'],
    ],
];
