@props(['tone' => 'neutral', 'icon' => null])

@php
    $tones = [
        'success' => 'bg-md-success-c text-md-success-o',
        'warning' => 'bg-md-warning-c text-md-warning-o',
        'error' => 'bg-md-error-c text-md-error-o',
        'accent' => 'bg-md-selected text-md-primary',
        'neutral' => 'bg-md-neutral-c text-md-neutral-o',
    ];
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold '.($tones[$tone] ?? $tones['neutral'])) }}>
    @if ($icon)<x-m3.icon :name="$icon" class="text-sm" />@endif{{ $slot }}
</span>
