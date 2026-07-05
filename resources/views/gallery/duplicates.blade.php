<x-layouts.app :title="__('gallery.dup_heading')">
    @php $dup = [
        'dataUrl' => route('gallery.duplicates.data'),
        'resolveBase' => route('gallery.duplicates.resolve', ['group' => '__G__']),
        'dismissBase' => route('gallery.duplicates.dismiss', ['group' => '__G__']),
        'confirm' => __('gallery.dup_confirm'),
        'token' => csrf_token(),
    ]; @endphp
    <div class="flex flex-col gap-4 md:flex-row">
    @include('gallery._sidebar')
    <div class="min-w-0 flex-1" x-data="duplicatesPage(@js($dup))" x-init="init()">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ __('gallery.dup_heading') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('gallery.dup_subheading') }}</p>
            </div>
        </div>

        <template x-if="!loading && groups.length === 0">
            <div class="mt-8 rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                {{ __('gallery.dup_empty') }}
            </div>
        </template>

        <div class="mt-6 space-y-6">
            <template x-for="g in groups" :key="g.group">
                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500">
                            {{ __('gallery.dup_similarity') }}: <span x-text="Math.round(g.score * 100) + '%'"></span>
                        </span>
                        <div class="flex gap-2">
                            <button type="button" @click="resolve(g)"
                                class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800">{{ __('gallery.dup_resolve') }}</button>
                            <button type="button" @click="dismiss(g)"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.dup_dismiss') }}</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                        <template x-for="p in g.photos" :key="p.id">
                            <label class="block cursor-pointer rounded-lg border p-2"
                                :class="keep[g.group] === p.id ? 'border-gray-900 ring-1 ring-gray-900' : 'border-gray-200'">
                                <img :src="p.thumb" alt="" class="aspect-square w-full rounded object-cover bg-gray-100" loading="lazy">
                                <div class="mt-2 flex items-center gap-2">
                                    <input type="radio" :name="'keep-'+g.group" :value="p.id" x-model.number="keep[g.group]"
                                        class="text-gray-900 focus:ring-gray-500">
                                    <span class="text-xs font-medium text-gray-700">{{ __('gallery.dup_keep_label') }}</span>
                                </div>
                                <p class="mt-1 truncate text-xs text-gray-700" x-text="p.name" :title="p.name"></p>
                                <p class="text-xs text-gray-400" x-text="p.size_human"></p>
                            </label>
                        </template>
                    </div>
                </section>
            </template>
        </div>
    </div>
    </div>
</x-layouts.app>
