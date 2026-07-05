{{--
    Spotlight-style global search.

    The header shows only a magnifier button. Clicking it (or pressing ⌘K / Ctrl+K)
    opens a centred modal that searches live as you type via the JSON suggest
    endpoint. Escape closes; arrow keys move; Enter follows the active result.
--}}
<div x-data="spotlight"
    @keydown.window.meta.k.prevent="openPalette()"
    @keydown.window.ctrl.k.prevent="openPalette()">

    <button type="button" @click="openPalette()" aria-label="{{ __('pages.spotlight.search_button_label') }}" title="{{ __('pages.spotlight.search_button_label') }}"
        class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="m21 21-4.35-4.35M16.5 10.5a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z" />
        </svg>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true"
            @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-gray-900/40" @click="close()"></div>

            <div class="relative mx-auto mt-[15vh] w-full max-w-xl px-4">
                <div class="overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5">
                    <div class="flex items-center gap-2 border-b border-gray-100 px-4">
                        <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m21 21-4.35-4.35M16.5 10.5a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z" />
                        </svg>
                        <input x-ref="input" type="text" x-model="query"
                            @input.debounce.200ms="runSearch()"
                            @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
                            @keydown.enter.prevent="go()"
                            placeholder="{{ __('pages.spotlight.placeholder') }}"
                            class="w-full border-0 bg-transparent py-3.5 text-sm focus:ring-0"
                            autocomplete="off" spellcheck="false">
                        <span x-show="loading" x-cloak class="text-xs text-gray-400">…</span>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto">
                        <p x-show="query.trim() !== '' && flat.length === 0 && !loading" x-cloak
                            class="px-4 py-6 text-center text-sm text-gray-500">{{ __('pages.spotlight.no_results') }}</p>

                        <template x-for="group in groups" :key="group.group">
                            <div class="py-1">
                                <p class="px-4 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400"
                                    x-text="group.group + ' (' + group.results.length + ')'"></p>
                                <ul>
                                    <template x-for="item in group.results" :key="item.url">
                                        <li>
                                            <a :href="item.url"
                                                class="block min-w-0 px-4 py-3"
                                                :class="isActive(item) ? 'bg-gray-100' : 'hover:bg-gray-50'">
                                                <span class="block truncate text-sm font-medium text-gray-900" x-text="item.title"></span>
                                                <span x-show="item.subtitle" class="block truncate text-xs text-gray-500"
                                                    x-text="item.subtitle"></span>
                                            </a>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        <a x-show="query.trim() !== '' && flat.length > 0" x-cloak href="#" @click.prevent="seeAll()"
                            class="block border-t border-gray-100 px-4 py-3 text-center text-xs text-gray-500 hover:bg-gray-50">
                            {{ __('pages.spotlight.see_all_results') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
