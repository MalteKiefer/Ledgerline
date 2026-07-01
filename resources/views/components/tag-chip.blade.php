@props(['tag', 'href' => null])

@php
    $dot = $tag->color ?: '#9CA3AF';
    $classes = 'inline-flex items-center gap-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes.' hover:bg-gray-200']) }}>
        <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $dot }}"></span>{{ $tag->name }}
    </a>
@else
    <span {{ $attributes->merge(['class' => $classes]) }}>
        <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $dot }}"></span>{{ $tag->name }}
    </span>
@endif
