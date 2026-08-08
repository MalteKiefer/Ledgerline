@props(['top' => null, 'bottom' => null])

{{-- Module sidebar: optional primary action (top slot), nav items (default slot),
     optional footer (bottom slot, e.g. storage quota). Same across every module;
     only the nav content differs. --}}
<aside {{ $attributes->class('flex flex-col w-56 shrink-0 bg-md-surface-2 border-r border-md-outline-variant p-3 overflow-y-auto') }}>
    @isset($top)
        <div class="mb-3">{{ $top }}</div>
    @endisset
    <nav class="flex flex-1 flex-col gap-0.5">{{ $slot }}</nav>
    @isset($bottom)
        <div class="mt-3">{{ $bottom }}</div>
    @endisset
</aside>
