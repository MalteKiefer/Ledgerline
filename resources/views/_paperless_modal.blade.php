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
                <p class="flex items-center gap-2 truncate text-xs text-gray-500">
                    <x-icon x-show="s.preparing" x-cloak name="arrow-path" class="h-3.5 w-3.5 shrink-0 animate-spin" />
                    <span class="truncate" x-text="s.filename"></span>
                </p>

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
                {{-- Correspondent (single, autocomplete) --}}
                <div x-data="{ show: false }" @click.outside="show = false">
                    <label class="block text-sm font-medium text-gray-700">{{ __('paperless.correspondent') }}</label>
                    <div class="relative mt-1">
                        <input type="text" x-model="s.corrQuery" @focus="show = true" @input="show = true; s.form.correspondent = ''"
                            placeholder="{{ __('paperless.search_or_create') }}" autocomplete="off"
                            class="block w-full rounded-md border-gray-300 pr-8 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        <button type="button" x-show="s.corrQuery" @click="s.clearCorrespondent(); show = false" class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        <div x-show="show" x-cloak class="absolute z-30 mt-1 max-h-48 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
                            <template x-for="c in s.filteredCorrespondents" :key="c.id">
                                <button type="button" @click="s.selectCorrespondent(c); show = false" class="block w-full truncate px-3 py-1.5 text-left hover:bg-gray-50" x-text="c.name"></button>
                            </template>
                            <button type="button" x-show="s.canCreate(s.correspondents, s.corrQuery)" @click="s.createTerm('correspondent', s.corrQuery); show = false"
                                class="block w-full truncate px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><span class="text-gray-400">{{ __('paperless.create') }}:</span> «<span x-text="s.corrQuery"></span>»</button>
                            <p x-show="! s.filteredCorrespondents.length && ! s.canCreate(s.correspondents, s.corrQuery)" class="px-3 py-1.5 text-gray-400">{{ __('paperless.none') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Document type (single, autocomplete) --}}
                <div x-data="{ show: false }" @click.outside="show = false">
                    <label class="block text-sm font-medium text-gray-700">{{ __('paperless.document_type') }}</label>
                    <div class="relative mt-1">
                        <input type="text" x-model="s.typeQuery" @focus="show = true" @input="show = true; s.form.documentType = ''"
                            placeholder="{{ __('paperless.search_or_create') }}" autocomplete="off"
                            class="block w-full rounded-md border-gray-300 pr-8 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        <button type="button" x-show="s.typeQuery" @click="s.clearDocumentType(); show = false" class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        <div x-show="show" x-cloak class="absolute z-30 mt-1 max-h-48 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
                            <template x-for="t in s.filteredDocumentTypes" :key="t.id">
                                <button type="button" @click="s.selectDocumentType(t); show = false" class="block w-full truncate px-3 py-1.5 text-left hover:bg-gray-50" x-text="t.name"></button>
                            </template>
                            <button type="button" x-show="s.canCreate(s.documentTypes, s.typeQuery)" @click="s.createTerm('document_type', s.typeQuery); show = false"
                                class="block w-full truncate px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><span class="text-gray-400">{{ __('paperless.create') }}:</span> «<span x-text="s.typeQuery"></span>»</button>
                            <p x-show="! s.filteredDocumentTypes.length && ! s.canCreate(s.documentTypes, s.typeQuery)" class="px-3 py-1.5 text-gray-400">{{ __('paperless.none') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Tags (multi, autocomplete with chips) --}}
                <div x-data="{ show: false }" @click.outside="show = false">
                    <label class="block text-sm font-medium text-gray-700">{{ __('paperless.tags') }}</label>
                    <div class="mt-1 flex flex-wrap gap-1" x-show="s.form.tags.length">
                        <template x-for="id in s.form.tags" :key="id">
                            <span class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                <span x-text="s.tagName(id)"></span>
                                <button type="button" @click="s.removeTag(id)" class="text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-3 w-3" /></button>
                            </span>
                        </template>
                    </div>
                    <div class="relative mt-1">
                        <input type="text" x-model="s.tagQuery" @focus="show = true" @input="show = true"
                            placeholder="{{ __('paperless.search_or_create') }}" autocomplete="off"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        {{-- Opens upward: this is the last field, so a downward list is
                             clipped by the scroll area and hidden behind the footer. --}}
                        <div x-show="show" x-cloak class="absolute bottom-full z-30 mb-1 max-h-48 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
                            <template x-for="t in s.filteredTags" :key="t.id">
                                <button type="button" @click="s.addTag(t)" class="block w-full truncate px-3 py-1.5 text-left hover:bg-gray-50" x-text="t.name"></button>
                            </template>
                            <button type="button" x-show="s.canCreate(s.tags, s.tagQuery)" @click="s.createTerm('tag', s.tagQuery)"
                                class="block w-full truncate px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><span class="text-gray-400">{{ __('paperless.create') }}:</span> «<span x-text="s.tagQuery"></span>»</button>
                            <p x-show="! s.filteredTags.length && ! s.canCreate(s.tags, s.tagQuery)" class="px-3 py-1.5 text-gray-400">{{ __('paperless.none') }}</p>
                        </div>
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
                <button type="button" @click="$store.paperless.submit()" :disabled="$store.paperless.submitting || $store.paperless.preparing || ! $store.paperless.file"
                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                    x-text="$store.paperless.submitting ? @js(__('paperless.sending')) : @js(__('paperless.send'))"></button>
            </div>
        </div>
    </div>
</template>
