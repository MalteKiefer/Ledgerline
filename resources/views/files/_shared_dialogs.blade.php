{{-- Unified share dialog (People + public link) — single entry point via openUnifiedShare(row).
     People tab: visible for folders only (invite member + members list).
       - Personal (not-yet-shared) folder: shows "Enable sharing" button (step 1).
         After enableSharing() converts the folder, step 2 (invite form + members) appears.
       - Already-shared folder (vaultId set): shows invite form + members directly.
     Link tab: public-link UI for individual files and personal (not-yet-shared) folders.
       Hidden for already-shared folders (vaultId set) — public-link of a shared folder
       is out of v1 scope (the server manifest is the vault store, not the personal one).
     Included from files/index.blade.php. --}}

<template x-teleport="body">
    <div x-show="unifiedShare.open" x-cloak
        class="fixed inset-0 z-[961] flex items-center justify-center p-4"
        @keydown.escape.window="closeUnifiedShare()">
        <div class="absolute inset-0 bg-gray-900/50" @click="closeUnifiedShare()"></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 pt-5 pb-0">
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('files.share_title') }}</h3>
                    <p class="mt-0.5 truncate text-sm text-gray-500 dark:text-gray-400" x-text="unifiedShare.row?.name"></p>
                </div>
                <button type="button" @click="closeUnifiedShare()"
                    class="ml-4 shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-accent/5">
                    <x-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>

            {{-- Tab bar: People (folders only) | Link (files + personal folders only).
                 Link tab is hidden for already-shared folders (vaultId set). --}}
            <div class="mt-4 flex items-center gap-0 border-b border-gray-100 dark:border-gray-800 px-5">
                <template x-if="unifiedShare.isFolder">
                    <button type="button"
                        @click="unifiedShare.tab = 'people'"
                        :class="unifiedShare.tab === 'people'
                            ? 'border-accent text-accent'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="mr-4 border-b-2 pb-3 text-sm font-medium transition-colors">
                        {{ __('files.share_people') }}
                    </button>
                </template>
                {{-- Hide Link tab for already-shared folders (has vaultId) --}}
                <template x-if="! (unifiedShare.isFolder && unifiedShare.vaultId)">
                    <button type="button"
                        @click="unifiedShare.tab = 'link'"
                        :class="unifiedShare.tab === 'link'
                            ? 'border-accent text-accent'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="border-b-2 pb-3 text-sm font-medium transition-colors">
                        {{ __('files.share_link') }}
                    </button>
                </template>
            </div>

            {{-- ===== People tab ===== --}}
            <template x-if="unifiedShare.tab === 'people' && unifiedShare.isFolder">
                <div class="p-5 space-y-4">

                    {{-- Step 1: personal folder, no vaultId yet.
                         Show hint + single primary "Enable sharing" button. --}}
                    <template x-if="! unifiedShare.vaultId">
                        <div class="space-y-3">
                            <p class="rounded-lg bg-blue-50 dark:bg-blue-900/20 px-3 py-2 text-sm text-blue-700 dark:text-blue-300">
                                {{ __('files.share_convert_hint') }}
                            </p>
                            <div class="flex justify-end">
                                <button type="button"
                                    @click="enableSharing()"
                                    class="rounded-xl ll-accent px-4 py-2 text-sm font-medium text-white">
                                    {{ __('files.share_enable') }}
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- Step 2 / already-shared folder: invite form + members list. --}}
                    <template x-if="unifiedShare.vaultId">
                        <div class="space-y-4">

                            {{-- Invite form --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('files.folder_recipient') }}</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input type="text" x-model="shareFolderDialog.identifier"
                                        @keydown.enter="lookUpFolderRecipient()"
                                        placeholder="{{ __('files.folder_recipient') }}"
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-accent focus:ring-accent">
                                    <button type="button" @click="lookUpFolderRecipient()"
                                        :disabled="shareFolderDialog.lookingUp || ! shareFolderDialog.identifier.trim()"
                                        class="shrink-0 rounded-xl ll-accent px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                                        x-text="shareFolderDialog.lookingUp ? '…' : '{{ __('files.folder_look_up') }}'"></button>
                                </div>
                            </div>

                            {{-- Fingerprint + role (shown once recipient is resolved and fingerprint is not changed) --}}
                            <template x-if="shareFolderDialog.resolved && shareFolderDialog.fingerprintStatus !== 'changed'">
                                <div class="space-y-3">
                                    <div class="rounded-lg border border-gray-200 dark:border-gray-800 p-3 text-xs">
                                        <p class="font-medium text-gray-500 dark:text-gray-400">{{ __('files.folder_fingerprint_label') }}</p>
                                        <p class="mt-1 break-all font-mono text-gray-900 dark:text-gray-100" x-text="shareFolderDialog.resolved && shareFolderDialog.resolved.fingerprint"></p>
                                        <p x-show="shareFolderDialog.fingerprintStatus === 'new'" class="mt-1 text-amber-600 dark:text-amber-400">{{ __('files.folder_fingerprint_new') }}</p>
                                        <p x-show="shareFolderDialog.fingerprintStatus === 'verified'" class="mt-1 flex items-center gap-1 text-green-600 dark:text-green-400"><x-icon name="check-circle" class="h-3.5 w-3.5" /><span>{{ __('files.folder_fingerprint_verified') }}</span></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('files.folder_role_label') }}</label>
                                        <select x-model="shareFolderDialog.role"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-accent focus:ring-accent">
                                            <option value="read">{{ __('files.folder_role_read') }}</option>
                                            <option value="edit">{{ __('files.folder_role_edit') }}</option>
                                            <option value="manage">{{ __('files.folder_role_manage') }}</option>
                                        </select>
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="button"
                                            @click="unifiedInvite()"
                                            :disabled="shareFolderDialog.sharing"
                                            class="rounded-xl ll-accent px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                                            <span x-text="shareFolderDialog.sharing ? '…' : '{{ __('files.folder_invite') }}'"></span>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            {{-- Fingerprint changed warning --}}
                            <p x-show="shareFolderDialog.fingerprintStatus === 'changed'" x-cloak
                                class="rounded-lg bg-red-50 dark:bg-red-900/20 px-3 py-2 text-sm text-red-700 dark:text-red-300">{{ __('files.folder_fingerprint_changed') }}</p>

                            {{-- Notice (error / info) --}}
                            <p x-show="shareFolderDialog.notice" x-cloak
                                class="rounded-lg bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-sm text-amber-800 dark:text-amber-300"
                                x-text="shareFolderDialog.notice"></p>

                            {{-- Members list --}}
                            <div class="border-t border-gray-100 dark:border-gray-800 pt-4">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">{{ __('files.folder_members') }}</p>

                                <template x-if="managingFolderVaultLoading">
                                    <div class="py-4 text-center text-sm text-gray-400">
                                        <span class="animate-spin inline-block"><x-icon name="arrow-path" class="h-5 w-5" /></span>
                                    </div>
                                </template>

                                <template x-if="! managingFolderVaultLoading">
                                    <div>
                                        <template x-if="rotatingFolderKeys">
                                            <div class="py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                                <span class="animate-spin inline-block mr-2"><x-icon name="arrow-path" class="h-4 w-4 inline" /></span>
                                                <span>{{ __('files.folder_rotating_keys') }}</span>
                                            </div>
                                        </template>
                                        <template x-if="! rotatingFolderKeys">
                                            <ul class="divide-y divide-gray-100 dark:divide-gray-800 max-h-48 overflow-y-auto -mx-5 px-5">
                                                <template x-for="m in managingFolderVaultMembers" :key="m.id">
                                                    <li class="flex items-center gap-3 py-2.5">
                                                        <x-icon name="user" class="h-5 w-5 shrink-0 text-gray-400" />
                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" x-text="m.email || m.name || ('#' + m.user_id)"></p>
                                                            <p class="text-xs text-gray-400 font-mono truncate" x-text="m.recipient_fingerprint ? m.recipient_fingerprint.slice(0,16) + '…' : '—'"></p>
                                                        </div>
                                                        {{-- Role select for active non-manager members when current user has manage access --}}
                                                        <template x-if="_canManageActive() && m.role !== 'manager' && m.status === 'active'">
                                                            <select
                                                                :value="_serverToClientRole(m.role)"
                                                                @change="changeFolderMemberRole(m.id, $event.target.value)"
                                                                class="shrink-0 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs py-0.5 px-1 focus:border-accent focus:ring-accent">
                                                                <option value="read">{{ __('files.folder_role_read') }}</option>
                                                                <option value="edit">{{ __('files.folder_role_edit') }}</option>
                                                                <option value="manage">{{ __('files.folder_role_manage') }}</option>
                                                            </select>
                                                        </template>
                                                        <template x-if="! (_canManageActive() && m.role !== 'manager' && m.status === 'active')">
                                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium"
                                                                  :class="m.role === 'manager' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' : (m.role === 'editor' ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400')"
                                                                  x-text="m.role === 'manager' ? '{{ __('files.folder_role_manager') }}' : (m.role === 'editor' ? '{{ __('files.folder_role_editor') }}' : '{{ __('files.folder_role_viewer') }}')"></span>
                                                        </template>
                                                        <span class="shrink-0 text-[10px]"
                                                              :class="m.status === 'active' ? 'text-green-600 dark:text-green-400' : 'text-amber-500'"
                                                              x-text="m.status === 'active' ? '{{ __('files.folder_member_status_active') }}' : '{{ __('files.folder_member_status_pending') }}'"></span>
                                                        <button type="button"
                                                            @click="removeFolderMember(m.id, m.user_id)"
                                                            title="{{ __('files.folder_remove_member') }}"
                                                            class="shrink-0 rounded-md p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                                                            <x-icon name="trash" class="h-4 w-4" />
                                                        </button>
                                                    </li>
                                                </template>
                                            </ul>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            {{-- Owner-only: convert this shared folder back to a private folder (dissolves the vault). --}}
                            <template x-if="sharedFolders.find(f => f.vaultId === unifiedShare.vaultId)?.owned">
                                <div class="pt-2 border-t border-gray-100 dark:border-gray-800">
                                    <button type="button"
                                        @click="convertSharedToPersonal(unifiedShare.vaultId); closeUnifiedShare()"
                                        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-accent/5">
                                        <x-icon name="lock-closed" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                        <span class="min-w-0 flex-1 text-left">
                                            <span class="block">{{ __('files.folder_to_private') }}</span>
                                            <span class="block text-xs text-gray-400 dark:text-gray-500">{{ __('files.folder_to_private_hint') }}</span>
                                        </span>
                                    </button>
                                </div>
                            </template>

                            {{-- Footer: delete shared folder (manage only) + close --}}
                            <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
                                <button type="button"
                                    x-show="sharedFolders.find(f => f.vaultId === unifiedShare.vaultId)?.role === 'manage'"
                                    @click="deleteSharedFolder(unifiedShare.vaultId); closeUnifiedShare()"
                                    class="rounded-md px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                                    <x-icon name="trash" class="inline h-3.5 w-3.5 mr-1" />{{ __('files.folder_delete') }}
                                </button>
                                <button type="button" @click="closeUnifiedShare()"
                                    class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('common.close') }}</button>
                            </div>

                        </div>
                    </template>

                    {{-- Footer for step-1 (personal folder, no vaultId yet) --}}
                    <template x-if="! unifiedShare.vaultId">
                        <div class="flex justify-end pt-2 border-t border-gray-100 dark:border-gray-800">
                            <button type="button" @click="closeUnifiedShare()"
                                class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('common.close') }}</button>
                        </div>
                    </template>

                </div>
            </template>

            {{-- ===== Link tab ===== --}}
            <template x-if="unifiedShare.tab === 'link'">
                <div class="p-5 space-y-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400"
                        x-text="unifiedShare.row?.kind === 'folder' ? '{{ __('files.share_intro_folder') }}' : '{{ __('files.share_intro_file') }}'"></p>

                    {{-- Existing link display --}}
                    <div x-show="share.link" x-cloak class="rounded-xl border border-black/[0.06] dark:border-white/10 p-3">
                        <label class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.share_link_label') }}</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="text" readonly :value="share.link" @focus="$event.target.select()"
                                class="w-full rounded-md border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-xs text-gray-700 dark:text-gray-300">
                            <button type="button" @click="copyShareLink()" title="{{ __('gallery.share_copy') }}"
                                class="shrink-0 rounded-md bg-gray-100 dark:bg-gray-800 p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700">
                                <x-icon name="clipboard" class="h-4 w-4" />
                            </button>
                        </div>
                        <p class="mt-2 text-[11px] leading-relaxed text-gray-400 dark:text-gray-500">{{ __('gallery.share_active_hint') }}</p>
                    </div>

                    {{-- Password + expiry --}}
                    <div class="space-y-3">
                        <label class="block text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.share_password') }}
                            <input type="password" x-model="share.password" autocomplete="new-password"
                                :placeholder="share.hasPassword ? '{{ __('gallery.share_password_set') }}' : '{{ __('gallery.share_password_hint') }}'"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                        </label>
                        <label class="block text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.share_expiry') }}
                            <input type="datetime-local" x-model="share.expiresAt"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                        </label>
                    </div>

                    <p x-show="share.error" x-cloak class="text-sm text-red-600 dark:text-red-400" x-text="share.error"></p>

                    {{-- Link actions --}}
                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-gray-100 dark:border-gray-800">
                        <button type="button" x-show="_shareSrc()?.share" x-cloak
                            @click="revokeShare()"
                            :disabled="share.busy"
                            class="rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 disabled:opacity-50">
                            {{ __('gallery.share_revoke') }}
                        </button>
                        <div class="ml-auto flex gap-2">
                            <button type="button" @click="closeUnifiedShare()"
                                class="rounded-md px-3 py-2 text-sm font-medium text-gray-500 hover:bg-accent/5">{{ __('gallery.share_close') }}</button>
                            <button type="button" x-show="! _shareSrc()?.share"
                                @click="createShare()"
                                :disabled="share.busy"
                                class="inline-flex items-center gap-1.5 ll-accent rounded-xl px-4 py-2 text-sm font-medium disabled:opacity-50">
                                <x-icon name="link" class="h-4 w-4" />{{ __('gallery.share_create_link') }}
                            </button>
                            <button type="button" x-show="_shareSrc()?.share" x-cloak
                                @click="updateShare()"
                                :disabled="share.busy"
                                class="inline-flex items-center gap-1.5 ll-accent rounded-xl px-4 py-2 text-sm font-medium disabled:opacity-50">
                                {{ __('gallery.share_update') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>
