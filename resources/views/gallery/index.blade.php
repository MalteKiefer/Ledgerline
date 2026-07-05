<x-layouts.app :title="__('gallery.title')">
  <div x-data="gallery('{{ route('gallery.store') }}', '{{ csrf_token() }}', '{{ route('gallery.feed', array_filter(['q' => $searchQuery ?: null, 'favorites' => $favoritesOnly ? 1 : null])) }}', {{ $photos->hasMorePages() ? 'true' : 'false' }}, {{ (int) $mapZoom }}, '{{ route('gallery.months') }}', '{{ route('gallery.geocode.reverse') }}')"
       x-init="initGallery()"
       @keydown.left.window="viewerOpen && prev()" @keydown.right.window="viewerOpen && next()"
       @keydown.window="onKeydown($event)">

    <x-page-heading :title="__('gallery.heading')" :subtitle="__('gallery.subtitle')">
        <x-slot:actions>
            <form method="GET" action="{{ route('gallery.index') }}" class="flex w-full items-center gap-1 sm:w-auto">
                <input type="search" name="q" value="{{ $searchQuery }}" placeholder="{{ __('gallery.search_placeholder') }}"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:w-56">
                <x-button type="submit" title="{{ __('gallery.search') }}" aria-label="{{ __('gallery.search') }}" class="!p-2"><x-icon name="magnifying-glass" class="h-5 w-5" /></x-button>
                @if ($searchQuery !== '')
                    <a href="{{ route('gallery.index') }}" class="px-1 text-sm text-gray-500 hover:text-gray-900" title="{{ __('gallery.search_clear') }}"><x-icon name="x-mark" /></a>
                @endif
            </form>
            @if ($favoritesOnly)
                <x-button :href="route('gallery.index')">{{ __('gallery.all_photos') }}</x-button>
            @else
                <x-button :href="route('gallery.index', ['favorites' => 1])">{{ __('gallery.favorites') }}</x-button>
            @endif
            <x-button :href="route('gallery.trips')">{{ __('gallery.trips') }}</x-button>
            <x-button :href="route('gallery.map')">{{ __('gallery.map') }}</x-button>
            <x-button :href="route('gallery.people')">{{ __('gallery.people_link') }}</x-button>
            <x-button :href="route('gallery.duplicates')">{{ __('gallery.dup_link') }}</x-button>
            <x-button :href="route('gallery.trash')">{{ __('gallery.trash') }}</x-button>
            <label title="{{ __('gallery.upload') }}" aria-label="{{ __('gallery.upload') }}" class="hidden cursor-pointer rounded-md bg-gray-900 p-2 text-white hover:bg-gray-800 sm:inline-flex">
                <x-icon name="arrow-up-tray" class="h-5 w-5" />
                <input type="file" accept="image/*,video/*" multiple class="hidden" @change="pick($event)">
            </label>
        </x-slot:actions>
    </x-page-heading>

    {{-- Floating upload button on mobile (hidden while selecting, to clear the bulk bar). --}}
    <label x-show="! selected.length" class="fixed bottom-6 right-5 z-30 flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-gray-900 text-white shadow-lg hover:bg-gray-800 sm:hidden" aria-label="{{ __('gallery.upload') }}" title="{{ __('gallery.upload') }}">
        <x-icon name="arrow-up-tray" class="h-6 w-6" />
        <input type="file" accept="image/*,video/*" multiple class="hidden" @change="pick($event)">
    </label>

    {{-- Upload tray (Google/Immich style): per-file thumbnail, progress and state --}}
    <div x-show="queue.length" x-cloak class="mt-4 rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2">
            <span class="text-sm font-medium text-gray-700">
                <template x-if="! summary"><span>{{ __('gallery.uploading') }} (<span x-text="queue.filter(i => ['done','duplicate','skipped','error'].includes(i.state)).length"></span>/<span x-text="queue.length"></span>)</span></template>
                <template x-if="summary">
                    <span>{{ __('gallery.upload_done') }} —
                        <span x-text="summary.created"></span> {{ __('gallery.uploaded_count') }}<span x-show="summary.duplicates.length">, <span x-text="summary.duplicates.length"></span> {{ __('gallery.duplicate_count') }}</span><span x-show="summary.skipped.length">, <span x-text="summary.skipped.length"></span> {{ __('gallery.skipped_count') }}</span><span x-show="summary.errored.length">, <span x-text="summary.errored.length"></span> {{ __('gallery.failed_count') }}</span>
                    </span>
                </template>
            </span>
            <button type="button" x-show="summary" @click="dismissUploads()" class="text-sm font-medium text-gray-600 hover:text-gray-900">{{ __('gallery.upload_dismiss') }}</button>
        </div>
        <div class="max-h-64 space-y-2 overflow-y-auto p-4">
            <template x-for="(item, i) in queue" :key="i">
                <div class="flex items-center gap-3">
                    <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded bg-gray-100">
                        <template x-if="item.preview"><img :src="item.preview" class="h-full w-full object-cover"></template>
                        <template x-if="! item.preview"><span class="flex h-full w-full items-center justify-center text-gray-400" x-text="item.isVideo ? '🎬' : '🖼️'"></span></template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex justify-between gap-2 text-xs">
                            <span class="truncate text-gray-700" x-text="item.name"></span>
                            <span class="shrink-0" :class="{'text-green-600': item.state==='done', 'text-amber-600': item.state==='duplicate'||item.state==='skipped', 'text-red-600': item.state==='error', 'text-gray-500': item.state==='uploading'||item.state==='pending'}"
                                x-text="{
                                    pending: '…', uploading: item.progress + '%', done: '✓',
                                    duplicate: '{{ __('gallery.duplicate') }}',
                                    skipped: item.reason === 'unsupported' ? '{{ __('gallery.skipped_unsupported') }}' : '{{ __('gallery.skipped_generic') }}',
                                    error: '✕',
                                }[item.state]"></span>
                        </div>
                        <div class="mt-1 h-1.5 w-full rounded bg-gray-100">
                            <div class="h-1.5 rounded transition-all"
                                :class="{'bg-green-500': item.state==='done', 'bg-amber-500': item.state==='duplicate'||item.state==='skipped', 'bg-red-500': item.state==='error', 'bg-gray-800': item.state==='uploading'||item.state==='pending'}"
                                :style="`width: ${item.state==='pending' ? 0 : (item.state==='uploading' ? item.progress : 100)}%`"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div x-show="summary && summary.duplicates.length" x-cloak class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500">
            {{ __('gallery.duplicate_list') }}: <span x-text="summary && summary.duplicates.join(', ')"></span>
        </div>
        <div x-show="summary && summary.skipped.length" x-cloak class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500">
            {{ __('gallery.skipped_list') }}: <span x-text="summary && summary.skipped.join(', ')"></span>
        </div>
    </div>

    {{-- Bulk bar --}}
    <div x-show="selected.length" x-cloak x-transition
        class="fixed inset-x-0 bottom-5 z-40 mx-auto flex w-max max-w-[95vw] flex-wrap items-center justify-center gap-3 rounded-full border border-gray-200 bg-white px-4 py-2 shadow-xl"
        x-data="{ deleteOpen: false, locationOpen: false, lat: '', lng: '' }"
        @location-picked.window="if ($event.detail.context === 'bulk') { lat = $event.detail.lat; lng = $event.detail.lng; }">
        <span class="text-sm font-medium text-gray-700"><span x-text="selected.length"></span> {{ __('gallery.selected', ['count' => '']) }}</span>
        <div class="flex items-center gap-2">
            <button type="button" @click="queueExport('original', '{{ __('downloads.queued_toast') }}')" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"><x-icon name="arrow-down-tray" />{{ __('gallery.download_original') }}</button>
            <button type="button" @click="queueExport('edited', '{{ __('downloads.queued_toast') }}')" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"><x-icon name="arrow-down-tray" />{{ __('gallery.download_edited') }}</button>
        </div>
        <button type="button" @click="locationOpen = true" title="{{ __('gallery.set_location') }}" aria-label="{{ __('gallery.set_location') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="map-pin" class="h-5 w-5" /></button>
        <button type="button" @click="deleteOpen = true" title="{{ __('gallery.delete') }}" aria-label="{{ __('gallery.delete') }}" class="rounded-md border border-red-300 p-2 text-red-700 hover:bg-red-50"><x-icon name="trash" class="h-5 w-5" /></button>

        <template x-teleport="body">
            <div x-show="locationOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="locationOpen = false">
                <div class="absolute inset-0 bg-gray-900/40" @click="locationOpen = false"></div>
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('gallery.set_location') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('gallery.set_location_hint') }}</p>
                    <form method="POST" action="{{ route('gallery.location') }}" @submit.prevent="bulkLocation($event).then(ok => { if (ok) locationOpen = false })" class="mt-4">
                        @csrf
                        <template x-for="id in selected" :key="id"><input type="hidden" name="photo_ids[]" :value="id"></template>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="any" name="latitude" x-model="lat" placeholder="lat" required class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            <input type="number" step="any" name="longitude" x-model="lng" placeholder="lng" required class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </div>
                        <button type="button" @click="window.dispatchEvent(new CustomEvent('open-location-picker', { detail: { context: 'bulk', lat, lng } }))"
                            class="mt-2 w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><span class="inline-flex items-center justify-center gap-1.5"><x-icon name="map-pin" />{{ __('gallery.change_location') }}</span></button>
                        <div class="mt-5 flex justify-end gap-3">
                            <button type="button" @click="locationOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('gallery.save_meta') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <template x-teleport="body">
            <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteOpen = false">
                <div class="absolute inset-0 bg-gray-900/40" @click="deleteOpen = false"></div>
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('gallery.delete_confirm') }}</p>
                    <form method="POST" action="{{ route('gallery.destroy') }}" @submit.prevent="bulkDelete($event).then(() => deleteOpen = false)" class="mt-5 flex justify-end gap-3">
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
            <p class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm">{{ $searchQuery !== '' ? __('gallery.search_empty', ['q' => $searchQuery]) : __('gallery.empty') }}</p>
        @endif
    </div>

    {{-- Infinite-scroll sentinel --}}
    <div x-ref="sentinel" x-intersect.margin.800px="loadMore()" class="h-10"></div>
    <div x-show="loading" x-cloak class="py-4 text-center text-sm text-gray-400">…</div>

    {{-- Year/month scrubber: year headers with three-letter months below --}}
    <div x-show="months.length" x-cloak
        class="fixed right-1 top-1/2 z-20 hidden max-h-[70vh] w-14 -translate-y-1/2 flex-col overflow-y-auto rounded-md bg-white/70 py-2 text-right backdrop-blur md:flex">
        <template x-for="(m, i) in months" :key="m.ym">
            <div>
                <div x-show="i === 0 || m.year !== months[i - 1].year"
                    class="px-2 pt-2 text-[11px] font-bold uppercase tracking-wide text-gray-800" x-text="m.year"></div>
                <button type="button" @click="scrollToMonth(m.ym)"
                    class="block w-full px-2 py-0.5 text-[11px] leading-tight text-gray-500 hover:font-semibold hover:text-gray-900"
                    x-text="m.month"></button>
            </div>
        </template>
    </div>

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
                {{-- Floating controls for mobile, where the sidebar is a bottom sheet.
                     Large, tappable circular buttons. --}}
                <div class="absolute right-3 top-3 z-10 flex items-center gap-3 sm:hidden">
                    <form method="POST" :action="`/gallery/${current.id}/favorite`" @submit.prevent="favoriteCurrent($event)">
                        @csrf
                        <button type="submit" aria-label="{{ __('gallery.favorite') }}"
                            :class="current.favorite === '1' ? 'text-red-500' : 'text-white'"
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-black/50 text-2xl backdrop-blur">
                            <x-icon name="heart-solid" class="h-5 w-5" x-show="current.favorite === '1'" />
                            <x-icon name="heart" class="h-5 w-5" x-show="current.favorite !== '1'" />
                        </button>
                    </form>
                    <button type="button" @click="showDetails = ! showDetails; editing = showDetails" aria-label="{{ __('gallery.meta_tech') }}"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur"><x-icon name="info" class="h-5 w-5" /></button>
                    <button type="button" @click="viewerOpen = false" aria-label="{{ __('gallery.close') }}"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>
                <button type="button" @click="prev()" x-show="index > 0" class="absolute left-4 text-white/70 hover:text-white" aria-label="{{ __('gallery.close') }}"><x-icon name="chevron-left" class="h-9 w-9" /></button>
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
                            <span class="inline-flex items-center gap-1"><x-icon name="stop" class="h-3.5 w-3.5" x-show="motionPlaying" /><x-icon name="play" class="h-3.5 w-3.5" x-show="! motionPlaying" />{{ __('gallery.motion') }}</span>
                        </button>
                    </div>
                </template>
                <button type="button" @click="next()" x-show="index < list.length - 1" class="absolute right-4 text-white/70 hover:text-white" aria-label="{{ __('gallery.close') }}"><x-icon name="chevron-right" class="h-9 w-9" /></button>
            </div>
            <aside :class="showDetails ? 'block' : 'hidden sm:block'"
                class="fixed inset-x-0 bottom-0 z-[1010] max-h-[80vh] overflow-y-auto rounded-t-2xl bg-white p-6 shadow-2xl sm:static sm:z-auto sm:max-h-none sm:w-80 sm:shrink-0 sm:rounded-none sm:shadow-none">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="text-sm font-semibold text-gray-900 break-all" x-text="current.name"></h2>
                    <div class="hidden shrink-0 items-center gap-2 sm:flex">
                        <button type="button" @click="editing = ! editing" :title="'{{ __('gallery.edit') }}'"
                            :class="editing ? 'text-gray-800' : 'text-gray-400 hover:text-gray-600'"><x-icon name="pencil" class="h-[18px] w-[18px]" /></button>
                        <form method="POST" :action="`/gallery/${current.id}/favorite`" @submit.prevent="favoriteCurrent($event)">
                            @csrf
                            <button type="submit" :title="current.favorite === '1' ? '{{ __('gallery.unfavorite') }}' : '{{ __('gallery.favorite') }}'"
                                :class="current.favorite === '1' ? 'text-red-500' : 'text-gray-400 hover:text-red-500'" class="text-xl">
                                <x-icon name="heart-solid" class="h-5 w-5" x-show="current.favorite === '1'" />
                                <x-icon name="heart" class="h-5 w-5" x-show="current.favorite !== '1'" />
                            </button>
                        </form>
                        <button type="button" @click="viewerOpen = false" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('gallery.close') }}"><x-icon name="x-mark" class="h-5 w-5" /></button>
                    </div>
                </div>
                @php
                    $cardRow = 'flex justify-between gap-3 py-1 text-sm';
                    $cardLabel = 'shrink-0 text-gray-500';
                    $cardValue = 'text-right text-gray-900';
                @endphp

                {{-- Capture details --}}
                <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('gallery.card_capture') }}</h3>
                    <dl class="mt-2 divide-y divide-gray-100">
                        <div class="{{ $cardRow }}"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_date') }}</dt><dd class="{{ $cardValue }}" x-text="`${current.date} · ${current.time}`"></dd></div>
                        <div class="{{ $cardRow }}" x-show="current.camera"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_camera') }}</dt><dd class="{{ $cardValue }}" x-text="current.camera"></dd></div>
                        <div class="{{ $cardRow }}" x-show="current.focal"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_focal') }}</dt><dd class="{{ $cardValue }}" x-text="current.focal"></dd></div>
                        <div class="{{ $cardRow }}" x-show="current.aperture"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_aperture') }}</dt><dd class="{{ $cardValue }}" x-text="current.aperture"></dd></div>
                        <div class="{{ $cardRow }}" x-show="current.shutter"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_shutter') }}</dt><dd class="{{ $cardValue }}" x-text="current.shutter"></dd></div>
                        <div class="{{ $cardRow }}" x-show="current.iso"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_iso') }}</dt><dd class="{{ $cardValue }}" x-text="current.iso"></dd></div>
                    </dl>
                </div>

                {{-- Technical details --}}
                <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('gallery.card_technical') }}</h3>
                    <dl class="mt-2 divide-y divide-gray-100">
                        <div class="{{ $cardRow }}" x-show="current.dims"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_dimensions') }}</dt><dd class="{{ $cardValue }}" x-text="current.dims"></dd></div>
                        <div class="{{ $cardRow }}" x-show="current.durationText"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_duration') }}</dt><dd class="{{ $cardValue }}" x-text="current.durationText"></dd></div>
                        <div class="{{ $cardRow }}" x-show="current.fps"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_fps') }}</dt><dd class="{{ $cardValue }}" x-text="current.fps"></dd></div>
                        <div class="{{ $cardRow }}" x-show="current.codec"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_codec') }}</dt><dd class="{{ $cardValue }}" x-text="current.codec"></dd></div>
                        <div class="{{ $cardRow }}"><dt class="{{ $cardLabel }}">{{ __('gallery.meta_size') }}</dt><dd class="{{ $cardValue }}" x-text="current.size"></dd></div>
                    </dl>
                </div>

                {{-- Location --}}
                <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="current.lat && current.lng">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('gallery.meta_location') }}</h3>
                    <div class="mt-2 text-sm text-gray-900">
                        <template x-if="current.placeLines">
                            <div>
                                <template x-for="(line, i) in current.placeLines.split('|')" :key="i">
                                    <span class="block" :class="i === 0 ? 'font-medium' : 'text-gray-600'" x-text="line"></span>
                                </template>
                            </div>
                        </template>
                        <div class="mt-1 text-gray-500">
                            <span x-text="current.lat && current.lng ? `${(+current.lat).toFixed(5)}, ${(+current.lng).toFixed(5)}` : ''"></span>
                            <a :href="`https://www.openstreetmap.org/?mlat=${current.lat}&mlon=${current.lng}#map=14/${current.lat}/${current.lng}`" target="_blank" rel="noopener" class="ml-1 underline">{{ __('gallery.map') }} ↗</a>
                        </div>
                    </div>
                    <div x-ref="miniMap" class="mt-2 h-40 w-full overflow-hidden rounded-md border border-gray-200"></div>
                </div>
                {{-- Editing tools, hidden until the edit button is pressed. --}}
                <div x-show="editing" x-cloak>
                {{-- Transform (rotate / flip) — images only; regenerates renditions from the original --}}
                <div class="mt-6 flex gap-2" x-show="current.mediaType !== 'video'">
                    <form method="POST" :action="`/gallery/${current.id}/transform`" class="flex-1">
                        @csrf
                        <input type="hidden" name="action" value="rotate_left">
                        <button type="submit" class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('gallery.rotate_left') }}"><x-icon name="arrow-uturn-left" class="mx-auto" /></button>
                    </form>
                    <form method="POST" :action="`/gallery/${current.id}/transform`" class="flex-1">
                        @csrf
                        <input type="hidden" name="action" value="rotate_right">
                        <button type="submit" class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('gallery.rotate_right') }}"><x-icon name="arrow-uturn-right" class="mx-auto" /></button>
                    </form>
                    <form method="POST" :action="`/gallery/${current.id}/transform`" class="flex-1">
                        @csrf
                        <input type="hidden" name="action" value="flip">
                        <button type="submit" class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('gallery.flip') }}"><x-icon name="arrows-right-left" class="mx-auto" /></button>
                    </form>
                </div>

                {{-- Edit name / date / time / location --}}
                <form method="POST" :action="`/gallery/${current.id}/meta`" @submit.prevent="saveMeta($event)" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                    @csrf @method('PUT')
                    <input type="text" name="name" x-model="current.name" placeholder="{{ __('gallery.meta_name') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="text" name="camera" x-model="current.camera" list="known-cameras" placeholder="{{ __('gallery.meta_camera') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <datalist id="known-cameras">
                        @foreach ($cameras as $camera)
                            <option value="{{ $camera }}"></option>
                        @endforeach
                    </datalist>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="date" :value="current.dateiso" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="time" name="time" :value="current.time" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="any" name="latitude" x-model="current.lat" placeholder="lat" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="number" step="any" name="longitude" x-model="current.lng" placeholder="lng" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <button type="button" @click="window.dispatchEvent(new CustomEvent('open-location-picker', { detail: { context: 'single', lat: current.lat, lng: current.lng } }))"
                        class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><span class="inline-flex items-center justify-center gap-1.5"><x-icon name="map-pin" />{{ __('gallery.change_location') }}</span></button>
                    <button type="submit" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.save_meta') }}</button>
                </form>
                </div>

                <div class="mt-4">
                    <p class="mb-1 text-xs font-medium text-gray-500">{{ __('gallery.download') }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        <a :href="current.original" class="rounded-md bg-gray-800 px-4 py-2 text-center text-sm font-medium text-white hover:bg-gray-700">{{ __('gallery.download_original') }}</a>
                        <a :href="`/gallery/${current.id}/download/edited`" class="rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.download_edited') }}</a>
                    </div>
                </div>
            </aside>
        </div>
    </template>

    @include('gallery._location_picker')

    {{-- Selection cap warning --}}
    <div x-show="capNotice" x-cloak x-transition
        class="fixed bottom-5 left-1/2 z-[1000] -translate-x-1/2 rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-lg">
        {{ __('gallery.selection_capped', ['max' => 1000]) }}
    </div>
  </div>
</x-layouts.app>
