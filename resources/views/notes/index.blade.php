<x-layouts.app :title="__('notes.title')">
  <div x-data="notes({
        saveFailed: @js(__('notes.save_failed')),
        shareFailed: @js(__('notes.share_failed')),
        deleteConfirm: @js(__('notes.delete_confirm')),
        emptyTrashConfirm: @js(__('notes.empty_trash_confirm')),
     })">

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">{{ __('notes.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div class="flex gap-4" style="height: calc(100vh - 10rem);">
        {{-- List pane --}}
        <aside class="flex w-full flex-col rounded-lg border border-gray-200 bg-white shadow-sm md:w-80 md:shrink-0">
            <div class="flex items-center gap-2 border-b border-gray-100 p-3">
                <input type="search" x-model="query" placeholder="{{ __('notes.search') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <x-button variant="primary" icon="plus" class="shrink-0 !gap-0" title="{{ __('notes.new_note') }}" @click="newNote()"></x-button>
            </div>
            <div class="flex items-center gap-3 border-b border-gray-100 px-3 py-2 text-xs">
                <button type="button" @click="view = 'active'" :class="view === 'active' ? 'font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'">{{ __('notes.active') }}</button>
                <button type="button" @click="view = 'trash'" :class="view === 'trash' ? 'font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'">{{ __('notes.trash') }} (<span x-text="trashCount"></span>)</button>
                <button type="button" x-show="view === 'trash' && trashCount" @click="emptyTrash()" class="ml-auto text-red-600 hover:text-red-700">{{ __('notes.empty_trash') }}</button>
            </div>
            <div x-show="allTags.length" class="flex flex-wrap gap-1 border-b border-gray-100 p-2">
                <template x-for="t in allTags" :key="t">
                    <button type="button" @click="activeTag = (activeTag === t ? '' : t)" class="rounded px-2 py-0.5 text-xs" :class="activeTag === t ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'" x-text="t"></button>
                </template>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <template x-for="n in filtered" :key="n.id">
                    <button type="button" @click="open(n)" class="block w-full border-b border-gray-50 px-4 py-3 text-left hover:bg-gray-50" :class="currentId === n.id ? 'bg-gray-50' : ''">
                        <span class="flex items-center gap-2">
                            <x-icon name="bookmark-solid" class="h-3.5 w-3.5 shrink-0 text-gray-500" x-show="n.pinned" x-cloak />
                            <span class="truncate text-sm font-medium text-gray-900" x-text="n.title || @js(__('notes.untitled'))"></span>
                        </span>
                        <span class="mt-0.5 block truncate text-xs text-gray-500" x-text="excerpt(n)"></span>
                    </button>
                </template>
                <p x-show="! filtered.length" class="px-4 py-8 text-center text-sm text-gray-500">{{ __('notes.empty') }}</p>
            </div>
        </aside>

        {{-- Editor pane --}}
        <section class="min-w-0 flex-1">
            <template x-if="! current">
                <div class="flex h-full items-center justify-center rounded-lg border border-dashed border-gray-300 text-sm text-gray-400">{{ __('notes.pick_note') }}</div>
            </template>
            <template x-if="current">
              <div class="flex h-full flex-col gap-3 lg:flex-row">
                <div class="flex min-w-0 flex-1 flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-2">
                        <input type="text" x-model="current.title" @input.debounce.800ms="save()" placeholder="{{ __('notes.title_placeholder') }}" class="w-full border-0 border-b border-gray-100 px-0 text-lg font-semibold text-gray-900 focus:border-gray-400 focus:ring-0">
                        <button type="button" @click="togglePin(current)" :title="current.pinned ? @js(__('notes.unpin')) : @js(__('notes.pin'))" class="rounded p-1" :class="current.pinned ? 'text-gray-800' : 'text-gray-400 hover:text-gray-600'"><x-icon name="bookmark" class="h-4 w-4" /></button>
                        <button type="button" @click="shareOpen = ! shareOpen" title="{{ __('notes.share') }}" class="rounded p-1 text-gray-400 hover:text-gray-700"><x-icon name="share" class="h-4 w-4" /></button>
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

                    {{-- Share panel --}}
                    <div x-show="shareOpen" x-cloak class="mt-3 rounded-md border border-gray-200 p-3" x-data="{ f: { expires_in: 86400, max_views: null, password: '' } }">
                        <div class="grid grid-cols-2 gap-2">
                            <select x-model.number="f.expires_in" class="rounded-md border-gray-300 text-xs shadow-sm">
                                <option value="3600">{{ __('notes.share_expiry_1h') }}</option>
                                <option value="86400">{{ __('notes.share_expiry_24h') }}</option>
                                <option value="604800">{{ __('notes.share_expiry_7d') }}</option>
                                <option value="2592000">{{ __('notes.share_expiry_30d') }}</option>
                            </select>
                            <input type="number" min="1" x-model="f.max_views" placeholder="{{ __('notes.share_max_views') }}" class="rounded-md border-gray-300 text-xs shadow-sm">
                            <input type="text" x-model="f.password" placeholder="{{ __('notes.share_password') }}" class="col-span-2 rounded-md border-gray-300 text-xs shadow-sm">
                        </div>
                        <button type="button" @click="createShare(f)" :disabled="shareBusy" class="mt-2 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('notes.share_create') }}</button>
                        <input x-show="shareUrl" x-cloak type="text" readonly :value="shareUrl" x-on:click="$el.select()" class="mt-2 w-full rounded-md border-gray-300 bg-gray-50 text-xs shadow-sm">
                    </div>
                </div>

                {{-- Rendered preview (server-side markdown) --}}
                <div class="min-w-0 flex-1 overflow-y-auto rounded-lg border border-gray-200 bg-white p-4 shadow-sm lg:max-w-md">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('notes.preview') }}</p>
                    <div class="prose prose-sm max-w-none text-gray-800" x-html="previewHtml"></div>
                </div>
              </div>
            </template>
        </section>
      </div>
    </template>
  </div>
</x-layouts.app>
