{{-- Cross-user plaintext folder sharing (pivot) — a self-contained Alpine island.
     Two iOS-styled panes: "Shared by me" (owner grants + roster management) and
     "Shared with me" (member browse + download + editor upload/rename/delete).
     All URLs are handed in via cfg; templated ids use literal __SHARE__/__FILE__
     placeholders the component string-replaces. This is a PLAIN <div>, so @js()
     in the x-data expression is fine; on the <x-*> components below use :: for
     Alpine binds and plain title="{{ }}" for static labels. --}}
<div x-data="sharedFolders({
        ownerIndex: '{{ route('files.folder-shares.index') }}',
        ownerStore: '{{ route('files.folder-shares.store') }}',
        ownerMember: '{{ url('/files/folder-shares') }}/__SHARE__/members',
        ownerDestroy: '{{ url('/files/folder-shares') }}/__SHARE__',
        memberIndex: '{{ route('shared-with-me.index') }}',
        memberBrowse: '{{ url('/shared-with-me') }}/__SHARE__',
        memberRaw: '{{ url('/shared-with-me') }}/__SHARE__/files/__FILE__/raw',
        memberUpload: '{{ url('/shared-with-me') }}/__SHARE__/upload',
        memberRename: '{{ url('/shared-with-me') }}/__SHARE__/files/__FILE__',
        memberDelete: '{{ url('/shared-with-me') }}/__SHARE__/files/__FILE__',
        foldersUrl: '{{ route('files.rel.index') }}',
        t: {
            load_failed: @js(__('files.sf_load_failed')),
            save_failed: @js(__('files.sf_save_failed')),
            recipient_not_found: @js(__('files.sf_recipient_not_found')),
            quota: @js(__('files.sf_quota')),
        },
     })" class="space-y-6">

    <x-alert variant="error" x-show="error" x-cloak x-text="error" />

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- ───────── Shared by me ───────── --}}
        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('files.sf_shared_by_me') }}</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ __('files.sf_intro_by_me') }}</p>
            </div>

            {{-- Create a share --}}
            <div class="ll-card space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('files.sf_folder') }}</label>
                    <select x-model="form.folderId"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-[#1c1c1e] text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                        <option value="">{{ __('files.sf_choose_folder') }}</option>
                        <template x-for="f in folders" :key="f.id">
                            <option :value="f.id" x-text="f.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('files.sf_recipient_email') }}</label>
                    <input type="email" x-model="form.email" autocomplete="off" placeholder="{{ __('files.sf_recipient_email') }}"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-[#1c1c1e] text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-32">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('files.sf_role') }}</label>
                        <select x-model="form.role"
                            class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-[#1c1c1e] text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                            <option value="viewer">{{ __('files.sf_role_viewer') }}</option>
                            <option value="editor">{{ __('files.sf_role_editor') }}</option>
                        </select>
                    </div>
                    <x-button variant="primary" icon="user-plus" class="ml-auto"
                        @click="createShare()" ::disabled="creating || ! form.folderId || ! form.email.trim()">
                        {{ __('files.sf_create_share') }}
                    </x-button>
                </div>
            </div>

            {{-- Existing shares --}}
            <template x-if="! loading && ownerShares.length === 0">
                <x-empty-state class="py-8">{{ __('files.sf_no_shares') }}</x-empty-state>
            </template>

            <div class="space-y-3">
                <template x-for="share in ownerShares" :key="share.id">
                    <div class="ll-card space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white shadow-sm" style="background:#3b9fd6">
                                <x-icon name="folder" class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1 truncate font-medium text-gray-900 dark:text-gray-100" x-text="share.folder_name"></span>
                            <x-icon-button name="trash" tone="red" size="sm"
                                @click="deleteShare(share)" title="{{ __('files.sf_delete_share') }}" aria-label="{{ __('files.sf_delete_share') }}" />
                        </div>
                        <ul class="divide-y divide-black/[0.06] dark:divide-white/10 rounded-xl border border-black/[0.06] dark:border-white/10">
                            <template x-for="member in share.members" :key="member.id">
                                <li class="flex flex-wrap items-center gap-2 px-3 py-2">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm text-gray-900 dark:text-gray-100" x-text="member.name || member.email"></span>
                                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="member.email"></span>
                                    </span>
                                    <select @change="changeRole(share, member, $event.target.value)"
                                        class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-[#1c1c1e] py-1 text-xs text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                                        <option value="viewer" :selected="member.role === 'viewer'">{{ __('files.sf_role_viewer') }}</option>
                                        <option value="editor" :selected="member.role === 'editor'">{{ __('files.sf_role_editor') }}</option>
                                    </select>
                                    <x-icon-button name="x-mark" tone="red" size="sm"
                                        @click="removeMember(share, member)" title="{{ __('files.sf_remove_member') }}" aria-label="{{ __('files.sf_remove_member') }}" />
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        </section>

        {{-- ───────── Shared with me ───────── --}}
        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('files.sf_shared_with_me') }}</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ __('files.sf_intro_with_me') }}</p>
            </div>

            {{-- Grant list --}}
            <div x-show="! open" x-cloak class="space-y-3">
                <template x-if="! loading && sharedWithMe.length === 0">
                    <x-empty-state class="py-8">{{ __('files.sf_no_shared_with_me') }}</x-empty-state>
                </template>
                <div class="ll-card !p-0 overflow-hidden" x-show="sharedWithMe.length > 0">
                    <ul class="divide-y divide-black/[0.06] dark:divide-white/10">
                        <template x-for="share in sharedWithMe" :key="share.id">
                            <li>
                                <button type="button" @click="openShare(share)"
                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-accent/5">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white shadow-sm" style="background:#59ad6b">
                                        <x-icon name="folder" class="h-5 w-5" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium text-gray-900 dark:text-gray-100" x-text="share.folder_name"></span>
                                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="share.owner?.name || share.owner?.email"></span>
                                    </span>
                                    <x-badge variant="accent" ::class="share.role === 'editor' ? '' : ''">
                                        <span x-text="share.role === 'editor' ? '{{ __('files.sf_role_editor') }}' : '{{ __('files.sf_role_viewer') }}'"></span>
                                    </x-badge>
                                    <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            {{-- Browse a share --}}
            <div x-show="open" x-cloak class="space-y-3">
                <div class="flex items-center gap-2">
                    <x-icon-button name="chevron-left" size="sm" @click="closeShare()" title="{{ __('files.sf_back') }}" aria-label="{{ __('files.sf_back') }}" />
                    <span class="min-w-0 flex-1 truncate font-medium text-gray-900 dark:text-gray-100" x-text="open?.folder_name"></span>
                    <template x-if="canEdit()">
                        <label title="{{ __('files.sf_upload_here') }}" class="cursor-pointer ll-accent rounded-xl p-2 text-white" ::class="busy ? 'opacity-60 pointer-events-none' : ''">
                            <x-icon name="arrow-up-tray" class="h-5 w-5" />
                            <input type="file" multiple class="hidden" @change="uploadFiles($event)">
                        </label>
                    </template>
                </div>

                <div class="ll-card !p-0 overflow-hidden">
                    <template x-if="(browse.folders?.length ?? 0) === 0 && (browse.files?.length ?? 0) === 0">
                        <x-empty-state class="py-8">{{ __('files.sf_empty_folder') }}</x-empty-state>
                    </template>
                    <ul class="divide-y divide-black/[0.06] dark:divide-white/10">
                        {{-- Sub-folders (informational — the subtree is already flattened) --}}
                        <template x-for="d in (browse.folders ?? [])" :key="'d' + d.id">
                            <li class="flex items-center gap-3 px-3 py-2.5" x-show="d.id !== browse.root_id">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#e2915a">
                                    <x-icon name="folder" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-300" x-text="d.name"></span>
                            </li>
                        </template>
                        {{-- Files --}}
                        <template x-for="f in (browse.files ?? [])" :key="'f' + f.id">
                            <li class="flex items-center gap-3 px-3 py-2.5">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#6b7280">
                                    <x-icon name="document" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <template x-if="renameId !== f.id">
                                        <span class="block truncate text-sm text-gray-900 dark:text-gray-100" x-text="f.name"></span>
                                    </template>
                                    <template x-if="renameId === f.id">
                                        <form class="flex items-center gap-2" @submit.prevent="commitRename(f)">
                                            <input type="text" x-model="renameValue"
                                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-[#1c1c1e] py-1 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                                            <x-button type="submit" variant="primary" size="sm">{{ __('files.sf_save') }}</x-button>
                                            <x-icon-button name="x-mark" size="sm" @click="renameId = null" aria-label="{{ __('common.cancel') }}" />
                                        </form>
                                    </template>
                                    <span class="block text-xs text-gray-400 dark:text-gray-500" x-text="formatBytes(f.size)"></span>
                                </span>
                                <span class="flex shrink-0 items-center gap-1">
                                    <a :href="fileUrl(f, false)" target="_blank" rel="noopener"
                                        class="min-h-9 min-w-9 inline-flex items-center justify-center rounded-lg p-2 text-gray-500 hover:bg-accent/5 dark:text-gray-400"
                                        title="{{ __('files.sf_open') }}" aria-label="{{ __('files.sf_open') }}"><x-icon name="eye" class="h-4 w-4" /></a>
                                    <a :href="fileUrl(f, true)"
                                        class="min-h-9 min-w-9 inline-flex items-center justify-center rounded-lg p-2 text-gray-500 hover:bg-accent/5 dark:text-gray-400"
                                        title="{{ __('files.sf_download') }}" aria-label="{{ __('files.sf_download') }}"><x-icon name="arrow-down-tray" class="h-4 w-4" /></a>
                                    <template x-if="canEdit()">
                                        <span class="flex items-center gap-1">
                                            <x-icon-button name="pencil" size="sm" @click="startRename(f)" title="{{ __('files.sf_rename') }}" aria-label="{{ __('files.sf_rename') }}" />
                                            <x-icon-button name="trash" tone="red" size="sm" @click="deleteFile(f)" title="{{ __('files.sf_delete') }}" aria-label="{{ __('files.sf_delete') }}" />
                                        </span>
                                    </template>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</div>
