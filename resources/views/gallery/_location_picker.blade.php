{{-- Shared interactive location picker: pin-drop on an OSM map or address search.
     Opened via the `open-location-picker` window event; emits `location-picked`. --}}
<div x-data="locationPicker('{{ route('gallery.geocode.search') }}')" x-init="initPicker()">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[1200] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-gray-900/50" @click="close()"></div>
            <div class="relative w-full max-w-2xl rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.pick_location') }}</h3>

                <div class="relative z-[1000] mt-3">
                    <input type="search" x-model="query" @input="queueSearch()" @keydown.enter.prevent="runSearch()"
                        placeholder="{{ __('gallery.search_address') }}"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <div x-show="results.length" x-cloak class="absolute z-[1000] mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-lg">
                        <template x-for="(res, i) in results" :key="i">
                            <button type="button" @click="choose(res)" class="block w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800" x-text="res.display"></button>
                        </template>
                    </div>
                </div>

                <div x-ref="pickerMap" class="mt-3 h-80 w-full overflow-hidden rounded-md border border-gray-200 dark:border-gray-800"></div>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('gallery.pick_location_hint') }}
                    <span x-show="lat != null" class="ml-1 font-mono" x-text="lat != null ? `${(+lat).toFixed(5)}, ${(+lng).toFixed(5)}` : ''"></span>
                </p>

                <div class="mt-4 flex justify-end gap-3">
                    <button type="button" @click="close()" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                    <button type="button" @click="apply()" :disabled="lat == null" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('gallery.use_location') }}</button>
                </div>
            </div>
        </div>
    </template>
</div>
