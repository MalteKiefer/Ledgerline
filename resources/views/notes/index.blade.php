<x-layouts.app :title="__('notes.title')">
  <div x-data="notes({
        saveFailed: @js(__('notes.save_failed')),
        deleteConfirm: @js(__('notes.delete_confirm')),
        emptyTrashConfirm: @js(__('notes.empty_trash_confirm')),
     })">

    {{-- Zero-knowledge gate: notes decrypt with the vault key. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <template x-if="state === 'locked'">
        <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 text-center">
            <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400"
               x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
            <button type="button" @click="$dispatch('vault-panel')"
                class="mt-5 inline-flex min-h-11 items-center gap-1.5 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                <x-icon name="lock-open" class="h-4 w-4" />
                <span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span>
            </button>
        </div>
    </template>

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-6 text-center text-sm text-red-700 dark:text-red-300">{{ __('notes.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div class="flex h-[calc(100dvh-11rem)] gap-4 md:h-[calc(100vh-10rem)]">
        {{-- List pane (full screen on mobile until a note is opened) --}}
        <aside class="w-full flex-col rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm md:w-80 md:shrink-0"
            :class="current ? 'hidden md:flex' : 'flex'">
            <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 p-3">
                <input type="search" x-model="query" placeholder="{{ __('notes.search') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <x-button variant="primary" icon="plus" class="shrink-0 !gap-0" title="{{ __('notes.new_note') }}" @click="newNote()"></x-button>
            </div>
            <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-800 px-3 py-2 text-xs">
                <button type="button" @click="view = 'active'" :class="view === 'active' ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'">{{ __('notes.active') }}</button>
                <button type="button" @click="view = 'trash'" :class="view === 'trash' ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'">{{ __('notes.trash') }} (<span x-text="trashCount"></span>)</button>
                <button type="button" x-show="view === 'trash' && trashCount" @click="emptyTrash()" class="ml-auto text-red-600 hover:text-red-700">{{ __('notes.empty_trash') }}</button>
            </div>
            <div x-show="allTags.length" class="flex flex-wrap gap-1 border-b border-gray-100 dark:border-gray-800 p-2">
                <template x-for="t in allTags" :key="t">
                    <button type="button" @click="activeTag = (activeTag === t ? '' : t)" class="rounded px-2 py-0.5 text-xs" :class="activeTag === t ? 'bg-gray-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200'" x-text="t"></button>
                </template>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <template x-for="n in filtered" :key="n.id">
                    <button type="button" @click="open(n)" class="block w-full border-b border-gray-50 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800" :class="currentId === n.id ? 'bg-gray-50 dark:bg-gray-800' : ''">
                        <span class="flex items-center gap-2">
                            <x-icon name="bookmark-solid" class="h-3.5 w-3.5 shrink-0 text-gray-500 dark:text-gray-400" x-show="n.pinned" x-cloak />
                            <span class="truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="n.title || @js(__('notes.untitled'))"></span>
                        </span>
                        <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400" x-text="excerpt(n)"></span>
                    </button>
                </template>
                <p x-show="! filtered.length" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('notes.empty') }}</p>
            </div>
        </aside>

        {{-- Editor pane (shown alone on mobile once a note is open) --}}
        <section class="min-w-0 flex-1" :class="current ? '' : 'hidden md:block'">
            <template x-if="! current">
                <div class="flex h-full items-center justify-center rounded-lg border border-dashed border-gray-300 dark:border-gray-700 text-sm text-gray-400 dark:text-gray-500">{{ __('notes.pick_note') }}</div>
            </template>
            <template x-if="current">
              <div class="flex h-full flex-col gap-3 lg:flex-row">
                <div class="flex min-w-0 flex-1 flex-col rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm">
                    <button type="button" @click="current = null; currentId = null"
                        class="mb-2 inline-flex min-h-11 w-max items-center gap-1 text-sm text-gray-600 dark:text-gray-400 md:hidden">
                        <x-icon name="chevron-left" class="h-4 w-4" />{{ __('common.back') }}
                    </button>
                    <div class="flex items-center gap-2">
                        <input type="text" x-model="current.title" @input.debounce.800ms="save()" placeholder="{{ __('notes.title_placeholder') }}" class="w-full border-0 border-b border-gray-100 px-0 text-lg font-semibold text-gray-900 focus:border-gray-400 focus:ring-0">
                        <button type="button" @click="togglePin(current)" :title="current.pinned ? @js(__('notes.unpin')) : @js(__('notes.pin'))" class="rounded p-1" :class="current.pinned ? 'text-gray-800 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500 hover:text-gray-600'"><x-icon name="bookmark" class="h-4 w-4" /></button>
                        <template x-if="view === 'trash'">
                            <span class="flex items-center gap-1">
                                <button type="button" @click="restore(current)" title="{{ __('notes.restore') }}" class="rounded p-1 text-gray-400 hover:text-gray-700"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
                                <button type="button" @click="remove(current)" title="{{ __('notes.delete_forever') }}" class="rounded p-1 text-gray-400 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
                            </span>
                        </template>
                        <button type="button" x-show="view !== 'trash'" @click="trash(current)" title="{{ __('notes.to_trash') }}" class="rounded p-1 text-gray-400 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                    <input type="text" x-model="tagsValue" @change="save()" placeholder="{{ __('notes.tags_placeholder') }}" class="mt-2 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <textarea x-model="current.content" @input="save(); schedulePreview()" placeholder="{{ __('notes.content') }}" class="mt-3 min-h-0 w-full flex-1 rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></textarea>
                </div>

                {{-- Rendered preview (client-side markdown, DOMPurify-sanitised) --}}
                <div class="min-w-0 flex-1 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm lg:max-w-md">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('notes.preview') }}</p>
                    <div class="prose prose-sm max-w-none text-gray-800 dark:text-gray-200" x-html="previewHtml"></div>
                </div>
              </div>
            </template>
        </section>
      </div>
    </template>
  </div>
</x-layouts.app>
