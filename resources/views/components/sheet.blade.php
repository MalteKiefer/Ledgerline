@props(['side' => 'left', 'store' => 'sidebarOpen', 'title' => null])

{{-- Generic off-canvas sheet driven by $store.nav.<store>. Used by the mobile
     "More" menu (side=bottom) and the per-module sidebars (side=left). Teleported
     to <body> so it escapes any positioned/overflow-hidden ancestor. --}}
@php
    $panel = match ($side) {
        'bottom' => 'inset-x-0 bottom-0 max-h-[85vh] rounded-t-2xl pb-[calc(env(safe-area-inset-bottom)+1rem)]',
        'right' => 'inset-y-0 right-0 h-full w-80 max-w-[85vw]',
        default => 'inset-y-0 left-0 h-full w-80 max-w-[85vw]',
    };
    $enter = $side === 'bottom' ? 'translate-y-full' : ($side === 'right' ? 'translate-x-full' : '-translate-x-full');
@endphp
<template x-teleport="body">
    <div x-show="$store.nav.{{ $store }}" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true"
        @keydown.escape.window="$store.nav.{{ $store }} = false">
        <div x-show="$store.nav.{{ $store }}" x-transition.opacity
            class="absolute inset-0 bg-gray-900/40" @click="$store.nav.{{ $store }} = false"></div>
        <div x-show="$store.nav.{{ $store }}"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="{{ $enter }}" x-transition:enter-end="translate-x-0 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0 translate-y-0" x-transition:leave-end="{{ $enter }}"
            class="absolute {{ $panel }} overflow-y-auto bg-white p-4 shadow-xl dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title ?? '' }}</h2>
                <button type="button" @click="$store.nav.{{ $store }} = false"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800"
                    aria-label="{{ __('common.cancel') }}"><x-icon name="x-mark" class="h-5 w-5" /></button>
            </div>
            {{ $slot }}
        </div>
    </div>
</template>
