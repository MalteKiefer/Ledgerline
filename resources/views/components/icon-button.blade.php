@props([
    'name',
    'tone' => 'gray',
    'variant' => 'ghost',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    // Canonical icon-only button (row actions, toolbars, close/copy/menu triggers).
    // Replaces the dozens of hand-rolled `<button class="rounded p-1.5 text-gray-500
    // hover:bg-accent/5 …">` across the app so every icon action looks and behaves
    // the same. Touch-safe min target, iOS radius. Pass an aria-label via attributes.
    $base = 'inline-flex items-center justify-center rounded-lg transition disabled:opacity-50';
    $sizes = [
        'sm' => 'min-h-8 min-w-8 p-1.5',
        'md' => 'min-h-10 min-w-10 p-2',
        'lg' => 'min-h-11 min-w-11 p-2.5',
    ];
    $iconSizes = ['sm' => 'h-4 w-4', 'md' => 'h-5 w-5', 'lg' => 'h-5 w-5'];
    $tones = [
        'gray' => 'text-gray-500 hover:bg-accent/5 hover:text-accent dark:text-gray-400',
        'accent' => 'text-accent hover:bg-accent/10',
        'red' => 'text-red-600 hover:bg-red-500/10 dark:text-red-400',
    ];
    $solid = 'll-accent shadow-sm shadow-accent/30 hover:brightness-105';
    $tone = $variant === 'solid' ? $solid : ($tones[$tone] ?? $tones['gray']);
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.$tone;
    $iconClass = $iconSizes[$size] ?? $iconSizes['md'];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}><x-icon :name="$name" class="{{ $iconClass }}" /></a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}><x-icon :name="$name" class="{{ $iconClass }}" /></button>
@endif
