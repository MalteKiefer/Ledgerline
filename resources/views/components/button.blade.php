@props([
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'href' => null,
    'type' => 'button',
])

@php
    // One button vocabulary across every app: primary (accent gradient), secondary
    // (outline) and danger (red outline) — same iOS radius, three sizes. Pages must
    // use this instead of hand-rolling `ll-accent`/border buttons so the look stays
    // consistent everywhere.
    $base = 'inline-flex items-center justify-center rounded-xl font-medium transition disabled:opacity-60';
    $sizes = [
        'sm' => 'min-h-9 gap-1 px-3 py-1.5 text-xs',
        'md' => 'min-h-11 gap-1.5 px-3.5 py-2 text-sm',
        'lg' => 'min-h-12 gap-2 px-4 py-2.5 text-base',
    ];
    $iconSizes = ['sm' => 'h-3.5 w-3.5', 'md' => 'h-4 w-4', 'lg' => 'h-5 w-5'];
    $variants = [
        'primary' => 'll-accent shadow-sm shadow-accent/30 hover:brightness-105',
        'secondary' => 'border border-md-outline-variant text-md-on-surface-var hover:border-accent hover:text-accent hover:bg-accent/5 dark:border-md-outline-variant dark:text-md-on-surface-var',
        'danger' => 'border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['secondary']);
    $iconClass = $iconSizes[$size] ?? $iconSizes['md'];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" class="{{ $iconClass }}" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" class="{{ $iconClass }}" />@endif
        {{ $slot }}
    </button>
@endif
