<x-layouts.app :title="__('todos.heading')">
  <div x-data="todos({
        saveFailed: @js(__('todos.save_failed')),
        renameList: @js(__('todos.rename_list')),
        deleteListConfirm: @js(__('todos.delete_list_confirm')),
        deleteConfirm: @js(__('todos.delete_confirm')),
        emptyTrashConfirm: @js(__('todos.empty_trash_confirm')),
     }, @js($lists), @js($todos))">

      <div>
        <x-page-heading :title="__('todos.heading')" :subtitle="__('todos.subheading')">
            <x-slot:actions>
                <x-button variant="primary" icon="plus" @click="newTask()">{{ __('todos.new_task') }}</x-button>
            </x-slot:actions>
        </x-page-heading>

        <x-alert variant="warning" x-show="error" x-cloak class="mt-4" x-text="error" />

        <div class="mt-6 flex flex-col gap-4 md:flex-row" style="min-height: calc(100vh - 18rem);">
            {{-- Sidebar --}}
        <div class="md:hidden">
            <button type="button" @click="$store.nav.toggleSidebar()"
                class="flex min-h-11 w-full items-center gap-2 rounded-xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] px-3 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm">
                <x-icon name="bars-3" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                <span>{{ __('common.sections') }}</span>
            </button>
        </div>
        <aside class="hidden w-full shrink-0 space-y-4 self-start md:block md:w-64">
            @include('todos._sidebar_content')
        </aside>
        <x-sheet side="left" store="sidebarOpen" :title="__('common.sections')">
            <div class="space-y-4">@include('todos._sidebar_content')</div>
        </x-sheet>

            {{-- Main --}}
            <section class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <input type="search" x-model="query" placeholder="{{ __('todos.search') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    <x-button variant="danger" class="shrink-0" x-show="view === 'trash' && trashCount" @click="emptyTrash()">{{ __('todos.empty_trash') }}</x-button>
                </div>

                <ul class="mt-4 space-y-2">
                    <template x-for="t in filteredTasks" :key="t.id">
                        <li class="flex items-start gap-3 rounded-xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-3 shadow-sm">
                            <input type="checkbox" :checked="t.done" @change="toggleDone(t)" class="mt-1 rounded border-gray-300 dark:border-gray-700 text-accent focus:ring-accent">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="priorityClass(t.priority)" :title="t.priority"></span>
                            <div class="min-w-0 flex-1 cursor-pointer" @click="editTask(t)">
                                <p class="truncate text-sm font-medium" :class="t.done ? 'text-gray-400 dark:text-gray-500 line-through' : 'text-gray-900 dark:text-gray-100'" x-text="t.title"></p>
                                <p x-show="t.description" class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="t.description"></p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <span x-show="t.due" class="rounded px-1.5 py-0.5 text-[11px]" :class="isOverdue(t) ? 'bg-red-100 text-red-700 dark:text-red-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400'" x-text="dueLabel(t)"></span>
                                    <template x-for="g in (t.tags ?? [])" :key="g"><span class="rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[11px] text-gray-600 dark:text-gray-400" x-text="g"></span></template>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1" @click.stop>
                                <x-action-menu :aria-label="__('common.actions')">
                                    <x-action-menu-item icon="arrow-uturn-right" x-show="t.url" ::href="t.url" target="_blank" rel="noopener">{{ __('todos.open_link') }}</x-action-menu-item>
                                    <x-action-menu-item icon="heart" @click="toggleMark(t)">{{ __('todos.marked_label') }}</x-action-menu-item>
                                    <x-action-menu-item icon="trash" danger x-show="view !== 'trash'" @click="trashTask(t)">{{ __('todos.delete') }}</x-action-menu-item>
                                    <x-action-menu-item icon="arrow-uturn-left" x-show="view === 'trash'" @click="restoreTask(t)">{{ __('todos.restore') }}</x-action-menu-item>
                                    <x-action-menu-item icon="x-mark" danger x-show="view === 'trash'" @click="deleteForever(t)">{{ __('todos.delete') }}</x-action-menu-item>
                                </x-action-menu>
                            </div>
                        </li>
                    </template>
                </ul>
                <p x-show="! filteredTasks.length" class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('todos.empty') }}</p>
            </section>
        </div>
      </div>

    {{-- Task editor modal --}}
    <template x-teleport="body">
        <div x-show="editorOpen" x-cloak class="fixed inset-0 z-[1050] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeEditor()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeEditor()"></div>
            <div class="relative flex max-h-[92vh] w-full max-w-lg flex-col rounded-2xl bg-white dark:bg-[#1c1c1e] shadow-xl" x-show="editing">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="editing?.id ? @js(__('todos.edit')) : @js(__('todos.new_task'))"></h3>
                    <x-icon-button name="x-mark" tone="gray" size="sm" @click="closeEditor()" aria-label="{{ __('todos.cancel') }}" />
                </div>
                <template x-if="editing">
                <div class="min-h-0 flex-1 space-y-4 overflow-auto p-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('todos.title') }}</label>
                        <input type="text" x-model="editing.title" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('todos.description') }}</label>
                        <textarea x-model="editing.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('todos.url') }}</label>
                        <input type="url" x-model="editing.url" placeholder="https://…" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('todos.priority') }}</label>
                            <select x-model="editing.priority" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                                <option value="low">{{ __('todos.priority_low') }}</option>
                                <option value="normal">{{ __('todos.priority_normal') }}</option>
                                <option value="high">{{ __('todos.priority_high') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('todos.list') }}</label>
                            <select x-model="editing.listId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                                <option :value="null">{{ __('todos.no_list') }}</option>
                                <template x-for="l in lists" :key="l.id"><option :value="l.id" x-text="l.name"></option></template>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('todos.due') }}</label>
                        <input type="datetime-local" x-model="editing.due" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('todos.tags') }}</label>
                        <x-tag-field :placeholder="__('todos.tags_placeholder')" />
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" x-model="editing.marked" class="rounded border-gray-300 dark:border-gray-700 text-accent focus:ring-accent">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('todos.marked_label') }}</span>
                    </label>
                </div>
                </template>
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 dark:border-gray-800 px-5 py-3">
                    <x-button variant="secondary" @click="closeEditor()">{{ __('todos.cancel') }}</x-button>
                    <x-button variant="primary" @click="saveTask()">{{ __('todos.save') }}</x-button>
                </div>
            </div>
        </div>
    </template>
  </div>
</x-layouts.app>
