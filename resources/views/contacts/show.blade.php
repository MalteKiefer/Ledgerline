<x-layouts.app :title="__('contacts.ui.heading')">
    @php $cfg = [
        'contactBase' => url('contacts'),
        'indexUrl' => route('contacts.index'),
        'dataUrl' => route('contacts.data'),
        'token' => csrf_token(),
        'contactId' => $contactId,
        'labels' => [
            'home' => __('contacts.ui.label_home'),
            'work' => __('contacts.ui.label_work'),
            'cell' => __('contacts.ui.label_mobile'),
            'other' => __('contacts.ui.label_other'),
        ],
        'relatedTypes' => collect(['spouse','child','parent','sibling','friend','colleague','assistant','manager','other'])
            ->mapWithKeys(fn ($t) => [$t => __('contacts.ui.related_type_'.$t)])->all(),
    ]; @endphp
    <div x-data="contactViewPage(@js($cfg))" x-init="init()" class="mx-auto max-w-xl">
        <x-page-heading :title="__('contacts.ui.heading')">
            <x-slot:actions>
                <x-button icon="chevron-left" href="{{ route('contacts.index') }}">{{ __('contacts.ui.back') }}</x-button>
                <x-button variant="primary" icon="pencil" href="{{ route('contacts.edit', $contactId) }}">{{ __('contacts.ui.edit') }}</x-button>
            </x-slot:actions>
        </x-page-heading>

        <div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            {{-- Header: avatar, name, org --}}
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 text-lg font-medium text-gray-500 dark:text-gray-400 ring-1 ring-gray-200 dark:ring-gray-700">
                    <template x-if="c.photo"><img :src="c.photo" alt="" class="h-full w-full object-cover"></template>
                    <template x-if="! c.photo && initials()"><span x-text="initials()"></span></template>
                    <template x-if="! c.photo && ! initials()"><x-icon name="user" class="h-6 w-6 text-gray-400 dark:text-gray-500" /></template>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        <span class="truncate" x-text="displayName()"></span>
                        <x-icon x-show="c.favorite" x-cloak name="star-solid" class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" />
                    </p>
                    <p class="truncate text-sm text-gray-500 dark:text-gray-400" x-text="[c.org, c.title].filter(Boolean).join(' · ')"></p>
                    <a x-show="c.person" x-cloak :href="c.person?.url"
                        class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-white">
                        <x-icon name="photo" class="h-3.5 w-3.5" />{{ __('contacts.ui.show_photos') }}
                    </a>
                </div>
            </div>

            <dl class="mt-5 space-y-4 border-t border-gray-100 dark:border-gray-800 pt-4 text-sm">
                {{-- Phones --}}
                <template x-if="(c.phones || []).length">
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('contacts.ui.phone') }}</dt>
                        <template x-for="(p,i) in c.phones" :key="'p'+i">
                            <dd class="mt-1 flex items-baseline justify-between gap-3">
                                <a :href="'tel:'+p.value" class="text-gray-900 dark:text-gray-100 hover:underline" x-text="p.value"></a>
                                <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500" x-text="label(p.type)"></span>
                            </dd>
                        </template>
                    </div>
                </template>
                {{-- Emails --}}
                <template x-if="(c.emails || []).length">
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('contacts.ui.email') }}</dt>
                        <template x-for="(e,i) in c.emails" :key="'e'+i">
                            <dd class="mt-1 flex items-baseline justify-between gap-3">
                                <a :href="'mailto:'+e.value" class="truncate text-gray-900 dark:text-gray-100 hover:underline" x-text="e.value"></a>
                                <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500" x-text="label(e.type)"></span>
                            </dd>
                        </template>
                    </div>
                </template>
                {{-- Websites --}}
                <template x-if="(c.urls || []).length">
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('contacts.ui.website') }}</dt>
                        <template x-for="(u,i) in c.urls" :key="'u'+i">
                            <dd class="mt-1 flex items-baseline justify-between gap-3">
                                <a :href="u.value" target="_blank" rel="noopener noreferrer" class="truncate text-gray-900 dark:text-gray-100 hover:underline" x-text="u.value"></a>
                                <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500" x-text="label(u.type)"></span>
                            </dd>
                        </template>
                    </div>
                </template>
                {{-- Addresses (with a small map thumbnail once geocoded) --}}
                <template x-if="(c.addresses || []).length">
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('contacts.ui.addresses') }}</dt>
                        <template x-for="(a,i) in c.addresses" :key="'ad'+i">
                            <dd class="mt-2 flex items-start justify-between gap-3">
                                <button type="button" @click="openMapChooser(i)" class="min-w-0 text-left" title="{{ __('contacts.ui.map_chooser_title') }}">
                                    <span class="block whitespace-pre-line text-gray-900 dark:text-gray-100 hover:underline" x-text="addressLines(a)"></span>
                                    <span class="mt-0.5 block text-xs text-gray-400 dark:text-gray-500" x-text="label(a.type)"></span>
                                </button>
                                <button type="button" x-show="geo[i]" x-cloak @click="showMap(i)"
                                    class="relative h-20 w-28 shrink-0 overflow-hidden rounded-md ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-gray-400"
                                    title="{{ __('contacts.ui.address_map') }}">
                                    <div :data-mini-map="i" class="pointer-events-none h-full w-full"></div>
                                </button>
                            </dd>
                        </template>
                    </div>
                </template>
                {{-- Birthday + anniversaries --}}
                <template x-if="c.bday || (c.anniversaries || []).length">
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('contacts.ui.bday') }} / {{ __('contacts.ui.anniversaries') }}</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100" x-show="c.bday"><span x-text="prettyDate(c.bday)"></span> <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('contacts.ui.bday') }}</span></dd>
                        <template x-for="(a,i) in c.anniversaries" :key="'an'+i">
                            <dd class="mt-1 text-gray-900 dark:text-gray-100"><span x-text="prettyDate(a.date)"></span> <span class="text-xs text-gray-400 dark:text-gray-500" x-text="a.label || ''"></span></dd>
                        </template>
                    </div>
                </template>
                {{-- Related --}}
                <template x-if="(c.related || []).length">
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('contacts.ui.related') }}</dt>
                        <template x-for="(r,i) in c.related" :key="'r'+i">
                            <dd class="mt-1 flex items-baseline justify-between gap-3">
                                <template x-if="r.contact_id">
                                    <a :href="cfg.contactBase + '/' + r.contact_id + '/view'" class="text-gray-900 dark:text-gray-100 hover:underline" x-text="r.name"></a>
                                </template>
                                <template x-if="! r.contact_id">
                                    <span class="text-gray-900 dark:text-gray-100" x-text="r.name || r.value"></span>
                                </template>
                                <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500" x-text="relatedLabel(r.type)"></span>
                            </dd>
                        </template>
                    </div>
                </template>
                {{-- Custom fields --}}
                <template x-if="(c.custom_fields || []).length">
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('contacts.ui.custom_fields') }}</dt>
                        <template x-for="(f,i) in c.custom_fields" :key="'cf'+i">
                            <dd class="mt-1 flex items-baseline justify-between gap-3">
                                <span class="text-gray-900 dark:text-gray-100" x-text="f.value"></span>
                                <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500" x-text="f.label || ''"></span>
                            </dd>
                        </template>
                    </div>
                </template>
                {{-- Groups --}}
                <template x-if="groupNames().length">
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('contacts.ui.groups') }}</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            <template x-for="g in groupNames()" :key="g">
                                <span class="rounded bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs text-gray-700 dark:text-gray-300" x-text="g"></span>
                            </template>
                        </dd>
                    </div>
                </template>
                {{-- Note --}}
                <template x-if="c.note">
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('contacts.ui.note') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-gray-900 dark:text-gray-100" x-text="c.note"></dd>
                    </div>
                </template>
            </dl>
        </div>

        {{-- Map provider chooser (opens the address externally in a new window) --}}
        <div x-show="mapChooser.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="mapChooser.open=false">
            <div class="absolute inset-0 bg-gray-900/50" @click="mapChooser.open=false"></div>
            <div class="relative my-24 w-full max-w-sm rounded-lg bg-white dark:bg-gray-900 p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('contacts.ui.map_chooser_title') }}</h3>
                <div class="mt-4 space-y-1">
                    {{-- Alphabetical: Apple Maps, Google Maps, HERE WeGo, OpenStreetMap --}}
                    <template x-for="p in [['apple','Apple Maps'],['google','Google Maps'],['here','HERE WeGo'],['osm','OpenStreetMap']]" :key="p[0]">
                        <button type="button" @click="openProvider(p[0])"
                            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <x-icon name="map-pin" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                            <span x-text="p[1]"></span>
                        </button>
                    </template>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="button" @click="mapChooser.open=false" class="rounded-md border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('contacts.ui.cancel') }}</button>
                </div>
            </div>
        </div>

        {{-- Address map preview --}}
        <div x-show="mapModal.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="closeMap()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeMap()"></div>
            <div class="relative my-16 w-full max-w-xl rounded-lg bg-white dark:bg-gray-900 p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('contacts.ui.map_title') }}</h3>
                    <button type="button" @click="closeMap()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>
                <p x-show="mapModal.error" x-cloak class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('contacts.ui.map_not_found') }}</p>
                <p x-show="mapModal.loading" x-cloak class="mt-4 text-sm text-gray-400 dark:text-gray-500">…</p>
                <div x-show="! mapModal.error && ! mapModal.loading" class="mt-4">
                    <div x-ref="contactMap" class="h-72 w-full overflow-hidden rounded-md ring-1 ring-gray-200 dark:ring-gray-700"></div>
                    <p class="mt-2 truncate text-xs text-gray-500 dark:text-gray-400" x-text="mapModal.display"></p>
                    <a x-show="mapModal.osmUrl" :href="mapModal.osmUrl" target="_blank" rel="noopener"
                        class="mt-1 inline-flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-white">
                        <x-icon name="map-pin" class="h-3.5 w-3.5" />{{ __('contacts.ui.map_open_osm') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
