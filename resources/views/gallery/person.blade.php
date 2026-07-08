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
    <div class="flex flex-col gap-4 md:flex-row">
    @include('gallery._sidebar')
    <div class="min-w-0 flex-1" x-data="personPage(@js($cfg))" x-init="init()">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('gallery.people') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">← {{ __('gallery.person_back') }}</a>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <div class="relative flex-1 max-w-sm">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('gallery.person_rename') }}</label>
                <input type="text" x-model="person.name"
                    placeholder="{{ __('gallery.person_rename_placeholder') }}"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
            </div>
            <x-button variant="primary" @click="save()">{{ __('gallery.person_save') }}</x-button>
            <span x-show="saved" x-cloak x-transition class="inline-flex items-center gap-1 text-sm font-medium text-green-600"><x-icon name="check" class="h-4 w-4" /> {{ __('gallery.person_saved') }}</span>
            <button type="button" @click="toggleHidden()" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                x-text="person.hidden ? '{{ __('gallery.person_unhide') }}' : '{{ __('gallery.person_hide') }}'"></button>
            {{-- Merge another (named) person in — autocomplete --}}
            <div class="relative" x-show="others.length">
                <input type="text" x-model="mergeQuery" @focus="mergeOpen=true" @click="mergeOpen=true"
                    @keydown.escape.stop="mergeOpen=false" @click.outside="mergeOpen=false"
                    placeholder="{{ __('gallery.person_merge') }}"
                    class="rounded-md border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:border-gray-500 focus:ring-gray-500">
                <div x-show="mergeOpen && filteredOthers().length" x-cloak
                    class="absolute right-0 z-20 mt-1 max-h-56 w-56 overflow-auto rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-1 shadow-lg">
                    <template x-for="o in filteredOthers()" :key="o.id">
                        <button type="button" @click="pickMerge(o)" class="block w-full px-3 py-1.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800" x-text="o.name"></button>
                    </template>
                </div>
            </div>
        </div>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500" x-text="'{{ __('gallery.person_photos', ['count' => '__C__']) }}'.replace('__C__', person.count || 0)"></p>

        {{-- Faces (reassign a misattributed one) --}}
        <template x-if="faces.length">
            <div class="mt-6">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.person_faces') }}</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    <template x-for="f in faces" :key="f.id">
                        <div class="group relative">
                            <img :src="f.thumb" alt="" class="h-16 w-16 rounded-full object-cover ring-1 ring-gray-200 dark:ring-gray-700">
                            <button @click="reassignFace(f.id)" title="{{ __('gallery.person_reassign') }}"
                                class="absolute -right-1 -top-1 hidden rounded-full bg-gray-900 dark:bg-gray-100 px-1.5 text-xs text-white dark:text-gray-900 group-hover:block"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
            <template x-for="ph in photos" :key="ph.id">
                <a :href="'/gallery/' + ph.id + '/original'" class="block">
                    <img :src="ph.thumb" alt="" class="aspect-square w-full rounded object-cover bg-gray-100 dark:bg-gray-800" loading="lazy" :title="ph.name">
                </a>
            </template>
        </div>
    </div>
    </div>
</x-layouts.app>
