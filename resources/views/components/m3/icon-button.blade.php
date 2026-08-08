@props([
    'name' => '',
    'tone' => 'standard',
    'tooltip' => null,
    'href' => null,
    'type' => 'button',
    'size' => 'md',
])

@php
    $box = ['sm' => 'w-9 h-9', 'md' => 'w-10 h-10', 'lg' => 'w-12 h-12'][$size] ?? 'w-10 h-10';
    $glyph = ['sm' => 'text-lg', 'md' => 'text-xl', 'lg' => 'text-2xl'][$size] ?? 'text-xl';
    $tones = [
        'standard' => 'text-md-on-surface-var',
        'tonal' => 'bg-md-secondary-container text-md-on-primary-container',
        'primary' => 'text-md-primary',
        'danger' => 'text-md-error-o',
    ];
    $classes = 'm3-state inline-flex items-center justify-center rounded-full transition select-none '.$box.' '.($tones[$tone] ?? $tones['standard']);
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($tooltip) title="{{ $tooltip }}" aria-label="{{ $tooltip }}" @endif {{ $attributes->class($classes) }}><x-m3.icon :name="$name" class="{{ $glyph }}" /></a>
@else
    <button type="{{ $type }}" @if ($tooltip) title="{{ $tooltip }}" aria-label="{{ $tooltip }}" @endif {{ $attributes->class($classes) }}><x-m3.icon :name="$name" class="{{ $glyph }}" /></button>
@endif
