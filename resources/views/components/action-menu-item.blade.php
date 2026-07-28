{{-- A row inside <x-action-menu>. Defaults to a <button>; pass `href` for a link. `danger`
     styles it red + adds a top divider. Icon optional. --}}
@props(['icon' => null, 'danger' => false, 'href' => null])
@php
    $base = 'flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition';
    $tone = $danger
        ? 'border-t border-black/[0.06] dark:border-white/10 text-red-600 dark:text-red-400 hover:bg-red-500/10'
        : 'text-gray-700 dark:text-gray-300 hover:bg-accent/5 hover:text-accent';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base.' '.$tone]) }}>
        @if ($icon)<x-icon :name="$icon" class="h-4 w-4 shrink-0" />@endif{{ $slot }}
    </a>
@else
    <button type="button" {{ $attributes->merge(['class' => $base.' '.$tone]) }}>
        @if ($icon)<x-icon :name="$icon" class="h-4 w-4 shrink-0" />@endif{{ $slot }}
    </button>
@endif
