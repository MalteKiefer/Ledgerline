@props([
    'variant' => 'secondary',
    'icon' => null,
    'href' => null,
    'type' => 'button',
])

@php
    // One button vocabulary across every app: primary (dark), secondary
    // (outline) and danger (red outline). Same padding, radius and text size.
    $base = 'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition disabled:opacity-60';
    $variants = [
        'primary' => 'bg-gray-900 text-white hover:bg-gray-800',
        'secondary' => 'border border-gray-300 text-gray-700 hover:bg-gray-50',
        'danger' => 'border border-red-300 text-red-700 hover:bg-red-50',
    ];
    $classes = $base.' '.($variants[$variant] ?? $variants['secondary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" class="h-4 w-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" class="h-4 w-4" />@endif
        {{ $slot }}
    </button>
@endif
