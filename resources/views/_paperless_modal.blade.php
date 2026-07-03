{{-- Shared Paperless transfer modal — driven by the global $store.paperless,
     reused by the mail attachment list and the file browser. --}}
<template x-teleport="body">
    <div x-show="$store.paperless.open" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true"
        x-init="$store.paperless.labels = { failed: @js(__('paperless.failed')) }"
        @keydown.escape.window="$store.paperless.open && $store.paperless.close()">
        <div class="absolute inset-0 bg-gray-900/60" @click="$store.paperless.close()"></div>
        <div class="relative flex max-h-[92vh] w-full max-w-lg flex-col rounded-lg bg-white shadow-xl">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
                <h3 class="text-base font-semibold text-gray-900">{{ __('paperless.modal_title') }}</h3>
                <button type="button" @click="$store.paperless.close()" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('paperless.cancel') }}"><x-icon name="x-mark" class="h-5 w-5" /></button>
            </div>

            <div class="min-h-0 flex-1 space-y-4 overflow-auto p-5" x-data="{ s: $store.paperless }">
                <p class="truncate text-xs text-gray-500" x-text="s.filename"></p>

                {{-- Title --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('paperless.doc_title') }}</label>
                    <input type="text" x-model="s.form.title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                </div>

                {{-- Date --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('paperless.date') }}</label>
                    <input type="date" x-model="s.form.created" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                </div>

                {{-- Correspondent --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('paperless.correspondent') }}</label>
                    <select x-model.number="s.form.correspondent" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        <option value="">{{ __('paperless.none') }}</option>
                        <template x-for="c in s.correspondents" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                    </select>
                    <div class="mt-2 flex items-center gap-2">
                        <input type="text" x-model="s.newCorrespondent" placeholder="{{ __('paperless.new_placeholder') }}" @keydown.enter.prevent="s.createTerm('correspondent', s.newCorrespondent)"
                            class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <button type="button" @click="s.createTerm('correspondent', s.newCorrespondent)" class="shrink-0 rounded-md border border-gray-300 px-2 py-1.5 text-xs text-gray-700 hover:bg-gray-50">{{ __('paperless.add') }}</button>
                    </div>
                </div>

                {{-- Document type --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('paperless.document_type') }}</label>
                    <select x-model.number="s.form.documentType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        <option value="">{{ __('paperless.none') }}</option>
                        <template x-for="t in s.documentTypes" :key="t.id"><option :value="t.id" x-text="t.name"></option></template>
                    </select>
                    <div class="mt-2 flex items-center gap-2">
                        <input type="text" x-model="s.newType" placeholder="{{ __('paperless.new_placeholder') }}" @keydown.enter.prevent="s.createTerm('document_type', s.newType)"
                            class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <button type="button" @click="s.createTerm('document_type', s.newType)" class="shrink-0 rounded-md border border-gray-300 px-2 py-1.5 text-xs text-gray-700 hover:bg-gray-50">{{ __('paperless.add') }}</button>
                    </div>
                </div>

                {{-- Tags (multi-select) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('paperless.tags') }}</label>
                    <div class="mt-1 max-h-32 overflow-auto rounded-md border border-gray-200 p-2">
                        <template x-if="! s.tags.length"><p class="text-xs text-gray-400">{{ __('paperless.none') }}</p></template>
                        <template x-for="t in s.tags" :key="t.id">
                            <label class="flex items-center gap-2 py-0.5 text-sm text-gray-700">
                                <input type="checkbox" :value="t.id" x-model.number="s.form.tags" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                                <span x-text="t.name"></span>
                            </label>
                        </template>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <input type="text" x-model="s.newTag" placeholder="{{ __('paperless.new_placeholder') }}" @keydown.enter.prevent="s.createTerm('tag', s.newTag)"
                            class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <button type="button" @click="s.createTerm('tag', s.newTag)" class="shrink-0 rounded-md border border-gray-300 px-2 py-1.5 text-xs text-gray-700 hover:bg-gray-50">{{ __('paperless.add') }}</button>
                    </div>
                </div>

                {{-- File browser only: keep or delete the vault file after upload. --}}
                <label x-show="s.allowDelete" class="flex items-center gap-2 border-t border-gray-100 pt-3">
                    <input type="checkbox" x-model="s.deleteAfter" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                    <span class="text-sm text-gray-700">{{ __('paperless.delete_after') }}</span>
                </label>

                <p x-show="s.error" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="s.error"></p>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-5 py-3">
                <button type="button" @click="$store.paperless.close()" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('paperless.cancel') }}</button>
                <button type="button" @click="$store.paperless.submit()" :disabled="$store.paperless.submitting"
                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                    x-text="$store.paperless.submitting ? @js(__('paperless.sending')) : @js(__('paperless.send'))"></button>
            </div>
        </div>
    </div>
</template>
