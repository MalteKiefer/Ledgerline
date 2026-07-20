{{-- Shared file/folder row-action menu items. Included by BOTH the grid and the
     list row menus so the two never drift. Runs in the menu's Alpine scope
     (row, menu, and the component methods/stores). --}}
@php $c = 'flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-accent/5'; @endphp
<button type="button" x-show="row.kind === 'file'" @click="download(row); menu = false" class="{{ $c }}"><x-icon name="arrow-down-tray" />{{ __('files.download') }}</button>
<button type="button" @click="openInfo(row); menu = false" class="{{ $c }}"><x-icon name="info" />{{ __('files.info') }}</button>
<button type="button" x-show="view === 'files' || view === 'shared'" @click="openShare(row); menu = false" class="{{ $c }}"><x-icon name="share" /><span x-text="row.share ? '{{ __('files.share_manage') }}' : '{{ __('files.share') }}'"></span></button>
<button type="button" @click="startRename(row); menu = false" class="{{ $c }}"><x-icon name="pencil" />{{ __('files.rename') }}</button>
<button type="button" @click="openMove(row); menu = false" class="{{ $c }}"><x-icon name="arrows-right-left" />{{ __('files.move') }}</button>
<button type="button" @click="openTags(row); menu = false" class="{{ $c }}"><x-icon name="tag" />{{ __('files.edit_tags') }}</button>
<button type="button" x-show="row.kind === 'file' && _textCapable(row)" @click="indexFile(row); menu = false" class="{{ $c }}"><x-icon name="sparkles" />{{ __('files.make_searchable') }}</button>
<button type="button" x-show="row.kind !== 'folder'" @click="openVersions(row); menu = false" class="{{ $c }}"><x-icon name="arrow-path" />{{ __('files.versions') }}</button>
<button type="button" x-show="isMarkdown(row)" @click="openMigrate(row); menu = false" class="{{ $c }}"><x-icon name="document-text" />{{ __('files.migrate_to_note') }}</button>
<button type="button" x-show="isPdf(row) && $store.paperless.configured" @click="openPaperless(row); menu = false" class="{{ $c }}"><x-icon name="share" />{{ __('paperless.send_to_paperless') }}</button>
<button type="button" @click="confirmDelete(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 dark:text-red-400 hover:bg-accent/5"><x-icon name="trash" />{{ __('common.delete') }}</button>
