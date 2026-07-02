<x-layouts.app :title="__('notes.title')">
  <div x-data="vaultNotes({
        stale: @js(__('notes.stale')),
        saveFailed: @js(__('notes.save_failed')),
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
            <p x-show="error" x-cloak class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800" x-text="error"></p>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <p x-show="notes.length === 0" class="px-4 py-10 text-center text-sm text-gray-500">{{ __('notes.empty') }}</p>
                <template x-for="note in notes" :key="note.id">
                    <button type="button" @click="open(note.id)"
                        class="block w-full border-b border-gray-100 px-4 py-3 text-left hover:bg-gray-50"
                        :class="currentId === note.id ? 'bg-gray-50' : ''">
                        <span class="flex items-center gap-2">
                            <svg x-show="note.pinned" class="h-3.5 w-3.5 shrink-0 text-gray-500" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 3a1 1 0 011 1v1.586l2.707 2.707a1 1 0 01-.29 1.626L15 12l-.5 6-2.5 3-2.5-3-.5-6-4.417-2.081a1 1 0 01-.29-1.626L7 5.586V4a1 1 0 011-1h8z"/></svg>
                            <span class="truncate text-sm font-medium text-gray-900" x-text="note.title || @js(__('notes.untitled'))"></span>
                        </span>
                        <span class="mt-0.5 block truncate text-xs text-gray-500" x-text="excerpt(note)"></span>
                        <span class="mt-1 flex items-center gap-2">
                            <span class="text-xs text-gray-400" x-text="fmtDate(note.updated)"></span>
                            <template x-for="tag in (note.tags ?? [])" :key="tag">
                                <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700" x-text="tag"></span>
                            </template>
                        </span>
                    </button>
                </template>
            </div>
        </aside>

        {{-- Editor pane --}}
        <section class="min-w-0 flex-1 flex-col rounded-lg border border-gray-200 bg-white shadow-sm"
            :class="mobilePane === 'list' ? 'hidden md:flex' : 'flex'">
            <template x-if="! current">
                <p class="flex h-full items-center justify-center p-10 text-center text-sm text-gray-500">{{ __('notes.no_selection') }}</p>
            </template>
            <template x-if="current">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 p-3">
                        <button type="button" @click="closeNote()" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50 md:hidden">‹ {{ __('notes.back') }}</button>
                        <input type="text" x-model="current.title" @input="markDirty()" placeholder="{{ __('notes.title_placeholder') }}"
                            class="min-w-0 flex-1 rounded-md border-gray-300 text-sm font-semibold shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="text-xs text-gray-400"
                                x-text="saveState === 'saving' ? @js(__('notes.saving')) : (saveState === 'saved' ? @js(__('notes.saved')) : (saveState === 'dirty' ? '●' : ''))"></span>
                            <button type="button" @click="togglePreview()"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                x-text="previewing ? @js(__('notes.edit')) : @js(__('notes.preview'))"></button>
                            <button type="button" @click="toTrash(current)" title="{{ __('notes.to_trash') }}"
                                class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">🗑</button>
                        </div>
                    </div>
                    <div class="min-h-0 flex-1 overflow-auto">
                        <div x-show="! previewing" x-ref="noteEditor" class="h-full"></div>
                        <div x-show="previewing" x-cloak class="markdown-body p-6" x-html="previewHtml"></div>
                    </div>
                </div>
            </template>
        </section>
      </div>
    </template>
  </div>
</x-layouts.app>
