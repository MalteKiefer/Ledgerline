{{-- Category icon resolved at runtime: renders every candidate icon server-side,
     x-show toggles the one matching the Alpine expression `$expr` (e.g. catIcon(c)).
     A reactive <x-icon ::name> does NOT work (the SVG is server-rendered). Kept in
     sync with FinanceController::CATEGORY_ICONS + invoices.js catIconOptions. --}}
@php
    $icons = ['hashtag', 'tag', 'banknotes', 'credit-card', 'wallet', 'building-library',
        'receipt-percent', 'chart-bar', 'arrow-trending-up', 'arrow-trending-down',
        'globe', 'globe-alt', 'home', 'camera', 'photo', 'film', 'bell', 'bookmark',
        'star', 'heart', 'calendar', 'clock', 'document', 'document-text',
        'document-duplicate', 'folder', 'inbox-stack', 'archive-box', 'server', 'key',
        'lock-closed', 'shield', 'shield-check', 'wifi', 'command-line', 'beaker',
        'thermometer', 'scale', 'cake', 'sparkles', 'map-pin', 'map', 'route',
        'paperclip', 'paper-clip', 'envelope', 'printer', 'users', 'user-group',
        'sun', 'moon'];
@endphp
@foreach ($icons as $ic)
    <span x-show="{{ $expr }} === '{{ $ic }}'"><x-icon :name="$ic" :class="$cls ?? 'h-4 w-4 text-white'" /></span>
@endforeach
