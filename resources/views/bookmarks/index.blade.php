<x-layouts.app :title="__('bookmarks.title')">
  <div x-data="bookmarks({
        saveFailed: @js(__('bookmarks.save_failed')),
        deleteFolderConfirm: @js(__('bookmarks.delete_folder_confirm')),
        subfolderPrompt: @js(__('bookmarks.subfolder_prompt')),
        deleteConfirm: @js(__('bookmarks.delete_confirm')),
        emptyTrashConfirm: @js(__('bookmarks.empty_trash_confirm')),
        importResult: @js(__('bookmarks.import_result')),
        urlRequired: @js(__('bookmarks.url_required')),
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
                        <li class="flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-sm"
                            draggable="true" @dragstart="dragItem = { type: 'bookmark', id: b.id }" @dragend="dragItem = null" :class="dragItem && dragItem.type === 'bookmark' && dragItem.id === b.id ? 'opacity-50' : ''">
                            <template x-if="host(b.url)">
                                <img :src="'{{ route('bookmarks.favicon') }}?host='+encodeURIComponent(host(b.url))" alt="" x-on:error="$el.style.display='none'" class="mt-0.5 h-5 w-5 shrink-0 rounded">
                            </template>
                            <div class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5">
                                    <a :href="b.url" target="_blank" rel="noopener" @click="view === 'readlater' && markRead(b)" class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100 hover:underline" x-text="b.title"></a>
                                    <span x-show="b.dead" x-cloak class="shrink-0 rounded bg-red-50 dark:bg-red-950 px-1.5 py-0.5 text-[10px] font-medium text-red-700 dark:text-red-300" title="{{ __('bookmarks.dead_hint') }}">{{ __('bookmarks.dead') }}</span>
                                </span>
                                <p class="truncate text-xs text-gray-400 dark:text-gray-500" x-text="b.url"></p>
                                <p x-show="b.description" class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="b.description"></p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    {{-- Folder badge: which folder this bookmark is in --}}
                                    <template x-if="b.folderId && folderById(b.folderId)">
                                        <button type="button" @click="view = b.folderId; activeTag = ''" class="inline-flex items-center gap-1 rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[11px] text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700">
                                            <svg class="h-3 w-3 shrink-0" :style="folderById(b.folderId).color ? ('color:'+folderById(b.folderId).color) : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="folderIconPath(folderById(b.folderId).icon)" /></svg>
                                            <span x-text="folderById(b.folderId).name"></span>
                                        </button>
                                    </template>
                                    <template x-for="g in (b.tags ?? [])" :key="g"><button type="button" @click="activeTag = g" class="rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[11px] text-gray-600 dark:text-gray-400 hover:bg-gray-200" x-text="g"></button></template>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" @click="toggleReadLater(b)" :title="b.readLater ? @js(__('bookmarks.read_later_remove')) : @js(__('bookmarks.read_later_add'))" class="rounded p-1" :class="b.readLater && ! b.read ? 'text-gray-900 dark:text-gray-100' : 'text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400'"><x-icon name="clock" class="h-4 w-4" /></button>
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

    {{-- Folder editor modal (create / subfolder / edit + colour + icon) --}}
    <template x-teleport="body">
        <div x-show="folderEditor.open" x-cloak class="fixed inset-0 z-[1050] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="folderEditor.open = false">
            <div class="absolute inset-0 bg-gray-900/50" @click="folderEditor.open = false"></div>
            <div class="relative w-full max-w-sm rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="folderEditor.id ? @js(__('bookmarks.folder_edit_title')) : @js(__('bookmarks.folder_new_title'))"></h3>
                <div class="mt-4 space-y-4">
                    <label class="block text-sm">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('bookmarks.folder_name') }}</span>
                        <input type="text" x-model="folderEditor.name" @keydown.enter="saveFolder()" autofocus class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </label>
                    <div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('bookmarks.folder_color') }}</span>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <button type="button" @click="folderEditor.color = ''" :class="! folderEditor.color ? 'ring-2 ring-offset-1 ring-gray-400' : ''" class="h-6 w-6 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800" title="{{ __('bookmarks.folder_none') }}"></button>
                            <template x-for="c in ['#ef4444','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#6b7280']" :key="c">
                                <button type="button" @click="folderEditor.color = c" :style="'background-color:'+c" :class="folderEditor.color === c ? 'ring-2 ring-offset-1 ring-gray-400' : ''" class="h-6 w-6 rounded-full"></button>
                            </template>
                        </div>
                    </div>
                    <div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('bookmarks.folder_icon') }}</span>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <template x-for="ic in allFolderIcons.slice(0, 11)" :key="ic">
                                <button type="button" @click="folderEditor.icon = ic" :class="folderEditor.icon === ic ? 'bg-gray-800 text-white' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'" class="rounded p-1.5">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="folderIconPath(ic)" /></svg>
                                </button>
                            </template>
                            {{-- Show the current icon too if it's beyond the first row --}}
                            <template x-if="folderEditor.icon && ! allFolderIcons.slice(0, 11).includes(folderEditor.icon)">
                                <button type="button" class="rounded bg-gray-800 p-1.5 text-white">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="folderIconPath(folderEditor.icon)" /></svg>
                                </button>
                            </template>
                            <button type="button" @click="moreIconsOpen = true" class="rounded border border-dashed border-gray-300 dark:border-gray-700 px-2 py-1.5 text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('bookmarks.more_icons') }}</button>
                        </div>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="folderEditor.open = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                    <button type="button" @click="saveFolder()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('common.save') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Full icon palette (secondary modal) --}}
    <template x-teleport="body">
        <div x-show="moreIconsOpen" x-cloak class="fixed inset-0 z-[1060] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="moreIconsOpen = false">
            <div class="absolute inset-0 bg-gray-900/50" @click="moreIconsOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('bookmarks.all_icons') }}</h3>
                <div class="mt-4 grid max-h-[60vh] grid-cols-6 gap-1.5 overflow-y-auto sm:grid-cols-8">
                    <button type="button" @click="folderEditor.icon = ''; moreIconsOpen = false" :class="! folderEditor.icon ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'" class="flex aspect-square items-center justify-center rounded" title="{{ __('bookmarks.folder_none') }}">
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                    <template x-for="ic in allFolderIcons" :key="ic">
                        <button type="button" @click="folderEditor.icon = ic; moreIconsOpen = false" :class="folderEditor.icon === ic ? 'bg-gray-800 text-white' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'" class="flex aspect-square items-center justify-center rounded">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="folderIconPath(ic)" /></svg>
                        </button>
                    </template>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="button" @click="moreIconsOpen = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.close') }}</button>
                </div>
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
                        <div class="mt-1 flex items-center gap-2">
                            <input type="url" x-model="editing.url" placeholder="https://…" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                            <button type="button" @click="fetchMeta()" :disabled="fetchingMeta || !(editing.url||'').startsWith('http')" title="{{ __('bookmarks.fetch_meta') }}"
                                class="shrink-0 rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50">
                                <x-icon name="arrow-path" class="h-4 w-4" x-bind:class="fetchingMeta && 'animate-spin'" />
                            </button>
                        </div>
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
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" x-model="editing.favorite" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('bookmarks.field_favorite') }}</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" x-model="editing.readLater" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('bookmarks.read_later') }}</span>
                        </label>
                    </div>
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
