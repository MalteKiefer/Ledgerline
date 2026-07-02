<x-layouts.app :title="__('bookmarks.title')">
  <div x-data="vaultBookmarks({
        stale: @js(__('bookmarks.stale')),
        saveFailed: @js(__('bookmarks.save_failed')),
        invalidUrl: @js(__('bookmarks.invalid_url')),
     })">

    {{-- Vault not set up / locked: only the gate. --}}
    <template x-if="state === 'unconfigured' || state === 'locked'">
        <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
            <x-icon name="lock-closed" class="mx-auto h-10 w-10 text-gray-400" />
            <p class="mt-4 text-sm text-gray-600" x-text="state === 'locked' ? @js(__('bookmarks.locked_notice')) : @js(__('bookmarks.unconfigured_notice'))"></p>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('vault-panel'))"
                class="mt-5 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                x-text="state === 'locked' ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></button>
        </div>
    </template>

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">{{ __('bookmarks.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div>
        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <nav class="text-sm text-gray-500">
                    <button type="button" @click="cwd = null" class="hover:underline">{{ __('bookmarks.all') }}</button>
                    <template x-for="crumb in breadcrumb" :key="crumb.id">
                        <span><span aria-hidden="true">/</span>
                            <button type="button" @click="cwd = crumb.id" class="hover:underline" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900" x-text="currentFolderName ?? @js(__('bookmarks.title'))"></h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form class="flex items-center gap-1" @submit.prevent="mkdir($refs.newFolder.value); $refs.newFolder.value = ''">
                    <input type="text" x-ref="newFolder" required placeholder="{{ __('bookmarks.new_folder') }}"
                        class="w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <button type="submit" title="{{ __('bookmarks.new_folder') }}" aria-label="{{ __('bookmarks.new_folder') }}"
                        class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="folder-plus" class="h-5 w-5" /></button>
                </form>
                <button type="button" @click="openAdd()" title="{{ __('bookmarks.new_bookmark') }}" aria-label="{{ __('bookmarks.new_bookmark') }}"
                    class="rounded-md bg-gray-800 p-2 text-white hover:bg-gray-700"><x-icon name="plus" class="h-5 w-5" /></button>
            </div>
        </div>

        {{-- Search + tabs --}}
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <input type="search" x-model="query" placeholder="{{ __('bookmarks.search') }}"
                class="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            <button type="button" @click="view = 'active'" class="text-sm" :class="view === 'active' ? 'font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'">{{ __('bookmarks.active') }}</button>
            <button type="button" @click="view = 'trash'" class="text-sm" :class="view === 'trash' ? 'font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'">
                {{ __('bookmarks.trash') }} (<span x-text="trashCount"></span>)
            </button>
            <button type="button" x-show="view === 'trash' && trashCount" x-cloak @click="emptyTrash()" class="text-sm text-red-600 hover:text-red-700">{{ __('bookmarks.empty_trash') }}</button>
            <span x-show="activeTag" x-cloak class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs text-blue-800">
                {{ __('bookmarks.filtered_by') }}: <span x-text="activeTag"></span>
                <button type="button" @click="activeTag = ''" class="text-blue-500 hover:text-blue-700"><x-icon name="x-mark" class="h-3 w-3" /></button>
            </span>
        </div>

        <p x-show="error" x-cloak class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800" x-text="error"></p>

        {{-- List --}}
        <div class="mt-4 overflow-visible rounded-lg border border-gray-200 bg-white shadow-sm">
            <template x-if="rows.length === 0">
                <p class="px-4 py-10 text-center text-sm text-gray-500">{{ __('bookmarks.empty') }}</p>
            </template>
            <ul class="divide-y divide-gray-100" x-show="rows.length > 0">
                {{-- Parent-folder shortcut, like "cd .." — virtual row, never part
                     of rows(), so excluded from actions. Also a drop target. --}}
                <template x-if="cwd !== null && query === '' && activeTag === '' && view === 'active'">
                    <li class="flex cursor-pointer items-center gap-2 px-4 py-3 text-gray-500 hover:bg-gray-50" @click="cwd = parentFolderId"
                        @dragover="if (dragItem) $event.preventDefault()" @drop.prevent="dropInto(parentFolderId)">
                        <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                        ..
                    </li>
                </template>
                <template x-for="row in rows" :key="row.kind + row.id">
                    <li class="relative flex items-center gap-3 px-4 py-3 hover:bg-gray-50" x-data="{ menu: false }"
                        draggable="true"
                        @dragstart.stop="onDragStart($event, row)" @dragend="onDragEnd()"
                        @dragover="if (row.kind === 'folder' && dragItem && !(dragItem.kind === 'folder' && dragItem.id === row.id)) $event.preventDefault()"
                        @drop.prevent="row.kind === 'folder' && dropInto(row.id)">
                        {{-- Folder row --}}
                        <template x-if="row.kind === 'folder'">
                            <button type="button" @click="cwd = row.id" class="flex min-w-0 flex-1 items-center gap-2 text-left">
                                <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                                <span class="truncate text-sm font-medium text-gray-900" x-text="row.name"></span>
                            </button>
                        </template>

                        {{-- Bookmark row --}}
                        <template x-if="row.kind === 'bookmark'">
                            <button type="button" @click="openBookmark(row)" class="flex min-w-0 flex-1 items-center gap-3 text-left">
                                <img x-show="row.favicon" :src="row.favicon" alt="" class="h-4 w-4 shrink-0 rounded-sm">
                                <span x-show="! row.favicon"><x-icon name="globe" class="h-4 w-4 shrink-0 text-gray-400" /></span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-gray-900" x-text="row.title || row.url"></span>
                                    <span class="block truncate text-xs text-gray-400" x-text="row.url"></span>
                                </span>
                                <span class="ml-2 hidden flex-wrap gap-1 sm:flex" @click.stop>
                                    <template x-for="tag in (row.tags ?? [])" :key="tag">
                                        <button type="button" @click="activeTag = tag" class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 hover:bg-gray-200" x-text="tag"></button>
                                    </template>
                                </span>
                            </button>
                        </template>

                        {{-- Favorite star (bookmarks only) --}}
                        <template x-if="row.kind === 'bookmark'">
                            <button type="button" @click.stop="toggleFavorite(row)" :title="row.favorite ? @js(__('bookmarks.unfavorite')) : @js(__('bookmarks.favorite'))"
                                class="shrink-0 rounded p-1 hover:bg-gray-100" :class="row.favorite ? 'text-gray-700' : 'text-gray-300'">
                                <x-icon name="heart-solid" x-show="row.favorite" x-cloak />
                                <x-icon name="heart" x-show="! row.favorite" />
                            </button>
                        </template>

                        {{-- Row menu --}}
                        <div class="relative shrink-0" @click.stop>
                            <button type="button" @click="menu = ! menu" @keydown.escape="menu = false" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><x-icon name="ellipsis" /></button>
                            <div x-show="menu" x-cloak @click.outside="menu = false" class="absolute right-0 z-20 mt-1 w-48 rounded-md border border-gray-200 bg-white py-1 text-left text-sm shadow-lg">
                                <template x-if="row.kind === 'folder'">
                                    <button type="button" @click="deleteFolder(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 hover:bg-gray-50"><x-icon name="trash" />{{ __('bookmarks.delete_forever') }}</button>
                                </template>
                                <template x-if="row.kind === 'bookmark' && view === 'active'">
                                    <span>
                                        <button type="button" @click="openBookmark(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="link" />{{ __('bookmarks.open') }}</button>
                                        <button type="button" @click="openEdit(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="pencil" />{{ __('bookmarks.edit') }}</button>
                                        <button type="button" @click="openTags(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="tag" />{{ __('bookmarks.edit_tags') }}</button>
                                        <button type="button" @click="openMove(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="arrows-right-left" />{{ __('bookmarks.move') }}</button>
                                        <button type="button" @click="toTrash(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 hover:bg-gray-50"><x-icon name="trash" />{{ __('bookmarks.to_trash') }}</button>
                                    </span>
                                </template>
                                <template x-if="row.kind === 'bookmark' && view === 'trash'">
                                    <span>
                                        <button type="button" @click="restore(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="arrow-uturn-left" />{{ __('bookmarks.restore') }}</button>
                                        <button type="button" @click="destroyForever(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 hover:bg-gray-50"><x-icon name="trash" />{{ __('bookmarks.delete_forever') }}</button>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
      </div>
    </template>

    {{-- Add / edit modal --}}
    <template x-teleport="body">
        <div x-show="dialogOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="dialogOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="dialogOpen = false"></div>
            <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900" x-text="editingId ? @js(__('bookmarks.edit_title')) : @js(__('bookmarks.add_title'))"></h3>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('bookmarks.url') }}</label>
                        <div class="mt-1 flex gap-2">
                            <input type="url" x-model="form.url" placeholder="https://…" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            <button type="button" @click="fetchMeta()" :disabled="fetching" class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                x-text="fetching ? @js(__('bookmarks.fetching')) : @js(__('bookmarks.fetch'))"></button>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">{{ __('bookmarks.fetch_hint') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('bookmarks.field_title') }}</label>
                        <input type="text" x-model="form.title" placeholder="{{ __('bookmarks.title_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('bookmarks.description') }}</label>
                        <textarea x-model="form.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('bookmarks.tags') }}</label>
                            <input type="text" x-model="form.tags" list="bookmark-tags" placeholder="tag1, tag2" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            <datalist id="bookmark-tags"><template x-for="tag in allTags" :key="tag"><option :value="tag"></option></template></datalist>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('bookmarks.field_folder') }}</label>
                            <select x-model="form.folder" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <option value="">{{ __('bookmarks.root_folder') }}</option>
                                <template x-for="opt in moveOptions" :key="opt.id"><option :value="opt.id" x-text="opt.label"></option></template>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" x-model="form.favorite" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                        {{ __('bookmarks.field_favorite') }}
                    </label>
                    <p x-show="error" x-cloak class="text-xs text-red-600" x-text="error"></p>
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="dialogOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="saveBookmark()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('bookmarks.save') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Tags modal --}}
    <template x-teleport="body">
        <div x-show="tagsOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="tagsOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="tagsOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('bookmarks.edit_tags') }}</h3>
                <input type="text" x-model="tagsValue" list="bookmark-tags" placeholder="tag1, tag2" class="mt-4 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="tagsOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyTags()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('bookmarks.save') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Move modal --}}
    <template x-teleport="body">
        <div x-show="moveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="moveOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="moveOpen = false"></div>
            <div class="relative flex max-h-[80vh] w-full max-w-md flex-col rounded-lg bg-white shadow-xl">
                <h3 class="border-b border-gray-100 px-6 py-4 text-base font-semibold text-gray-900">{{ __('bookmarks.move_title') }}</h3>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50">
                        <input type="radio" name="bm_move" value="" x-model="moveTarget" class="border-gray-300 text-gray-800 focus:ring-gray-500">
                        {{ __('bookmarks.root_folder') }}
                    </label>
                    <template x-for="opt in moveOptions" :key="opt.id">
                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50">
                            <input type="radio" name="bm_move" :value="opt.id" x-model="moveTarget" class="border-gray-300 text-gray-800 focus:ring-gray-500">
                            <span x-text="opt.label"></span>
                        </label>
                    </template>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="moveOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyMove()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('bookmarks.move_here') }}</button>
                </div>
            </div>
        </div>
    </template>
  </div>
</x-layouts.app>
