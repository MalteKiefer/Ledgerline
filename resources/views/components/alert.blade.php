@props([
    'variant' => 'info',
])

@php
    // One inline alert/status banner vocabulary across the app (auth flashes, form
    // errors, module error banners). iOS radius, tinted surface. A single root div so
    // Alpine bindings (x-show / x-text) forward cleanly onto it.
    $base = 'rounded-xl border px-4 py-3 text-sm';
    $variants = [
        'success' => 'border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950 text-green-700 dark:text-green-300',
        'error' => 'border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300',
        'warning' => 'border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-300',
        'info' => 'border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-950 text-blue-800 dark:text-blue-300',
        'neutral' => 'border-black/[0.06] dark:border-white/10 bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300',
    ];
    $classes = $base.' '.($variants[$variant] ?? $variants['info']);
@endphp

<div {{ $attributes->class($classes) }}>{{ $slot }}</div>
