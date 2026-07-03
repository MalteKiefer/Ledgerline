<x-layouts.app :title="__('todos.heading')">
  <div x-data="vaultTodos({
        stale: @js(__('todos.stale')),
        saveFailed: @js(__('todos.save_failed')),
        renameList: @js(__('todos.rename_list')),
        deleteListConfirm: @js(__('todos.delete_list_confirm')),
        deleteConfirm: @js(__('todos.delete_confirm')),
        emptyTrashConfirm: @js(__('todos.empty_trash_confirm')),
     })">

    {{-- Vault gate --}}
    <template x-if="state === 'unconfigured' || state === 'locked'">
        <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
            <x-icon name="lock-closed" class="mx-auto h-10 w-10 text-gray-400" />
            <p class="mt-4 text-sm text-gray-600" x-text="state === 'locked' ? @js(__('todos.locked_notice')) : @js(__('todos.unconfigured_notice'))"></p>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('vault-panel'))"
                class="mt-5 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                x-text="state === 'locked' ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></button>
        </div>
    </template>

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">{{ __('todos.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ __('todos.heading') }}</h1>
                <p class="mt-1 text-sm text-gray-600">{{ __('todos.subheading') }}</p>
            </div>
            <button type="button" @click="newTask()" class="shrink-0 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">+ {{ __('todos.new_task') }}</button>
        </div>

        <p x-show="error" x-cloak class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800" x-text="error"></p>

        <div class="mt-6 flex flex-col gap-4 md:flex-row" style="min-height: calc(100vh - 18rem);">
            {{-- Sidebar: filters, lists, tags --}}
            <aside class="w-full shrink-0 space-y-4 md:w-64">
                <div class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm text-sm">
                    <button type="button" @click="view = 'all'; activeTag = ''" class="flex w-full items-center justify-between rounded px-3 py-1.5 text-left" :class="view === 'all' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50'">{{ __('todos.all') }}</button>
                    <button type="button" @click="view = 'marked'; activeTag = ''" class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-left" :class="view === 'marked' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50'"><x-icon name="heart" class="h-4 w-4" />{{ __('todos.marked') }}</button>
                    <button type="button" @click="view = 'trash'; activeTag = ''" class="flex w-full items-center justify-between rounded px-3 py-1.5 text-left" :class="view === 'trash' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50'">
                        <span class="flex items-center gap-2"><x-icon name="trash" class="h-4 w-4" />{{ __('todos.trash') }}</span>
                        <span x-show="trashCount" class="text-xs text-gray-400" x-text="trashCount"></span>
                    </button>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm text-sm">
                    <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('todos.lists') }}</p>
                    <template x-for="l in lists" :key="l.id">
                        <div class="group flex items-center justify-between rounded px-3 py-1.5" :class="view === l.id ? 'bg-gray-100' : 'hover:bg-gray-50'">
                            <button type="button" @click="view = l.id; activeTag = ''" class="min-w-0 flex-1 truncate text-left" :class="view === l.id ? 'font-medium text-gray-900' : 'text-gray-700'" x-text="l.name"></button>
                            <span class="flex shrink-0 items-center gap-1 opacity-0 group-hover:opacity-100">
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

                <div x-show="allTags.length" class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm text-sm">
                    <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('todos.tags') }}</p>
                    <div class="flex flex-wrap gap-1 px-2 py-1">
                        <template x-for="t in allTags" :key="t">
                            <button type="button" @click="activeTag = (activeTag === t ? '' : t)" class="rounded px-2 py-0.5 text-xs" :class="activeTag === t ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" x-text="t"></button>
                        </template>
                    </div>
                </div>
            </aside>

            {{-- Main: search + task list --}}
            <section class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <input type="search" x-model="query" placeholder="{{ __('todos.search') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <button type="button" x-show="view === 'trash' && trashCount" @click="emptyTrash()" class="shrink-0 rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('todos.empty_trash') }}</button>
                </div>

                <ul class="mt-4 space-y-2">
                    <template x-for="t in tasks" :key="t.id">
                        <li class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                            <input type="checkbox" :checked="t.done" @change="toggleDone(t)" class="mt-1 rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="priorityClass(t.priority)" :title="t.priority"></span>
                            <div class="min-w-0 flex-1 cursor-pointer" @click="editTask(t)">
                                <p class="truncate text-sm font-medium" :class="t.done ? 'text-gray-400 line-through' : 'text-gray-900'" x-text="t.title"></p>
                                <p x-show="t.description" class="truncate text-xs text-gray-500" x-text="t.description"></p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <span x-show="t.due" class="rounded px-1.5 py-0.5 text-[11px]" :class="isOverdue(t) ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'" x-text="dueLabel(t)"></span>
                                    <template x-for="g in (t.tags ?? [])" :key="g"><span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600" x-text="g"></span></template>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <a x-show="t.url" :href="t.url" target="_blank" rel="noopener" @click.stop title="{{ __('todos.open_link') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><x-icon name="arrow-uturn-right" class="h-4 w-4" /></a>
                                <button type="button" @click.stop="toggleMark(t)" :title="'{{ __('todos.marked_label') }}'" class="rounded p-1" :class="t.marked ? 'text-red-500' : 'text-gray-300 hover:text-gray-500'"><x-icon name="heart" class="h-4 w-4" /></button>
                                <button type="button" x-show="view !== 'trash'" @click.stop="trashTask(t)" title="{{ __('todos.delete') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
                                <button type="button" x-show="view === 'trash'" @click.stop="restoreTask(t)" title="{{ __('todos.restore') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
                                <button type="button" x-show="view === 'trash'" @click.stop="deleteForever(t)" title="{{ __('todos.delete') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                            </div>
                        </li>
                    </template>
                </ul>
                <p x-show="! tasks.length" class="mt-10 text-center text-sm text-gray-500">{{ __('todos.empty') }}</p>
            </section>
        </div>
      </div>
    </template>

    {{-- Task editor modal --}}
    <template x-teleport="body">
        <div x-show="editorOpen" x-cloak class="fixed inset-0 z-[1050] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeEditor()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeEditor()"></div>
            <div class="relative flex max-h-[92vh] w-full max-w-lg flex-col rounded-lg bg-white shadow-xl" x-show="editing">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-900" x-text="editing?.id ? @js(__('todos.edit')) : @js(__('todos.new_task'))"></h3>
                    <button type="button" @click="closeEditor()" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('todos.cancel') }}"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>
                <div class="min-h-0 flex-1 space-y-4 overflow-auto p-5" x-show="editing">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('todos.title') }}</label>
                        <input type="text" x-model="editing.title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('todos.description') }}</label>
                        <textarea x-model="editing.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('todos.url') }}</label>
                        <input type="url" x-model="editing.url" placeholder="https://…" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('todos.priority') }}</label>
                            <select x-model="editing.priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                                <option value="low">{{ __('todos.priority_low') }}</option>
                                <option value="normal">{{ __('todos.priority_normal') }}</option>
                                <option value="high">{{ __('todos.priority_high') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('todos.list') }}</label>
                            <select x-model="editing.listId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                                <option :value="null">{{ __('todos.no_list') }}</option>
                                <template x-for="l in lists" :key="l.id"><option :value="l.id" x-text="l.name"></option></template>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('todos.due') }}</label>
                        <input type="datetime-local" x-model="editing.due" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                    </div>
                    {{-- Reminder channels — only meaningful when a due date is set. --}}
                    <div x-show="editing.due" x-cloak>
                        <label class="block text-sm font-medium text-gray-700">{{ __('todos.notify_heading') }}</label>
                        <div class="mt-1 flex flex-wrap gap-3 text-sm text-gray-700">
                            <label class="flex items-center gap-1.5"><input type="checkbox" value="desktop" x-model="editing.reminderChannels" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">{{ __('todos.channel_browser') }}</label>
                            <label class="flex items-center gap-1.5"><input type="checkbox" value="ntfy" x-model="editing.reminderChannels" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">{{ __('todos.channel_ntfy') }}</label>
                            <label class="flex items-center gap-1.5"><input type="checkbox" value="mail" x-model="editing.reminderChannels" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">{{ __('todos.channel_mail') }}</label>
                            <label class="flex items-center gap-1.5"><input type="checkbox" value="webhook" x-model="editing.reminderChannels" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">{{ __('todos.channel_webhook') }}</label>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ __('todos.notify_hint') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('todos.tags') }}</label>
                        <input type="text" x-model="tagsValue" placeholder="{{ __('todos.tags_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" x-model="editing.marked" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                        <span class="text-sm text-gray-700">{{ __('todos.marked_label') }}</span>
                    </label>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-5 py-3">
                    <button type="button" @click="closeEditor()" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('todos.cancel') }}</button>
                    <button type="button" @click="saveTask()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('todos.save') }}</button>
                </div>
            </div>
        </div>
    </template>
  </div>
</x-layouts.app>
