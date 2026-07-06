                <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2 text-sm shadow-sm">
                    <button type="button" @click="view = 'all'; activeTag = ''" class="block w-full rounded px-3 py-1.5 text-left" :class="view === 'all' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'">{{ __('todos.all') }}</button>
                    <button type="button" @click="view = 'marked'; activeTag = ''" class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-left" :class="view === 'marked' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"><x-icon name="heart" class="h-4 w-4" />{{ __('todos.marked') }}</button>
                    <button type="button" @click="view = 'trash'; activeTag = ''" class="flex w-full items-center justify-between rounded px-3 py-1.5 text-left" :class="view === 'trash' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'">
                        <span class="flex items-center gap-2"><x-icon name="trash" class="h-4 w-4" />{{ __('todos.trash') }}</span>
                        <span x-show="trashCount" class="text-xs text-gray-400 dark:text-gray-500" x-text="trashCount"></span>
                    </button>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2 text-sm shadow-sm">
                    <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('todos.lists') }}</p>
                    <template x-for="l in lists" :key="l.id">
                        <div class="group flex items-center justify-between rounded px-3 py-1.5" :class="view === l.id ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-50 dark:hover:bg-gray-800'">
                            <button type="button" @click="view = l.id; activeTag = ''" class="min-w-0 flex-1 truncate text-left" :class="view === l.id ? 'font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300'" x-text="l.name"></button>
                            <span class="flex shrink-0 items-center gap-1 opacity-100 md:opacity-0 md:group-hover:opacity-100">
                                <button type="button" @click="renameList(l)" title="{{ __('todos.rename_list') }}" class="rounded p-0.5 text-gray-400 hover:text-gray-700"><x-icon name="pencil" class="h-3.5 w-3.5" /></button>
                                <button type="button" @click="deleteList(l)" title="{{ __('todos.delete_list') }}" class="rounded p-0.5 text-gray-400 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                            </span>
                        </div>
                    </template>
                    <form class="mt-1 flex items-center gap-1 px-1" @submit.prevent="addList()">
                        <input type="text" x-model="newListName" placeholder="{{ __('todos.new_list_placeholder') }}" class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <button type="submit" title="{{ __('todos.add_list') }}" class="shrink-0 rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-50"><x-icon name="plus" class="h-4 w-4" /></button>
                    </form>
                </div>

                <div x-show="allTags.length" class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2 text-sm shadow-sm">
                    <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('todos.tags') }}</p>
                    <div class="flex flex-wrap gap-1 px-2 py-1">
                        <template x-for="t in allTags" :key="t">
                            <button type="button" @click="activeTag = (activeTag === t ? '' : t)" class="rounded px-2 py-0.5 text-xs" :class="activeTag === t ? 'bg-gray-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200'" x-text="t"></button>
                        </template>
                    </div>
                </div>
