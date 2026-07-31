{{-- Category icon resolved at runtime: renders every candidate icon server-side,
     x-show toggles the one matching the Alpine expression `$expr` (e.g. catIcon(c)).
     A reactive <x-icon ::name> does NOT work (the SVG is server-rendered). Kept in
     sync with FinanceController::CATEGORY_ICONS + invoices.js catIconOptions. --}}
@php
    $icons = [
        'hashtag', 'tag', 'banknotes', 'credit-card', 'wallet', 'currency-euro',
        'currency-dollar', 'currency-pound', 'currency-yen', 'currency-rupee', 'receipt-percent', 'receipt-refund',
        'calculator', 'building-library', 'building-office', 'building-office-2', 'building-storefront', 'briefcase',
        'chart-bar', 'chart-bar-square', 'chart-pie', 'presentation-chart-line', 'presentation-chart-bar', 'arrow-trending-up',
        'arrow-trending-down', 'table-cells', 'list-bullet', 'queue-list', 'document', 'document-text',
        'document-check', 'document-duplicate', 'document-magnifying-glass', 'document-currency-euro', 'document-currency-dollar', 'document-plus',
        'document-minus', 'clipboard-document', 'clipboard-document-check', 'clipboard-document-list', 'newspaper', 'book-open',
        'folder', 'folder-open', 'archive-box', 'archive-box-arrow-down', 'inbox', 'inbox-stack',
        'rectangle-stack', 'square-3-stack-3d', 'rectangle-group', 'server', 'server-stack', 'cpu-chip',
        'shopping-cart', 'shopping-bag', 'gift', 'gift-top', 'truck', 'cube',
        'cube-transparent', 'wrench', 'wrench-screwdriver', 'cog-6-tooth', 'cog-8-tooth', 'bolt',
        'fire', 'light-bulb', 'command-line', 'beaker', 'scale', 'swatch',
        'paint-brush', 'pencil-square', 'scissors', 'envelope', 'at-symbol', 'phone',
        'phone-arrow-up-right', 'chat-bubble-left', 'chat-bubble-left-right', 'chat-bubble-oval-left', 'megaphone', 'video-camera',
        'microphone', 'musical-note', 'speaker-wave', 'signal', 'rss', 'cloud',
        'cloud-arrow-up', 'cloud-arrow-down', 'globe', 'globe-alt', 'globe-europe-africa', 'globe-americas',
        'globe-asia-australia', 'map', 'map-pin', 'route', 'home', 'home-modern',
        'camera', 'photo', 'film', 'printer', 'device-tablet', 'users',
        'user-group', 'academic-cap', 'hand-thumb-up', 'hand-thumb-down', 'hand-raised', 'trophy',
        'flag', 'ticket', 'bell', 'bell-alert', 'bookmark', 'star',
        'heart', 'sparkles', 'calendar', 'calendar-days', 'calendar-date-range', 'clock',
        'sun', 'moon', 'plus-circle', 'minus-circle', 'check-badge', 'exclamation-circle',
        'question-mark-circle', 'adjustments-horizontal', 'adjustments-vertical', 'funnel', 'bars-arrow-down', 'bars-arrow-up',
        'eye-slash', 'key', 'lock-closed', 'shield', 'shield-check', 'wifi',
        'paper-clip', 'backspace', 'battery-100', 'thermometer', 'cake',
    ];
@endphp
@foreach ($icons as $ic)
    <span x-show="{{ $expr }} === '{{ $ic }}'"><x-icon :name="$ic" :class="$cls ?? 'h-4 w-4 text-white'" /></span>
@endforeach
