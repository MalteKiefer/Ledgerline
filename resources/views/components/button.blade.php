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
        'primary' => 'll-accent shadow-sm shadow-accent/30 hover:brightness-105',
        'secondary' => 'border border-gray-300 text-gray-700 hover:border-accent hover:text-accent hover:bg-accent/5 dark:border-gray-700 dark:text-gray-300',
        'danger' => 'border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950',
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
