@props([
    'variant' => 'filled',
    'size' => 'md',
    'icon' => null,
    'trailingIcon' => null,
    'href' => null,
    'type' => 'button',
])

@php
    // Material Design 3 button vocabulary — filled / tonal / outlined / text /
    // danger, three sizes, state layer (m3-state). App-wide: use this instead of
    // hand-rolled buttons. Alpine bindings use the :: convention on components.
    $base = 'm3-state inline-flex items-center justify-center rounded-lg font-medium transition select-none disabled:opacity-50 disabled:pointer-events-none';
    $sizes = [
        'sm' => 'min-h-8 gap-1.5 px-3 text-xs',
        'md' => 'min-h-10 gap-2 px-4 text-sm',
        'lg' => 'min-h-12 gap-2 px-6 text-base',
    ];
    $variants = [
        'filled' => 'bg-md-primary text-md-on-primary shadow-sm',
        'tonal' => 'bg-md-primary-container text-md-on-primary-container',
        'outlined' => 'border border-md-outline text-md-primary',
        'text' => 'text-md-primary',
        'danger' => 'border border-md-error-o/40 text-md-error-o',
    ];
    $iconSize = ['sm' => 'text-base', 'md' => 'text-lg', 'lg' => 'text-xl'][$size] ?? 'text-lg';
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['filled']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-m3.icon :name="$icon" class="{{ $iconSize }}" />@endif
        {{ $slot }}
        @if ($trailingIcon)<x-m3.icon :name="$trailingIcon" class="{{ $iconSize }}" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-m3.icon :name="$icon" class="{{ $iconSize }}" />@endif
        {{ $slot }}
        @if ($trailingIcon)<x-m3.icon :name="$trailingIcon" class="{{ $iconSize }}" />@endif
    </button>
@endif
