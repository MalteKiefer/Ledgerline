@props([
    'title',
    'subtitle' => null,
])

{{-- Consistent page header: title (+ optional subtitle) on the left, an actions
     slot on the right. Used across Todos, Contacts, Calendar and Gallery. --}}
<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-md-on-surface dark:text-md-on-surface">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
