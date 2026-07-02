<x-layouts.app :title="__('notes.title')">
  <div x-data="vaultNotes({
        stale: @js(__('notes.stale')),
        saveFailed: @js(__('notes.save_failed')),
        shareFailed: @js(__('notes.share_failed')),
     })" @keydown.window.prevent.cmd.s="saveNow()" @keydown.window.prevent.ctrl.s="saveNow()">

    {{-- Vault not set up / locked: only the gate. --}}
    <template x-if="state === 'unconfigured' || state === 'locked'">
        <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            <p class="mt-4 text-sm text-gray-600" x-text="state === 'locked' ? @js(__('notes.locked_notice')) : @js(__('notes.unconfigured_notice'))"></p>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('vault-panel'))"
                class="mt-5 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                x-text="state === 'locked' ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></button>
        </div>
    </template>

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">{{ __('notes.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div class="flex gap-4" style="height: calc(100vh - 14rem);">

        {{-- List pane --}}
        <aside class="flex w-full flex-col rounded-lg border border-gray-200 bg-white shadow-sm md:w-80 md:shrink-0"
            :class="mobilePane === 'editor' ? 'hidden md:flex' : 'flex'">
            <div class="flex items-center gap-2 border-b border-gray-100 p-3">
                <input type="search" x-model="query" placeholder="{{ __('notes.search') }}"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <button type="button" @click="create()" title="{{ __('notes.new_note') }}"
                    class="shrink-0 rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">+</button>
            </div>
            <div class="flex items-center gap-2 border-b border-gray-100 px-3 py-2 text-xs">
                <button type="button" @click="view = 'active'" :class="view === 'active' ? 'font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'">{{ __('notes.active') }}</button>
                <button type="button" @click="view = 'trash'" :class="view === 'trash' ? 'font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'">
                    {{ __('notes.trash') }} (<span x-text="trashCount"></span>)
                </button>
                <button type="button" x-show="view === 'trash' && trashCount" x-cloak @click="emptyTrash()"
                    class="ml-auto text-red-600 hover:text-red-700">{{ __('notes.empty_trash') }}</button>
                <span x-show="activeTag" x-cloak class="ml-auto inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-blue-800">
                    {{ __('notes.filtered_by') }}: <span x-text="activeTag"></span>
                    <button type="button" @click="activeTag = ''" class="text-blue-500 hover:text-blue-700"><x-icon name="x-mark" class="h-3 w-3" /></button>
                </span>
            </div>
            <p x-show="error" x-cloak class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800" x-text="error"></p>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <p x-show="notes.length === 0" class="px-4 py-10 text-center text-sm text-gray-500">{{ __('notes.empty') }}</p>
                <template x-for="note in notes" :key="note.id">
                    <div class="relative cursor-pointer border-b border-gray-100 px-4 py-3 hover:bg-gray-50"
                        x-data="{ menu: false }" @click="view === 'active' ? open(note.id) : null"
                        :class="currentId === note.id ? 'bg-gray-50' : ''">
                        <span class="flex items-center gap-2 pr-6">
                            <svg x-show="note.pinned" class="h-3.5 w-3.5 shrink-0 text-gray-500" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 3a1 1 0 011 1v1.586l2.707 2.707a1 1 0 01-.29 1.626L15 12l-.5 6-2.5 3-2.5-3-.5-6-4.417-2.081a1 1 0 01-.29-1.626L7 5.586V4a1 1 0 011-1h8z"/></svg>
                            <span class="truncate text-sm font-medium text-gray-900" x-text="note.title || @js(__('notes.untitled'))"></span>
                        </span>
                        <span class="mt-0.5 block truncate text-xs text-gray-500" x-text="excerpt(note)"></span>
                        <span class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="text-xs text-gray-400" x-text="fmtDate(note.updated)"></span>
                            <template x-for="tag in (note.tags ?? [])" :key="tag">
                                <button type="button" @click.stop="activeTag = tag"
                                    class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 hover:bg-gray-200" x-text="tag"></button>
                            </template>
                        </span>
                        <span class="absolute right-2 top-2" @click.stop>
                            <button type="button" @click="menu = ! menu" @keydown.escape="menu = false" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><x-icon name="ellipsis" /></button>
                            <span x-show="menu" x-cloak @click.outside="menu = false" class="absolute right-0 z-20 mt-1 block w-44 rounded-md border border-gray-200 bg-white py-1 text-left text-sm shadow-lg">
                                <template x-if="view === 'active'">
                                    <span>
                                        <button type="button" @click="togglePin(note); menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50" x-text="note.pinned ? @js(__('notes.unpin')) : @js(__('notes.pin'))"></button>
                                        <button type="button" @click="openTags(note); menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('notes.edit_tags') }}</button>
                                        <button type="button" @click="toTrash(note); menu = false" class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-gray-50">{{ __('notes.to_trash') }}</button>
                                    </span>
                                </template>
                                <template x-if="view === 'trash'">
                                    <span>
                                        <button type="button" @click="restore(note); menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('notes.restore') }}</button>
                                        <button type="button" @click="destroyForever(note); menu = false" class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-gray-50">{{ __('notes.delete_forever') }}</button>
                                    </span>
                                </template>
                            </span>
                        </span>
                    </div>
                </template>
            </div>
        </aside>

        {{-- Editor pane --}}
        <section class="min-w-0 flex-1 flex-col border border-gray-200 bg-white shadow-sm"
            @keydown.escape.window="fullscreen = false"
            :class="fullscreen ? 'fixed inset-0 z-[60] flex rounded-none' : ('rounded-lg ' + (mobilePane === 'list' ? 'hidden md:flex' : 'flex'))">
            <template x-if="! current">
                <p class="flex h-full items-center justify-center p-10 text-center text-sm text-gray-500">{{ __('notes.no_selection') }}</p>
            </template>
            <template x-if="current">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 p-3">
                        <button type="button" @click="closeNote()" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50 md:hidden"><span class="inline-flex items-center gap-1"><x-icon name="chevron-left" class="h-3.5 w-3.5" />{{ __('notes.back') }}</span></button>
                        <input type="text" x-model="current.title" @input="markDirty()" placeholder="{{ __('notes.title_placeholder') }}"
                            class="min-w-0 flex-1 rounded-md border-gray-300 text-sm font-semibold shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <div class="flex shrink-0 items-center gap-2" x-data="{ menu: false, sub: null }">
                            <span class="text-xs text-gray-400"
                                x-text="saveState === 'saving' ? @js(__('notes.saving')) : (saveState === 'saved' ? @js(__('notes.saved')) : (saveState === 'dirty' ? '●' : ''))"></span>
                            <button type="button" @click="toggleFullscreen()"
                                :title="fullscreen ? @js(__('notes.exit_fullscreen')) : @js(__('notes.fullscreen'))"
                                :aria-label="fullscreen ? @js(__('notes.exit_fullscreen')) : @js(__('notes.fullscreen'))"
                                class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50">
                                <x-icon name="arrows-pointing-out" x-show="! fullscreen" />
                                <x-icon name="arrows-pointing-in" x-show="fullscreen" x-cloak />
                            </button>
                            <div class="relative">
                                <button type="button" @click="menu = ! menu; sub = null" @keydown.escape="menu = false" title="{{ __('notes.menu') }}" aria-label="{{ __('notes.menu') }}"
                                    class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="ellipsis" /></button>
                                <div x-show="menu" x-cloak @click.outside="menu = false; sub = null" class="absolute right-0 z-20 mt-1 w-56 rounded-md border border-gray-200 bg-white py-1 text-left text-sm shadow-lg">
                                    <button type="button" @click="togglePin(current); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon ::name="current.pinned ? 'bookmark-solid' : 'bookmark'" /><span x-text="current.pinned ? @js(__('notes.unpin')) : @js(__('notes.pin'))"></span></button>
                                    <button type="button" @click="openTags(current); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="tag" />{{ __('notes.edit_tags') }}</button>
                                    <button type="button" @click="openShare(); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="share" />{{ __('notes.share') }}</button>
                                    <div class="my-1 border-t border-gray-100"></div>
                                    {{-- View submenu --}}
                                    <div class="relative">
                                        <button type="button" @click="sub = (sub === 'view' ? null : 'view')" class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">
                                            <span class="flex items-center gap-2"><x-icon name="eye" />{{ __('notes.view') }}</span><x-icon name="chevron-right" class="h-3.5 w-3.5" />
                                        </button>
                                        <div x-show="sub === 'view'" x-cloak class="absolute right-full top-0 mr-1 w-44 rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                            <button type="button" @click="setMode('edit'); menu = false; sub = null" class="flex w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-gray-50" :class="mode === 'edit' ? 'text-gray-900 font-medium' : 'text-gray-700'"><x-icon name="pencil" />{{ __('notes.mode_edit') }}</button>
                                            <button type="button" @click="setMode('split'); menu = false; sub = null" class="hidden w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-gray-50 md:flex" :class="mode === 'split' ? 'text-gray-900 font-medium' : 'text-gray-700'"><x-icon name="view-columns" />{{ __('notes.mode_split') }}</button>
                                            <button type="button" @click="setMode('preview'); menu = false; sub = null" class="flex w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-gray-50" :class="mode === 'preview' ? 'text-gray-900 font-medium' : 'text-gray-700'"><x-icon name="eye" />{{ __('notes.mode_preview') }}</button>
                                        </div>
                                    </div>
                                    {{-- Export submenu --}}
                                    <div class="relative">
                                        <button type="button" @click="sub = (sub === 'export' ? null : 'export')" class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">
                                            <span class="flex items-center gap-2"><x-icon name="document-arrow-down" />{{ __('notes.export') }}</span><x-icon name="chevron-right" class="h-3.5 w-3.5" />
                                        </button>
                                        <div x-show="sub === 'export'" x-cloak class="absolute right-full top-0 mr-1 w-52 rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                            <button type="button" @click="exportMarkdown(); menu = false; sub = null" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="arrow-down-tray" />{{ __('notes.export_markdown') }}</button>
                                            <button type="button" @click="exportPdf(); menu = false; sub = null" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="document-arrow-down" />{{ __('notes.export_pdf') }}</button>
                                        </div>
                                    </div>
                                    <div class="my-1 border-t border-gray-100"></div>
                                    <button type="button" @click="toTrash(current); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 hover:bg-gray-50"><x-icon name="trash" />{{ __('notes.to_trash') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex min-h-0 flex-1">
                        <div x-show="mode !== 'preview'" x-ref="noteEditor" class="h-full min-w-0 overflow-hidden" :class="mode === 'split' ? 'w-1/2 border-r border-gray-200' : 'w-full'"></div>
                        <div x-show="mode !== 'edit'" x-cloak x-ref="notePreview" @click="togglePreviewTask($event)" class="markdown-body h-full min-w-0 overflow-auto p-6" :class="mode === 'split' ? 'w-1/2' : 'w-full'" x-html="previewHtml"></div>
                    </div>
                </div>
            </template>
        </section>
      </div>
    </template>
    {{-- Tags modal --}}
    <template x-teleport="body">
        <div x-show="tagsOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="tagsOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="tagsOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('notes.edit_tags') }}</h3>
                <input type="text" x-model="tagsValue" list="note-tags" placeholder="tag1, tag2"
                    class="mt-4 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <datalist id="note-tags">
                    <template x-for="tag in allTags" :key="tag"><option :value="tag"></option></template>
                </datalist>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="tagsOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyTags()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                </div>
            </div>
        </div>
    </template>
    {{-- Share modal --}}
    <template x-teleport="body">
        <div x-show="shareDialog" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="shareDialog = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="shareDialog = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('notes.share_title') }}</h3>
                <p class="mt-1 text-xs text-gray-500">{{ __('notes.share_intro') }}</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('notes.share_expiry') }}</label>
                        <select x-model="shareExpiry" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            <option value="3600">{{ __('notes.share_expiry_1h') }}</option>
                            <option value="86400">{{ __('notes.share_expiry_24h') }}</option>
                            <option value="604800">{{ __('notes.share_expiry_7d') }}</option>
                            <option value="2592000">{{ __('notes.share_expiry_30d') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('notes.share_password') }}</label>
                        <input type="password" x-model="sharePassword" autocomplete="new-password"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('notes.share_max_views') }}</label>
                        <input type="number" min="1" x-model="shareMaxViews"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <p x-show="! sharePassword" x-cloak class="text-xs text-amber-700">{{ __('notes.share_no_password_hint') }}</p>
                    <p x-show="shareError" x-cloak class="text-xs text-red-600" x-text="shareError"></p>
                </div>

                {{-- Generated link --}}
                <div x-show="shareLink" x-cloak class="mt-4">
                    <label class="block text-xs font-medium text-gray-700">{{ __('notes.share_your_link') }}</label>
                    <div class="mt-1 flex gap-2">
                        <input type="text" :value="shareLink" readonly @focus="$event.target.select()"
                            class="min-w-0 flex-1 rounded-md border-gray-300 bg-gray-50 text-xs shadow-sm">
                        <button type="button" @click="copyShareLink()" class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            x-text="shareCopied ? @js(__('notes.share_copied')) : @js(__('notes.share_copy'))"></button>
                    </div>
                </div>

                {{-- Active shares for this note --}}
                <div class="mt-5 border-t border-gray-100 pt-4">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('notes.share_active') }}</h4>
                    <p x-show="activeShares.length === 0" class="mt-2 text-xs text-gray-500">{{ __('notes.share_none') }}</p>
                    <ul class="mt-2 space-y-2">
                        <template x-for="s in activeShares" :key="s.id">
                            <li class="flex items-center justify-between gap-2 rounded-md border border-gray-100 px-3 py-2 text-xs">
                                <span class="min-w-0">
                                    <span class="block text-gray-700">{{ __('notes.share_expires') }}: <span x-text="fmtDateTime(s.expires)"></span></span>
                                    <span class="text-gray-400">
                                        <span x-show="s.hasPassword">{{ __('notes.share_password_badge') }} · </span>
                                        <span x-show="s.maxViews">{{ __('notes.share_views') }}: <span x-text="s.maxViews"></span></span>
                                    </span>
                                </span>
                                <button type="button" @click="revokeShare(s)" class="shrink-0 text-red-600 hover:text-red-700">{{ __('notes.share_revoke') }}</button>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="shareDialog = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('notes.done') }}</button>
                    <button type="button" @click="createShare()" :disabled="shareBusy" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('notes.share_create') }}</button>
                </div>
            </div>
        </div>
    </template>
  </div>
</x-layouts.app>
