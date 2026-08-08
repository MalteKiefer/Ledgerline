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

    <div x-data="contactViewPage(@js($cfg))" x-init="init()" class="mx-auto max-w-2xl">
        {{-- Top action bar --}}
        <div class="flex items-center gap-2">
            <x-m3.icon-button name="arrow_back" href="{{ route('contacts.index') }}" tooltip="{{ __('contacts.ui.back') }}" />
            <div class="ml-auto flex items-center gap-2">
                <x-m3.icon-button name="star" tooltip="{{ __('contacts.ui.favorite_add') }}" ::class="c.favorite && 'text-md-primary'" />
                <x-m3.button variant="filled" icon="edit" href="{{ route('contacts.edit', $contactId) }}">{{ __('contacts.ui.edit') }}</x-m3.button>
            </div>
        </div>

        {{-- Hero --}}
        <div class="mt-6 flex items-center gap-5">
            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full text-2xl font-medium text-white"
                :style="!c.photo && ('background:'+avatarColor())">
                <template x-if="c.photo"><img :src="c.photo" alt="" class="h-full w-full object-cover"></template>
                <template x-if="! c.photo && initials()"><span x-text="initials()"></span></template>
                <template x-if="! c.photo && ! initials()"><span class="msym text-3xl">person</span></template>
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="flex items-center gap-2 text-2xl font-semibold text-md-on-surface">
                    <span class="truncate" x-text="displayName()"></span>
                    <span x-show="c.favorite" x-cloak class="msym msym-fill text-lg text-md-primary">star</span>
                </h1>
                <p class="truncate text-sm text-md-on-surface-var" x-text="[c.org, c.title].filter(Boolean).join(' · ')"></p>
                <a x-show="c.person" x-cloak :href="c.person?.url"
                    class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-md-primary hover:underline">
                    <span class="msym text-sm">photo_library</span>{{ __('contacts.ui.show_photos') }}
                </a>
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="mt-5 flex flex-wrap gap-2">
            <template x-if="(c.phones || []).length">
                <x-m3.button variant="tonal" icon="call" ::href="'tel:'+c.phones[0].value">{{ __('contacts.ui.phone') }}</x-m3.button>
            </template>
            <template x-if="(c.emails || []).length">
                <x-m3.button variant="tonal" icon="mail" ::href="'mailto:'+c.emails[0].value">{{ __('contacts.ui.email') }}</x-m3.button>
            </template>
        </div>

        {{-- Detail card: one field group per row, leading Material icon --}}
        <x-m3.card class="mt-5 px-5 py-2">
            <dl class="divide-y divide-md-outline-variant/70 text-sm">
                {{-- Phones --}}
                <template x-if="(c.phones || []).length">
                    <div class="flex items-start gap-4 py-3">
                        <span class="msym mt-0.5 text-xl text-md-on-surface-var">call</span>
                        <div class="min-w-0 flex-1 space-y-2">
                            <template x-for="(p,i) in c.phones" :key="'p'+i">
                                <div class="flex items-baseline justify-between gap-3">
                                    <a :href="'tel:'+p.value" class="text-md-on-surface hover:underline" x-text="p.value"></a>
                                    <span class="shrink-0 text-xs text-md-on-surface-var" x-text="label(p.type)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                {{-- Emails --}}
                <template x-if="(c.emails || []).length">
                    <div class="flex items-start gap-4 py-3">
                        <span class="msym mt-0.5 text-xl text-md-on-surface-var">mail</span>
                        <div class="min-w-0 flex-1 space-y-2">
                            <template x-for="(e,i) in c.emails" :key="'e'+i">
                                <div class="flex items-baseline justify-between gap-3">
                                    <a :href="'mailto:'+e.value" class="truncate text-md-on-surface hover:underline" x-text="e.value"></a>
                                    <span class="shrink-0 text-xs text-md-on-surface-var" x-text="label(e.type)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                {{-- Websites --}}
                <template x-if="(c.urls || []).length">
                    <div class="flex items-start gap-4 py-3">
                        <span class="msym mt-0.5 text-xl text-md-on-surface-var">language</span>
                        <div class="min-w-0 flex-1 space-y-2">
                            <template x-for="(u,i) in c.urls" :key="'u'+i">
                                <div class="flex items-baseline justify-between gap-3">
                                    <a :href="u.value" target="_blank" rel="noopener noreferrer" class="truncate text-md-on-surface hover:underline" x-text="u.value"></a>
                                    <span class="shrink-0 text-xs text-md-on-surface-var" x-text="label(u.type)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                {{-- Addresses --}}
                <template x-if="(c.addresses || []).length">
                    <div class="flex items-start gap-4 py-3">
                        <span class="msym mt-0.5 text-xl text-md-on-surface-var">location_on</span>
                        <div class="min-w-0 flex-1 space-y-3">
                            <template x-for="(a,i) in c.addresses" :key="'ad'+i">
                                <div class="flex items-start justify-between gap-3">
                                    <button type="button" @click="openMapChooser(i)" class="min-w-0 text-left" title="{{ __('contacts.ui.map_chooser_title') }}">
                                        <span class="block whitespace-pre-line text-md-on-surface hover:underline" x-text="addressLines(a)"></span>
                                        <span class="mt-0.5 block text-xs text-md-on-surface-var" x-text="label(a.type)"></span>
                                    </button>
                                    <button type="button" x-show="geo[i]" x-cloak @click="showMap(i)"
                                        class="relative h-16 w-24 shrink-0 overflow-hidden rounded-lg ring-1 ring-md-outline hover:ring-md-primary"
                                        title="{{ __('contacts.ui.address_map') }}">
                                        <div :data-mini-map="i" class="pointer-events-none h-full w-full"></div>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                {{-- Birthday + anniversaries --}}
                <template x-if="c.bday || (c.anniversaries || []).length">
                    <div class="flex items-start gap-4 py-3">
                        <span class="msym mt-0.5 text-xl text-md-on-surface-var">cake</span>
                        <div class="min-w-0 flex-1 space-y-1">
                            <div x-show="c.bday" class="text-md-on-surface"><span x-text="prettyDate(c.bday)"></span> <span class="text-xs text-md-on-surface-var">{{ __('contacts.ui.bday') }}</span></div>
                            <template x-for="(a,i) in c.anniversaries" :key="'an'+i">
                                <div class="text-md-on-surface"><span x-text="prettyDate(a.date)"></span> <span class="text-xs text-md-on-surface-var" x-text="a.label || ''"></span></div>
                            </template>
                        </div>
                    </div>
                </template>
                {{-- Related --}}
                <template x-if="(c.related || []).length">
                    <div class="flex items-start gap-4 py-3">
                        <span class="msym mt-0.5 text-xl text-md-on-surface-var">group</span>
                        <div class="min-w-0 flex-1 space-y-2">
                            <template x-for="(r,i) in c.related" :key="'r'+i">
                                <div class="flex items-baseline justify-between gap-3">
                                    <template x-if="r.contact_id">
                                        <a :href="cfg.contactBase + '/' + r.contact_id + '/view'" class="text-md-on-surface hover:underline" x-text="r.name"></a>
                                    </template>
                                    <template x-if="! r.contact_id">
                                        <span class="text-md-on-surface" x-text="r.name || r.value"></span>
                                    </template>
                                    <span class="shrink-0 text-xs text-md-on-surface-var" x-text="relatedLabel(r.type)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                {{-- Custom fields --}}
                <template x-if="(c.custom_fields || []).length">
                    <div class="flex items-start gap-4 py-3">
                        <span class="msym mt-0.5 text-xl text-md-on-surface-var">list</span>
                        <div class="min-w-0 flex-1 space-y-2">
                            <template x-for="(f,i) in c.custom_fields" :key="'cf'+i">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="text-md-on-surface" x-text="f.value"></span>
                                    <span class="shrink-0 text-xs text-md-on-surface-var" x-text="f.label || ''"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                {{-- Groups --}}
                <template x-if="groupNames().length">
                    <div class="flex items-start gap-4 py-3">
                        <span class="msym mt-0.5 text-xl text-md-on-surface-var">label</span>
                        <div class="flex min-w-0 flex-1 flex-wrap gap-1.5">
                            <template x-for="g in groupNames()" :key="g">
                                <x-m3.badge tone="accent"><span x-text="g"></span></x-m3.badge>
                            </template>
                        </div>
                    </div>
                </template>
                {{-- Note --}}
                <template x-if="c.note">
                    <div class="flex items-start gap-4 py-3">
                        <span class="msym mt-0.5 text-xl text-md-on-surface-var">notes</span>
                        <dd class="min-w-0 flex-1 whitespace-pre-line text-md-on-surface" x-text="c.note"></dd>
                    </div>
                </template>
            </dl>
        </x-m3.card>

        {{-- Map provider chooser --}}
        <div x-show="mapChooser.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" @keydown.escape.window="mapChooser.open=false">
            <div class="absolute inset-0 bg-black/40" @click="mapChooser.open=false"></div>
            <x-m3.card class="relative my-24 w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold text-md-on-surface">{{ __('contacts.ui.map_chooser_title') }}</h3>
                <div class="mt-4 space-y-1">
                    <template x-for="p in [['apple','Apple Maps'],['google','Google Maps'],['here','HERE WeGo'],['osm','OpenStreetMap']]" :key="p[0]">
                        <button type="button" @click="openProvider(p[0])"
                            class="m3-state flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm text-md-on-surface">
                            <span class="msym text-xl text-md-on-surface-var">location_on</span><span x-text="p[1]"></span>
                        </button>
                    </template>
                </div>
                <div class="mt-4 flex justify-end">
                    <x-m3.button variant="text" type="button" @click="mapChooser.open=false">{{ __('contacts.ui.cancel') }}</x-m3.button>
                </div>
            </x-m3.card>
        </div>

        {{-- Address map preview --}}
        <div x-show="mapModal.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeMap()">
            <div class="absolute inset-0 bg-black/40" @click="closeMap()"></div>
            <x-m3.card class="relative my-16 w-full max-w-xl p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-md-on-surface">{{ __('contacts.ui.map_title') }}</h3>
                    <x-m3.icon-button name="close" @click="closeMap()" />
                </div>
                <p x-show="mapModal.error" x-cloak class="mt-4 text-sm text-md-on-surface-var">{{ __('contacts.ui.map_not_found') }}</p>
                <p x-show="mapModal.loading" x-cloak class="mt-4 text-sm text-md-on-surface-var">…</p>
                <div x-show="! mapModal.error && ! mapModal.loading" class="mt-4">
                    <div x-ref="contactMap" class="h-72 w-full overflow-hidden rounded-lg ring-1 ring-md-outline"></div>
                    <p class="mt-2 truncate text-xs text-md-on-surface-var" x-text="mapModal.display"></p>
                    <a x-show="mapModal.osmUrl" :href="mapModal.osmUrl" target="_blank" rel="noopener"
                        class="mt-1 inline-flex items-center gap-1 text-xs text-md-primary hover:underline">
                        <span class="msym text-sm">open_in_new</span>{{ __('contacts.ui.map_open_osm') }}
                    </a>
                </div>
            </x-m3.card>
        </div>
    </div>
</x-layouts.app>
