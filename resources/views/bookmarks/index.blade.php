<x-layouts.app :title="__('bookmarks.title')">
  <div x-data="bookmarks({
        saveFailed: @js(__('bookmarks.save_failed')),
        deleteFolderConfirm: @js(__('bookmarks.delete_folder_confirm')),
        deleteConfirm: @js(__('bookmarks.delete_confirm')),
        emptyTrashConfirm: @js(__('bookmarks.empty_trash_confirm')),
     })">

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-6 text-center text-sm text-red-700 dark:text-red-300">{{ __('bookmarks.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div>
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('bookmarks.title') }}</h1>
            <x-button variant="primary" icon="plus" class="shrink-0" @click="newBookmark()">{{ __('bookmarks.new_bookmark') }}</x-button>
        </div>

        <p x-show="error" x-cloak class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800" x-text="error"></p>

        <div class="mt-6 flex flex-col gap-4 md:flex-row" style="min-height: calc(100vh - 18rem);">
            {{-- Sidebar --}}
        <div class="md:hidden">
            <button type="button" @click="$store.nav.toggleSidebar()"
                class="flex min-h-11 w-full items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm">
                <x-icon name="bars-3" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                <span>{{ __('common.sections') }}</span>
            </button>
        </div>
        <aside class="hidden w-full shrink-0 space-y-4 self-start md:block md:w-64">
            @include('bookmarks._sidebar_content')
        </aside>
        <x-sheet side="left" store="sidebarOpen" :title="__('common.sections')">
            <div class="space-y-4">@include('bookmarks._sidebar_content')</div>
        </x-sheet>

            {{-- Main --}}
            <section class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <input type="search" x-model="query" placeholder="{{ __('bookmarks.search') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <button type="button" x-show="view === 'trash' && trashCount" @click="emptyTrash()" class="shrink-0 rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 dark:text-red-300 hover:bg-red-50">{{ __('bookmarks.empty_trash') }}</button>
                </div>

                <ul class="mt-4 space-y-2">
                    <template x-for="b in filtered" :key="b.id">
                        <li class="flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-sm">
                            <template x-if="host(b.url)">
                                <img :src="`https://${host(b.url)}/favicon.ico`" alt="" referrerpolicy="no-referrer" x-on:error="$el.style.display='none'" class="mt-0.5 h-5 w-5 shrink-0 rounded">
                            </template>
                            <div class="min-w-0 flex-1">
                                <a :href="b.url" target="_blank" rel="noopener" class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100 hover:underline" x-text="b.title"></a>
                                <p class="truncate text-xs text-gray-400 dark:text-gray-500" x-text="b.url"></p>
                                <p x-show="b.description" class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="b.description"></p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <template x-for="g in (b.tags ?? [])" :key="g"><button type="button" @click="activeTag = g" class="rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[11px] text-gray-600 dark:text-gray-400 hover:bg-gray-200" x-text="g"></button></template>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" @click="toggleFavorite(b)" :title="b.favorite ? @js(__('bookmarks.unfavorite')) : @js(__('bookmarks.favorite'))" class="rounded p-1" :class="b.favorite ? 'text-red-500' : 'text-gray-300 hover:text-gray-500'"><x-icon name="heart" class="h-4 w-4" /></button>
                                <template x-if="view !== 'trash'">
                                    <span class="flex items-center gap-1">
                                        <button type="button" @click="editBookmark(b)" title="{{ __('bookmarks.edit') }}" class="rounded p-1 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="pencil" class="h-4 w-4" /></button>
                                        <button type="button" @click="trash(b)" title="{{ __('bookmarks.to_trash') }}" class="rounded p-1 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
                                    </span>
                                </template>
                                <template x-if="view === 'trash'">
                                    <span class="flex items-center gap-1">
                                        <button type="button" @click="restore(b)" title="{{ __('bookmarks.restore') }}" class="rounded p-1 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
                                        <button type="button" @click="remove(b)" title="{{ __('bookmarks.delete_forever') }}" class="rounded p-1 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                                    </span>
                                </template>
                            </div>
                        </li>
                    </template>
                </ul>
                <p x-show="! filtered.length" class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('bookmarks.empty') }}</p>
            </section>
        </div>
      </div>
    </template>

    {{-- Editor modal --}}
    <template x-teleport="body">
        <div x-show="editorOpen" x-cloak class="fixed inset-0 z-[1050] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeEditor()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeEditor()"></div>
            <div class="relative flex max-h-[92vh] w-full max-w-lg flex-col rounded-lg bg-white dark:bg-gray-900 shadow-xl" x-show="editing">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="editing?.id ? @js(__('bookmarks.edit_title')) : @js(__('bookmarks.add_title'))"></h3>
                    <button type="button" @click="closeEditor()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600" aria-label="{{ __('bookmarks.cancel') }}"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>
                <div class="min-h-0 flex-1 space-y-4 overflow-auto p-5" x-show="editing">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('bookmarks.url') }}</label>
                        <input type="url" x-model="editing.url" placeholder="https://…" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('bookmarks.field_title') }}</label>
                        <input type="text" x-model="editing.title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('bookmarks.description') }}</label>
                        <textarea x-model="editing.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('bookmarks.field_folder') }}</label>
                            <select x-model="editing.folderId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                                <option :value="null">{{ __('bookmarks.no_folder') }}</option>
                                <template x-for="f in folders" :key="f.id"><option :value="f.id" x-text="f.name"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('bookmarks.tags') }}</label>
                            <input type="text" x-model="tagsValue" placeholder="{{ __('bookmarks.tags_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        </div>
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" x-model="editing.favorite" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('bookmarks.field_favorite') }}</span>
                    </label>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 dark:border-gray-800 px-5 py-3">
                    <button type="button" @click="closeEditor()" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('bookmarks.cancel') }}</button>
                    <button type="button" @click="saveBookmark()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('bookmarks.save') }}</button>
                </div>
            </div>
        </div>
    </template>
  </div>
</x-layouts.app>
