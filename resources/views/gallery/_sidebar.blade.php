@php
    $items = [
        ['label' => __('gallery.heading'), 'url' => route('gallery.index'), 'icon' => 'photo', 'active' => request()->routeIs('gallery.index') && ! request()->boolean('favorites')],
        ['label' => __('gallery.favorites'), 'url' => route('gallery.index', ['favorites' => 1]), 'icon' => 'heart', 'active' => request()->routeIs('gallery.index') && request()->boolean('favorites')],
        ['label' => __('gallery.albums_link'), 'url' => route('gallery.albums'), 'icon' => 'view-columns', 'active' => request()->routeIs('gallery.albums*')],
        ['label' => __('gallery.people_link'), 'url' => route('gallery.people'), 'icon' => 'ellipsis', 'active' => request()->routeIs('gallery.people*')],
        ['label' => __('gallery.trips'), 'url' => route('gallery.trips'), 'icon' => 'map-pin', 'active' => request()->routeIs('gallery.trips')],
        ['label' => __('gallery.map'), 'url' => route('gallery.map'), 'icon' => 'globe', 'active' => request()->routeIs('gallery.map')],
        ['label' => __('gallery.dup_link'), 'url' => route('gallery.duplicates'), 'icon' => 'arrows-pointing-in', 'active' => request()->routeIs('gallery.duplicates')],
        ['label' => __('gallery.trash'), 'url' => route('gallery.trash'), 'icon' => 'trash', 'active' => request()->routeIs('gallery.trash')],
    ];
    $active = collect($items)->firstWhere('active', true);
@endphp

{{-- Mobile: a compact trigger opening the sidebar as a slide-over sheet (the
     rail itself is hidden < md so it never stacks as a full-width block). --}}
<div class="md:hidden">
    <button type="button" @click="$store.nav.toggleSidebar()"
        class="flex min-h-11 w-full items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm">
        <x-icon name="bars-3" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
        <span>{{ $active['label'] ?? __('common.sections') }}</span>
    </button>
</div>

{{-- Desktop rail --}}
<aside class="hidden shrink-0 self-start rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2 shadow-sm md:block md:w-52">
    <nav class="space-y-1 text-sm">
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}"
                @class([
                    'flex min-h-11 items-center gap-2 rounded-md px-3 font-medium',
                    'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' => $item['active'],
                    'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' => ! $item['active'],
                ])>
                <x-icon :name="$item['icon']" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</aside>

{{-- Mobile slide-over --}}
<x-sheet side="left" store="sidebarOpen" :title="__('common.sections')">
    <nav class="space-y-1 text-sm">
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}" @click="$store.nav.closeAll()"
                @class([
                    'flex min-h-11 items-center gap-2 rounded-md px-3 font-medium',
                    'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' => $item['active'],
                    'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' => ! $item['active'],
                ])>
                <x-icon :name="$item['icon']" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</x-sheet>
