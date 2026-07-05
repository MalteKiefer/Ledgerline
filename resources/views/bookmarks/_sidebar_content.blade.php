                <div class="rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-sm">
                    <button type="button" @click="view = 'all'; activeTag = ''" class="block w-full rounded px-3 py-1.5 text-left" :class="view === 'all' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50'">{{ __('bookmarks.all') }}</button>
                    <button type="button" @click="view = 'favorites'; activeTag = ''" class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-left" :class="view === 'favorites' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50'"><x-icon name="heart" class="h-4 w-4" />{{ __('bookmarks.favorites') }}</button>
                    <button type="button" @click="view = 'trash'; activeTag = ''" class="flex w-full items-center justify-between rounded px-3 py-1.5 text-left" :class="view === 'trash' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50'">
                        <span class="flex items-center gap-2"><x-icon name="trash" class="h-4 w-4" />{{ __('bookmarks.trash') }}</span>
                        <span x-show="trashCount" class="text-xs text-gray-400" x-text="trashCount"></span>
                    </button>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-sm">
                    <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('bookmarks.folders') }}</p>
                    <template x-for="f in folders" :key="f.id">
                        <div class="group flex items-center justify-between rounded px-3 py-1.5" :class="view === f.id ? 'bg-gray-100' : 'hover:bg-gray-50'">
                            <button type="button" @click="view = f.id; activeTag = ''" class="min-w-0 flex-1 truncate text-left" :class="view === f.id ? 'font-medium text-gray-900' : 'text-gray-700'" x-text="f.name"></button>
                            <button type="button" @click="deleteFolder(f)" title="{{ __('bookmarks.delete_folder') }}" class="rounded p-0.5 text-gray-400 opacity-100 hover:text-red-600 md:opacity-0 md:group-hover:opacity-100"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </div>
                    </template>
                    <form class="mt-1 flex items-center gap-1 px-1" @submit.prevent="addFolder()">
                        <input type="text" x-model="newFolderName" placeholder="{{ __('bookmarks.new_folder') }}" class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <button type="submit" title="{{ __('bookmarks.new_folder') }}" class="shrink-0 rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-50"><x-icon name="plus" class="h-4 w-4" /></button>
                    </form>
                </div>

                <div x-show="allTags.length" class="rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-sm">
                    <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('bookmarks.tags') }}</p>
                    <div class="flex flex-wrap gap-1 px-2 py-1">
                        <template x-for="t in allTags" :key="t">
                            <button type="button" @click="activeTag = (activeTag === t ? '' : t)" class="rounded px-2 py-0.5 text-xs" :class="activeTag === t ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" x-text="t"></button>
                        </template>
                    </div>
                </div>
