<x-layouts.app :title="__('messages.nav.files')">
  @php
      $typeLabels = collect(\App\Enums\FileType::cases())
          ->mapWithKeys(fn (\App\Enums\FileType $c): array => [$c->value => $c->label()]);
  @endphp
  <div x-data="vaultFiles({
        blobBase: '{{ url('/vault/blobs') }}',
        token: '{{ csrf_token() }}',
     }, {
        types: @js($typeLabels),
        stale: @js(__('files.vault_stale')),
        saveFailed: @js(__('files.save_failed')),
        uploadFailed: @js(__('files.upload_failed')),
        downloadFailed: @js(__('files.download_failed')),
     })">

    {{-- Whole-window drop zone (folders with subfolders supported) --}}
    <div x-show="dragging && state === 'ready'" x-cloak @drop.prevent="drop($event)" @dragover.prevent
        class="fixed inset-0 z-[900] flex items-center justify-center bg-gray-900/50 p-8">
        <div class="rounded-2xl border-4 border-dashed border-white/80 px-16 py-24 text-center text-lg font-medium text-white">{{ __('files.drop_hint') }}</div>
    </div>

    {{-- Vault not set up / locked: no browser, only the gate. The server holds
         no readable file metadata, so there is nothing else to show. --}}
    <template x-if="state === 'unconfigured' || state === 'locked'">
        <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            <p class="mt-4 text-sm text-gray-600" x-text="state === 'locked' ? @js(__('files.locked_notice')) : @js(__('files.unconfigured_notice'))"></p>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('vault-panel'))"
                class="mt-5 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                x-text="state === 'locked' ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></button>
        </div>
    </template>

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">{{ __('files.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div>
        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <nav class="text-sm text-gray-500">
                    <button type="button" @click="cwd = null" class="hover:underline">{{ __('files.all_files') }}</button>
                    <template x-for="crumb in breadcrumb" :key="crumb.id">
                        <span>
                            <span aria-hidden="true">/</span>
                            <button type="button" @click="cwd = crumb.id" class="hover:underline" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900" x-text="currentFolderName ?? @js(__('messages.nav.files'))"></h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                {{-- New folder --}}
                <form class="flex items-center gap-1" @submit.prevent="mkdir($refs.newFolder.value); $refs.newFolder.value = ''">
                    <input type="text" x-ref="newFolder" required placeholder="{{ __('files.new_folder') }}"
                        class="w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">+</button>
                </form>
                {{-- Upload --}}
                <label class="cursor-pointer rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                    {{ __('files.upload') }}
                    <input type="file" multiple class="hidden" @change="upload($event.target.files); $event.target.value = ''">
                </label>
            </div>
        </div>

        {{-- Search (client-side, over the decrypted manifest) --}}
        <div class="mt-6">
            <input type="search" x-model="query" placeholder="{{ __('files.search') }}"
                class="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        </div>

        <p x-show="error" x-cloak class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800" x-text="error"></p>

        {{-- Browser --}}
        <div class="mt-4 overflow-visible rounded-lg border border-gray-200 bg-white shadow-sm">
            <template x-if="rows.length === 0">
                <p class="px-4 py-10 text-center text-sm text-gray-500">{{ __('files.empty_explorer') }}</p>
            </template>
            <table x-show="rows.length > 0" class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3">
                            <button type="button" @click="sortDir = sortDir === 'asc' ? 'desc' : 'asc'" class="uppercase hover:text-gray-700">
                                {{ __('files.col_name') }} <span x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        <th class="hidden px-4 py-3 sm:table-cell">{{ __('files.col_type') }}</th>
                        <th class="hidden px-4 py-3 text-right sm:table-cell">{{ __('files.col_size') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="row in rows" :key="row.kind + row.id">
                        <tr class="cursor-pointer hover:bg-gray-50" x-data="{ menu: false }"
                            @click="if (renaming !== row.id) { row.kind === 'folder' ? cwd = row.id : openFile(row) }">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <span class="flex items-center gap-2" x-show="renaming !== row.id">
                                    <svg x-show="row.kind === 'folder'" class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                                    <svg x-show="row.kind === 'file'" class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                    <span x-text="row.name"></span>
                                </span>
                                <form x-show="renaming === row.id" x-cloak class="flex gap-2" @click.stop @submit.prevent="applyRename(row)">
                                    <input type="text" x-model="renameValue" x-ref="rename"
                                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                    <button type="submit" class="rounded-md bg-gray-800 px-3 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                                    <button type="button" @click="renaming = null" class="text-sm text-gray-500">✕</button>
                                </form>
                            </td>
                            <td class="hidden px-4 py-3 text-gray-600 sm:table-cell" x-text="row.kind === 'folder' ? @js(__('files.folder')) : typeLabel(row)"></td>
                            <td class="hidden px-4 py-3 text-right text-gray-600 sm:table-cell" x-text="row.kind === 'folder' ? '—' : fmtSize(row.size)"></td>
                            <td class="px-4 py-3 text-right" @click.stop>
                                <div class="relative inline-block text-left">
                                    <button type="button" @click="menu = ! menu" @keydown.escape="menu = false" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="{{ __('files.actions') }}">⋯</button>
                                    <div x-show="menu" x-cloak @click.outside="menu = false" class="absolute right-0 z-20 mt-1 w-40 rounded-md border border-gray-200 bg-white py-1 text-left text-sm shadow-lg">
                                        <button type="button" x-show="row.kind === 'file'" @click="download(row); menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.download') }}</button>
                                        <button type="button" @click="startRename(row); menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.rename') }}</button>
                                        <button type="button" @click="openMove(row); menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.move') }}</button>
                                        <button type="button" @click="confirmDelete(row); menu = false" class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-gray-50">{{ __('common.delete') }}</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
      </div>
    </template>

    {{-- Upload progress --}}
    <div x-show="up.active" x-cloak class="fixed bottom-5 right-5 z-[950] w-80 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl">
        <div class="flex items-center justify-between px-4 py-2 text-sm font-medium text-gray-700">
            <span>{{ __('files.uploading') }}</span>
            <span class="text-gray-500"><span x-text="up.done"></span>/<span x-text="up.total"></span></span>
        </div>
        <div class="h-2 bg-gray-100">
            <div class="h-2 bg-gray-800 transition-all" :style="`width: ${up.total ? Math.round(up.done / up.total * 100) : 0}%`"></div>
        </div>
        <p class="px-4 py-2 text-xs text-amber-700">{{ __('files.encrypting_keep_open') }}</p>
    </div>

    {{-- Download progress --}}
    <div x-show="dl.active" x-cloak class="fixed bottom-5 right-5 z-[950] w-80 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-xl">
        {{ __('files.decrypting') }}
    </div>

    {{-- Move modal --}}
    <template x-teleport="body">
        <div x-show="moveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="moveOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="moveOpen = false"></div>
            <div class="relative flex max-h-[80vh] w-full max-w-md flex-col rounded-lg bg-white shadow-xl">
                <h3 class="border-b border-gray-100 px-6 py-4 text-base font-semibold text-gray-900">{{ __('files.move_title') }}</h3>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50">
                        <input type="radio" name="move_target" value="" x-model="moveTarget" class="border-gray-300 text-gray-800 focus:ring-gray-500">
                        {{ __('files.root_folder') }}
                    </label>
                    <template x-for="opt in moveOptions" :key="opt.id">
                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50">
                            <input type="radio" name="move_target" :value="opt.id" x-model="moveTarget" class="border-gray-300 text-gray-800 focus:ring-gray-500">
                            <span x-text="opt.label"></span>
                        </label>
                    </template>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="moveOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyMove()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.move_here') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Viewer / editor: image, PDF or editable text, decrypted in the browser --}}
    <template x-teleport="body">
        <div x-show="viewer.open" x-cloak class="fixed inset-0 z-[1050] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeViewer()">
            <div class="absolute inset-0 bg-gray-900/60" @click="closeViewer()"></div>
            <div class="relative flex max-h-[92vh] w-full max-w-4xl flex-col rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
                    <h3 class="truncate text-base font-semibold text-gray-900" x-text="viewer.row?.name"></h3>
                    <div class="flex shrink-0 items-center gap-3">
                        <button type="button" @click="download(viewer.row)" class="text-sm text-gray-600 hover:text-gray-900">{{ __('files.download') }}</button>
                        <button type="button" @click="closeViewer()" class="text-xl leading-none text-gray-400 hover:text-gray-600" aria-label="{{ __('common.cancel') }}">✕</button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 overflow-auto p-4">
                    <img x-show="viewer.kind === 'image'" x-cloak :src="viewer.src" :alt="viewer.row?.name" class="mx-auto max-h-[75vh] rounded object-contain">
                    <template x-if="viewer.kind === 'pdf'">
                        <object :data="viewer.src" type="application/pdf" class="h-[75vh] w-full rounded"></object>
                    </template>
                    <div x-show="viewer.kind === 'text'" x-cloak>
                        <div class="mb-2 flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-500">{{ __('files.language') }}</label>
                            <select x-model="editorLang" @change="onEditorLanguageChange()"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <option value="">{{ __('files.plain_text') }}</option>
                                <template x-for="name in languageOptions" :key="name">
                                    <option :value="name" x-text="name"></option>
                                </template>
                            </select>
                            <span class="text-xs text-gray-400">{{ __('files.search_hint') }}</span>
                        </div>
                        <div x-ref="viewerEditor" class="overflow-hidden rounded-lg border border-gray-300"></div>
                        <div class="mt-3 flex items-center gap-3">
                            <button type="button" @click="saveText()" :disabled="viewer.saving"
                                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('files.save') }}</button>
                            <span x-show="viewer.saved" x-cloak class="text-sm text-green-600">✓</span>
                        </div>
                    </div>
                    <p x-show="viewer.kind === 'none'" x-cloak class="py-10 text-center text-sm text-gray-500">{{ __('files.encrypted_no_preview') }}</p>
                </div>
            </div>
        </div>
    </template>

    {{-- Delete confirm --}}
    <template x-teleport="body">
        <div x-show="deleteRef" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteRef = null">
            <div class="absolute inset-0 bg-gray-900/40" @click="deleteRef = null"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600">
                    <span x-text="deleteRef?.name"></span> —
                    <span x-text="deleteRef?.kind === 'folder' ? @js(__('files.delete_folder_confirm')) : @js(__('files.delete_file_confirm'))"></span>
                </p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="deleteRef = null" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyDelete()" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('common.delete') }}</button>
                </div>
            </div>
        </div>
    </template>
  </div>
</x-layouts.app>
