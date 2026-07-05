<x-layouts.app :title="__('contacts.dup.heading')">
    @php $cfg = [
        'dataUrl' => route('contacts.duplicates.data'),
        'mergeUrl' => route('contacts.duplicates.merge'),
        'dismissUrl' => route('contacts.duplicates.dismiss'),
        'confirm' => __('contacts.dup.merge_confirm'),
        'token' => csrf_token(),
        'reasons' => [
            'email' => __('contacts.dup.match_email'),
            'phone' => __('contacts.dup.match_phone'),
            'name' => __('contacts.dup.match_name'),
        ],
    ]; @endphp
    <div x-data="contactDuplicatesPage(@js($cfg))" x-init="init()">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ __('contacts.dup.heading') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">{{ __('contacts.dup.subheading') }}</p>
            </div>
            <a href="{{ route('contacts.index') }}" class="shrink-0 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('contacts.dup.back') }}</a>
        </div>

        <template x-if="!loading && groups.length === 0">
            <div class="mt-8 rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                {{ __('contacts.dup.empty') }}
            </div>
        </template>

        <div class="mt-6 space-y-6">
            <template x-for="g in groups" :key="g.signature">
                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500">
                            {{ __('contacts.dup.matched_by') }}:
                            <span x-text="g.reasons.map(r => cfg.reasons[r] || r).join(', ')"></span>
                        </span>
                        <div class="flex gap-2">
                            <button type="button" @click="merge(g)"
                                class="inline-flex min-h-11 items-center gap-1.5 rounded-md bg-gray-900 px-4 py-2.5 text-xs font-medium text-white hover:bg-gray-800">
                                <x-icon name="arrows-pointing-in" class="h-4 w-4" />
                                {{ __('contacts.dup.merge') }}
                            </button>
                            <button type="button" @click="dismiss(g)"
                                class="min-h-11 rounded-md border border-gray-300 px-4 py-2.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('contacts.dup.dismiss') }}</button>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="c in g.contacts" :key="c.id">
                            <label class="block cursor-pointer rounded-lg border p-3"
                                :class="primary[g.signature] === c.id ? 'border-gray-900 ring-1 ring-gray-900' : 'border-gray-200'">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                                        <template x-if="c.avatar"><img :src="c.avatar" alt="" class="h-full w-full object-cover"></template>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900" x-text="(((c.first_name||'')+' '+(c.last_name||'')).trim()) || c.fn || '—'"></p>
                                        <p class="truncate text-xs text-gray-500" x-text="c.org || ''"></p>
                                    </div>
                                </div>
                                <div class="mt-2 space-y-0.5">
                                    <template x-for="(e,i) in c.emails" :key="'e'+i">
                                        <p class="break-all text-xs text-gray-600" x-text="e"></p>
                                    </template>
                                    <template x-for="(p,i) in c.phones" :key="'p'+i">
                                        <p class="break-all text-xs text-gray-600" x-text="p"></p>
                                    </template>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <input type="radio" :name="'primary-'+g.signature" :value="c.id" x-model.number="primary[g.signature]"
                                        class="text-gray-900 focus:ring-gray-500">
                                    <span class="text-xs font-medium text-gray-700">{{ __('contacts.dup.keep_as_primary') }}</span>
                                </div>
                            </label>
                        </template>
                    </div>
                </section>
            </template>
        </div>

        {{-- Merge confirm modal --}}
        <div x-show="confirmModal.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="confirmModal.open=false">
            <div class="absolute inset-0 bg-gray-900/40" @click="confirmModal.open=false"></div>
            <div class="relative my-8 sm:my-24 w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('contacts.dup.merge') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ __('contacts.dup.merge_confirm') }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="confirmModal.open=false" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('contacts.ui.cancel') }}</button>
                    <button type="button" @click="doConfirm()" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('contacts.dup.merge') }}</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
