@props([
    'icon' => null,
    'label' => '',
    'count' => null,
    'active' => false,
    'href' => null,
    'dot' => null,
])

@php
    $classes = 'm3-state flex items-center gap-3 rounded-lg px-3 h-11 text-sm cursor-pointer transition '
        .($active ? 'bg-md-selected text-md-primary font-semibold' : 'text-md-on-surface-var');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($dot)<span class="h-2.5 w-2.5 rounded-full shrink-0" style="background: {{ $dot }}"></span>
        @elseif ($icon)<x-m3.icon :name="$icon" class="text-xl shrink-0" />@endif
        <span class="truncate">{{ $label }}{{ $slot }}</span>
        @if ($count !== null)<span class="ml-auto text-xs">{{ $count }}</span>@endif
    </a>
@else
    <div {{ $attributes->class($classes) }}>
        @if ($dot)<span class="h-2.5 w-2.5 rounded-full shrink-0" style="background: {{ $dot }}"></span>
        @elseif ($icon)<x-m3.icon :name="$icon" class="text-xl shrink-0" />@endif
        <span class="truncate">{{ $label }}{{ $slot }}</span>
        @if ($count !== null)<span class="ml-auto text-xs">{{ $count }}</span>@endif
    </div>
@endif
