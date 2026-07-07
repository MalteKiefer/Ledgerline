                <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2 text-sm shadow-sm">
                    <button type="button" @click="view = 'all'; activeTag = ''" class="block w-full rounded px-3 py-1.5 text-left" :class="view === 'all' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'">{{ __('bookmarks.all') }}</button>
                    <button type="button" @click="view = 'favorites'; activeTag = ''" class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-left" :class="view === 'favorites' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"><x-icon name="heart" class="h-4 w-4" />{{ __('bookmarks.favorites') }}</button>
                    <button type="button" @click="view = 'readlater'; activeTag = ''" class="flex w-full items-center justify-between rounded px-3 py-1.5 text-left" :class="view === 'readlater' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'">
                        <span class="flex items-center gap-2"><x-icon name="clock" class="h-4 w-4" />{{ __('bookmarks.read_later') }}</span>
                        <span x-show="readLaterCount" class="text-xs text-gray-400 dark:text-gray-500" x-text="readLaterCount"></span>
                    </button>
                    <button type="button" x-show="deadCount" @click="view = 'dead'; activeTag = ''" class="flex w-full items-center justify-between rounded px-3 py-1.5 text-left" :class="view === 'dead' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'">
                        <span class="flex items-center gap-2"><x-icon name="exclamation-triangle" class="h-4 w-4" />{{ __('bookmarks.dead_links') }}</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500" x-text="deadCount"></span>
                    </button>
                    <button type="button" @click="view = 'trash'; activeTag = ''" class="flex w-full items-center justify-between rounded px-3 py-1.5 text-left" :class="view === 'trash' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'">
                        <span class="flex items-center gap-2"><x-icon name="trash" class="h-4 w-4" />{{ __('bookmarks.trash') }}</span>
                        <span x-show="trashCount" class="text-xs text-gray-400 dark:text-gray-500" x-text="trashCount"></span>
                    </button>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2 text-sm shadow-sm">
                    <div class="flex items-center justify-between px-3 py-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('bookmarks.folders') }}</p>
                        <button type="button" @click="openFolderCreate(null)" title="{{ __('bookmarks.new_folder') }}" class="rounded p-0.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="plus" class="h-4 w-4" /></button>
                    </div>
                    {{-- Root drop target: move an item out of any folder --}}
                    <div @dragover.prevent="if (dragItem) $event.currentTarget.classList.add('ring-1','ring-gray-400')" @dragleave="$event.currentTarget.classList.remove('ring-1','ring-gray-400')"
                        @drop.prevent="$event.currentTarget.classList.remove('ring-1','ring-gray-400'); onFolderDrop(null)"
                        class="mx-1 rounded px-2 py-1 text-xs text-gray-400 dark:text-gray-500">{{ __('bookmarks.no_folder') }}</div>
                    <template x-for="f in folderTree" :key="f.id">
                        <div class="group flex items-center justify-between rounded" :class="view === f.id ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                            :style="'padding-left:' + (f.depth * 12) + 'px'"
                            draggable="true" @dragstart.stop="dragItem = { type: 'folder', id: f.id }" @dragend="dragItem = null"
                            @dragover.prevent="if (dragItem && dragItem.id !== f.id) $event.currentTarget.classList.add('ring-1','ring-gray-400')"
                            @dragleave="$event.currentTarget.classList.remove('ring-1','ring-gray-400')"
                            @drop.prevent="$event.currentTarget.classList.remove('ring-1','ring-gray-400'); onFolderDrop(f.id)">
                            <button type="button" @click="view = f.id; activeTag = ''" class="flex min-w-0 flex-1 items-center gap-1.5 truncate px-3 py-1.5 text-left" :class="view === f.id ? 'font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300'">
                                <svg class="h-3.5 w-3.5 shrink-0" :style="f.color ? ('color:' + f.color) : ''" :class="! f.color && 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="folderIconPath(f.icon)" /></svg>
                                <span class="truncate" x-text="f.name"></span>
                            </button>
                            <span class="flex shrink-0 items-center gap-0.5 pr-2">
                                <button type="button" @click="addSubfolder(f)" title="{{ __('bookmarks.subfolder') }}" class="rounded p-0.5 text-gray-400 dark:text-gray-500 opacity-100 hover:text-gray-700 dark:hover:text-gray-300 md:opacity-0 md:group-hover:opacity-100"><x-icon name="plus" class="h-3.5 w-3.5" /></button>
                                <button type="button" @click="openFolderEdit(f)" title="{{ __('bookmarks.edit_folder') }}" class="rounded p-0.5 text-gray-400 dark:text-gray-500 opacity-100 hover:text-gray-700 dark:hover:text-gray-300 md:opacity-0 md:group-hover:opacity-100"><x-icon name="pencil" class="h-3.5 w-3.5" /></button>
                                <button type="button" @click="deleteFolder(f)" title="{{ __('bookmarks.delete_folder') }}" class="rounded p-0.5 text-gray-400 dark:text-gray-500 opacity-100 hover:text-red-600 md:opacity-0 md:group-hover:opacity-100"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                            </span>
                        </div>
                    </template>
                </div>

                <div x-show="allTags.length" class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2 text-sm shadow-sm">
                    <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('bookmarks.tags') }}</p>
                    <div class="flex flex-wrap gap-1 px-2 py-1">
                        <template x-for="t in allTags" :key="t">
                            <button type="button" @click="activeTag = (activeTag === t ? '' : t)" class="rounded px-2 py-0.5 text-xs" :class="activeTag === t ? 'bg-gray-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200'" x-text="t"></button>
                        </template>
                    </div>
                </div>

                <div class="space-y-1 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2 text-sm shadow-sm">
                    <label class="block cursor-pointer rounded px-3 py-1.5 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800" :class="importing && 'pointer-events-none opacity-60'">
                        {{ __('bookmarks.import') }}
                        <input type="file" accept=".html,text/html" class="hidden" :disabled="importing" @change="importFile($event)">
                    </label>
                    <a href="{{ route('bookmarks.export') }}" class="block rounded px-3 py-1.5 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('bookmarks.export') }}</a>
                    <p x-show="importResult" x-cloak class="px-3 py-1 text-xs text-gray-500 dark:text-gray-400" x-text="importResult"></p>
                </div>
