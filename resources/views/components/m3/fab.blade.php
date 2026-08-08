@props(['icon' => 'add', 'extended' => false, 'href' => null, 'type' => 'button'])

@php
    $classes = 'm3-state inline-flex items-center justify-center gap-3 rounded-2xl bg-md-primary-container text-md-on-primary-container shadow-md font-medium transition select-none '
        .($extended ? 'h-14 px-5' : 'w-14 h-14');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}><x-m3.icon :name="$icon" class="text-2xl" />{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}><x-m3.icon :name="$icon" class="text-2xl" />{{ $slot }}</button>
@endif
