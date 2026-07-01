@props([
    'placeholder' => 'Search…',
])

@php
    // Keep the other active filters (sort, dir, type, tag, …) when searching.
    $hidden = collect(request()->query())->except(['q', 'page']);
@endphp

<form method="GET" {{ $attributes->merge(['class' => 'w-full max-w-xs']) }}>
    @foreach ($hidden as $key => $value)
        @if (! is_array($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
    <label for="table-search" class="sr-only">Search</label>
    <input type="search" id="table-search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}"
        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
</form>
