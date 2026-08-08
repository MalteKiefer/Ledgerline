@props([
    'variant' => 'gray',
])

@php
    // One small pill/badge vocabulary (roles, counts, status tags). A single root span
    // so Alpine bindings (x-text / x-show) forward cleanly onto it.
    $base = 'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-medium';
    $variants = [
        'accent' => 'bg-accent/15 text-accent',
        'gray' => 'bg-gray-500/15 text-md-on-surface-var dark:text-md-on-surface-var',
        'success' => 'bg-green-500/15 text-green-600 dark:text-green-400',
        'warning' => 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        'error' => 'bg-red-500/15 text-red-600 dark:text-red-400',
    ];
    $classes = $base.' '.($variants[$variant] ?? $variants['gray']);
@endphp

<span {{ $attributes->class($classes) }}>{{ $slot }}</span>
