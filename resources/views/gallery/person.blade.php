<x-layouts.app :title="__('gallery.people_heading')">
    @php $cfg = [
        'dataUrl' => route('gallery.people.show.data', ['person' => $person]),
        'updateUrl' => route('gallery.people.update', ['person' => $person]),
        'mergeUrl' => route('gallery.people.merge', ['person' => $person]),
        'faceBase' => url('gallery/faces'),
        'token' => csrf_token(),
        'mergeConfirm' => __('gallery.person_merge_confirm'),
        'reassignConfirm' => __('gallery.person_reassign_confirm'),
    ]; @endphp
    <div x-data="personPage(@js($cfg))" x-init="init()">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('gallery.people') }}" class="text-sm text-gray-500 hover:text-gray-900">← {{ __('gallery.person_back') }}</a>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500">{{ __('gallery.person_rename') }}</label>
                <input type="text" x-model="person.name" placeholder="{{ __('gallery.person_rename_placeholder') }}"
                    class="mt-1 block w-full max-w-sm rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
            </div>
            <button type="button" @click="save()" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('gallery.person_save') }}</button>
            <button type="button" @click="toggleHidden()" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                x-text="person.hidden ? '{{ __('gallery.person_unhide') }}' : '{{ __('gallery.person_hide') }}'"></button>
            <select x-show="others.length" @change="merge($event.target.value); $event.target.value=''"
                class="rounded-md border-gray-300 text-sm text-gray-700">
                <option value="">{{ __('gallery.person_merge') }}</option>
                <template x-for="o in others" :key="o.id"><option :value="o.id" x-text="o.name"></option></template>
            </select>
        </div>
        <p class="mt-1 text-xs text-gray-400" x-text="'{{ __('gallery.person_photos', ['count' => '__C__']) }}'.replace('__C__', person.count || 0)"></p>

        {{-- Faces (reassign a misattributed one) --}}
        <template x-if="faces.length">
            <div class="mt-6">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('gallery.person_faces') }}</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    <template x-for="f in faces" :key="f.id">
                        <div class="group relative">
                            <img :src="f.thumb" alt="" class="h-16 w-16 rounded-full object-cover ring-1 ring-gray-200">
                            <button @click="reassignFace(f.id)" title="{{ __('gallery.person_reassign') }}"
                                class="absolute -right-1 -top-1 hidden rounded-full bg-gray-900 px-1.5 text-xs text-white group-hover:block">✕</button>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
            <template x-for="ph in photos" :key="ph.id">
                <a :href="'/gallery/' + ph.id + '/original'" class="block">
                    <img :src="ph.thumb" alt="" class="aspect-square w-full rounded object-cover bg-gray-100" loading="lazy" :title="ph.name">
                </a>
            </template>
        </div>
    </div>
</x-layouts.app>
