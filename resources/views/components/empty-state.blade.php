@props([
    'icon' => null,
])

@php
    // Consistent empty-state placeholder (no items / no results). The component owns the
    // colour/centre/size; the caller owns the spacing (pass mt-*/py-* via class), so it
    // is a drop-in for the existing `<p class="… text-center text-sm text-md-on-surface-var …">`.
    // Single root so Alpine bindings (x-show / x-text) forward cleanly. With an icon it
    // stacks a muted glyph over the text.
    $classes = 'text-center text-sm text-md-on-surface-var dark:text-md-on-surface-var';
@endphp

@if ($icon)
    <div {{ $attributes->class($classes) }}>
        <x-icon :name="$icon" class="mx-auto h-8 w-8 text-md-on-surface-var dark:text-md-on-surface-var" />
        <p class="mt-2">{{ $slot }}</p>
    </div>
@else
    <p {{ $attributes->class($classes) }}>{{ $slot }}</p>
@endif
