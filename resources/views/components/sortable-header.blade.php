@props([
    'column',
    'label',
    'sort',
    'dir',
])

@php
    $isActive = $sort === $column;
    $nextDir = ($isActive && $dir === 'asc') ? 'desc' : 'asc';
    // Preserve current filters; reset pagination when the sort changes.
    $params = array_merge(request()->query(), ['sort' => $column, 'dir' => $nextDir]);
    unset($params['page']);
    $href = url()->current().'?'.http_build_query($params);
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-1 hover:text-gray-700']) }}>
    {{ $label }}
    <span aria-hidden="true" class="text-gray-400">
        @if ($isActive)
            {{ $dir === 'asc' ? '↑' : '↓' }}
        @else
            <span class="opacity-0 group-hover:opacity-100">↕</span>
        @endif
    </span>
</a>
