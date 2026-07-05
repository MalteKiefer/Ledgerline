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
@endphp
<aside class="w-full shrink-0 md:w-52">
    <nav class="space-y-1 text-sm">
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}"
                @class([
                    'flex items-center gap-2 rounded-md px-3 py-2 font-medium',
                    'bg-gray-100 text-gray-900' => $item['active'],
                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => ! $item['active'],
                ])>
                <x-icon :name="$item['icon']" class="h-4 w-4 text-gray-400" />
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</aside>
