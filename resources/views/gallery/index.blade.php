<x-layouts.app :title="__('gallery.title')">
  <div x-data="gallery('{{ route('gallery.store') }}', '{{ csrf_token() }}', '{{ route('gallery.feed') }}', {{ $photos->hasMorePages() ? 'true' : 'false' }}, {{ (int) $mapZoom }})"
       x-init="initGallery()"
       @keydown.left.window="viewerOpen && prev()" @keydown.right.window="viewerOpen && next()">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('gallery.heading') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ __('gallery.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($favoritesOnly)
                <a href="{{ route('gallery.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.all_photos') }}</a>
            @else
                <a href="{{ route('gallery.index', ['favorites' => 1]) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">♥ {{ __('gallery.favorites') }}</a>
            @endif
            <a href="{{ route('gallery.trips') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.trips') }}</a>
            <a href="{{ route('gallery.map') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.map') }}</a>
            <a href="{{ route('gallery.trash') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.trash') }}</a>
            <label class="cursor-pointer rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                {{ __('gallery.upload') }}
                <input type="file" accept="image/*" multiple class="hidden" @change="pick($event)">
            </label>
        </div>
    </div>

    {{-- Upload progress --}}
    <div x-show="queue.length" x-cloak class="mt-4 space-y-2 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <template x-for="(item, i) in queue" :key="i">
            <div>
                <div class="flex justify-between text-xs text-gray-600">
                    <span class="truncate" x-text="item.name"></span>
                    <span x-show="item.error" class="text-red-600">✕</span>
                    <span x-show="item.duplicate" class="text-amber-600">{{ __('gallery.duplicate') }}</span>
                    <span x-show="! item.error && ! item.duplicate" x-text="item.progress + '%'"></span>
                </div>
                <div class="mt-1 h-1.5 w-full rounded bg-gray-100">
                    <div class="h-1.5 rounded" :class="item.error ? 'bg-red-500' : (item.done ? 'bg-green-500' : 'bg-gray-800')" :style="`width: ${item.progress}%`"></div>
                </div>
            </div>
        </template>
    </div>

    {{-- Bulk bar --}}
    <div x-show="selected.length" x-cloak class="mt-4 flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm" x-data="{ deleteOpen: false }">
        <span class="text-sm font-medium text-gray-700"><span x-text="selected.length"></span> {{ __('gallery.selected', ['count' => '']) }}</span>
        <button type="button" @click="deleteOpen = true" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('gallery.delete') }}</button>

        <template x-teleport="body">
            <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteOpen = false">
                <div class="absolute inset-0 bg-gray-900/40" @click="deleteOpen = false"></div>
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('gallery.delete_confirm') }}</p>
                    <form method="POST" action="{{ route('gallery.destroy') }}" class="mt-5 flex justify-end gap-3">
                        @csrf @method('DELETE')
                        <template x-for="id in selected" :key="id"><input type="hidden" name="photo_ids[]" :value="id"></template>
                        <button type="button" @click="deleteOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                        <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('common.delete') }}</button>
                    </form>
                </div>
            </div>
        </template>
    </div>

    {{-- Timeline (infinite scroll appends here) --}}
    <div x-ref="timeline" class="mt-6 space-y-8">
        @include('gallery._timeline', ['grouped' => $grouped])
        @if ($grouped->isEmpty())
            <p class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm">{{ __('gallery.empty') }}</p>
        @endif
    </div>

    {{-- Infinite-scroll sentinel --}}
    <div x-intersect="loadMore()" class="h-10"></div>
    <div x-show="loading" x-cloak class="py-4 text-center text-sm text-gray-400">…</div>

    {{-- Full-screen drop overlay (Google Photos style) --}}
    <div x-show="dragging" x-cloak @drop.prevent="drop($event)" @dragover.prevent
        class="fixed inset-0 z-[900] flex items-center justify-center bg-gray-900/50 p-8">
        <div class="rounded-2xl border-4 border-dashed border-white/80 px-16 py-24 text-center text-lg font-medium text-white">
            {{ __('gallery.drop_hint') }}
        </div>
    </div>

    {{-- Viewer with metadata sidebar + arrow-key navigation --}}
    <template x-teleport="body">
        <div x-show="viewerOpen" x-cloak class="fixed inset-0 z-[1000] flex bg-black/90" @keydown.escape.window="viewerOpen = false">
            <div class="relative flex flex-1 items-center justify-center p-4">
                <button type="button" @click="prev()" x-show="index > 0" class="absolute left-4 text-4xl text-white/70 hover:text-white" aria-label="‹">‹</button>
                {{-- Video loads only on play (preload=none); until then the poster is shown. No autoplay. --}}
                <template x-if="current.mediaType === 'video'">
                    <video :src="current.video" :poster="current.medium" controls preload="none" playsinline class="max-h-[92vh] max-w-full rounded bg-black shadow-2xl"></video>
                </template>
                <template x-if="current.mediaType !== 'video'">
                    <div class="relative">
                        {{-- Motion clip loads only when the badge is pressed; muted loop. --}}
                        <template x-if="current.motion && motionPlaying">
                            <video :src="current.motion" autoplay muted loop playsinline class="max-h-[92vh] max-w-full rounded shadow-2xl"></video>
                        </template>
                        <template x-if="! (current.motion && motionPlaying)">
                            <img :src="current.medium" :alt="current.name" class="max-h-[92vh] max-w-full rounded shadow-2xl">
                        </template>
                        <button type="button" x-show="current.motion" @click="motionPlaying = ! motionPlaying"
                            class="absolute bottom-3 left-3 rounded-full bg-black/60 px-3 py-1 text-xs font-medium text-white hover:bg-black/80">
                            <span x-text="motionPlaying ? '■' : '▶'"></span> {{ __('gallery.motion') }}
                        </button>
                    </div>
                </template>
                <button type="button" @click="next()" x-show="index < list.length - 1" class="absolute right-4 text-4xl text-white/70 hover:text-white" aria-label="›">›</button>
            </div>
            <aside class="hidden w-80 shrink-0 overflow-y-auto bg-white p-6 sm:block">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="text-sm font-semibold text-gray-900 break-all" x-text="current.name"></h2>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" @click="editing = ! editing" :title="'{{ __('gallery.edit') }}'"
                            :class="editing ? 'text-gray-800' : 'text-gray-400 hover:text-gray-600'" class="text-base">✎</button>
                        <form method="POST" :action="`/gallery/${current.id}/favorite`">
                            @csrf
                            <button type="submit" :title="current.favorite === '1' ? '{{ __('gallery.unfavorite') }}' : '{{ __('gallery.favorite') }}'"
                                :class="current.favorite === '1' ? 'text-red-500' : 'text-gray-400 hover:text-red-500'" class="text-xl">
                                <span x-text="current.favorite === '1' ? '♥' : '♡'"></span>
                            </button>
                        </form>
                        <button type="button" @click="viewerOpen = false" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('gallery.close') }}">✕</button>
                    </div>
                </div>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-gray-500">{{ __('gallery.meta_date') }}</dt><dd class="text-gray-900" x-text="`${current.date} · ${current.time}`"></dd></div>
                    <div x-show="current.camera"><dt class="text-gray-500">{{ __('gallery.meta_camera') }}</dt><dd class="text-gray-900" x-text="current.camera"></dd></div>
                    <div x-show="current.dims"><dt class="text-gray-500">{{ __('gallery.meta_dimensions') }}</dt><dd class="text-gray-900" x-text="current.dims"></dd></div>
                    <div x-show="current.durationText"><dt class="text-gray-500">{{ __('gallery.meta_duration') }}</dt><dd class="text-gray-900" x-text="current.durationText"></dd></div>
                    <div x-show="current.tech"><dt class="text-gray-500">{{ __('gallery.meta_tech') }}</dt><dd class="text-gray-900" x-text="current.tech"></dd></div>
                    <div><dt class="text-gray-500">{{ __('gallery.meta_size') }}</dt><dd class="text-gray-900" x-text="current.size"></dd></div>
                    <div x-show="current.lat && current.lng">
                        <dt class="text-gray-500">{{ __('gallery.meta_location') }}</dt>
                        <dd class="text-gray-900">
                            <span x-show="current.place" x-text="current.place" class="block"></span>
                            <span x-text="`${(+current.lat).toFixed(5)}, ${(+current.lng).toFixed(5)}`" class="text-gray-500"></span>
                            <a :href="`https://www.openstreetmap.org/?mlat=${current.lat}&mlon=${current.lng}#map=14/${current.lat}/${current.lng}`" target="_blank" rel="noopener" class="ml-1 text-gray-500 underline">{{ __('gallery.map') }} ↗</a>
                            <div x-ref="miniMap" x-show="current.lat && current.lng" class="mt-2 h-40 w-full overflow-hidden rounded-md border border-gray-200"></div>
                        </dd>
                    </div>
                </dl>
                {{-- Editing tools, hidden until the edit button is pressed. --}}
                <div x-show="editing" x-cloak>
                {{-- Transform (rotate / flip) — images only; regenerates renditions from the original --}}
                <div class="mt-6 flex gap-2" x-show="current.mediaType !== 'video'">
                    <form method="POST" :action="`/gallery/${current.id}/transform`" class="flex-1">
                        @csrf
                        <input type="hidden" name="action" value="rotate_left">
                        <button type="submit" class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('gallery.rotate_left') }}">⤺</button>
                    </form>
                    <form method="POST" :action="`/gallery/${current.id}/transform`" class="flex-1">
                        @csrf
                        <input type="hidden" name="action" value="rotate_right">
                        <button type="submit" class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('gallery.rotate_right') }}">⤻</button>
                    </form>
                    <form method="POST" :action="`/gallery/${current.id}/transform`" class="flex-1">
                        @csrf
                        <input type="hidden" name="action" value="flip">
                        <button type="submit" class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('gallery.flip') }}">⇋</button>
                    </form>
                </div>

                {{-- Edit name / date / time / location --}}
                <form method="POST" :action="`/gallery/${current.id}/meta`" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                    @csrf @method('PUT')
                    <input type="text" name="name" x-model="current.name" placeholder="{{ __('gallery.meta_name') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="date" :value="current.dateiso" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="time" name="time" :value="current.time" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="any" name="latitude" x-model="current.lat" placeholder="lat" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="number" step="any" name="longitude" x-model="current.lng" placeholder="lng" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <button type="submit" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.save_meta') }}</button>
                </form>
                </div>

                <a :href="current.original" class="mt-4 block rounded-md bg-gray-800 px-4 py-2 text-center text-sm font-medium text-white hover:bg-gray-700">{{ __('gallery.download') }}</a>
            </aside>
        </div>
    </template>
  </div>
</x-layouts.app>
