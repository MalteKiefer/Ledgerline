{{-- Shared-folder dialogs: share (invite) dialog + members management modal.
     Mirrors the passwords share/members markup. Included from files/index.blade.php. --}}

{{-- Share folder dialog (manager only) --}}
<div x-show="shareFolderDialog.open" x-cloak
    class="fixed inset-0 z-[961] flex items-center justify-center p-4"
    @keydown.escape.window="closeShareFolderDialog()">
    <div class="absolute inset-0 bg-gray-900/50" @click="closeShareFolderDialog()"></div>
    <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-5 shadow-xl">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('files.folder_share') }}</h3>
            <button type="button" @click="closeShareFolderDialog()" class="rounded p-1 text-gray-400 hover:bg-accent/5"><x-icon name="x-mark" class="h-5 w-5" /></button>
        </div>
        <div class="mt-4 space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('files.folder_recipient') }}</label>
                <div class="mt-1 flex items-center gap-2">
                    <input type="text" x-model="shareFolderDialog.identifier"
                        @keydown.enter="lookUpFolderRecipient()"
                        placeholder="{{ __('files.folder_recipient') }}"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-accent focus:ring-accent">
                    <button type="button" @click="lookUpFolderRecipient()"
                        :disabled="shareFolderDialog.lookingUp || ! shareFolderDialog.identifier.trim()"
                        class="shrink-0 rounded-md ll-accent px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                        x-text="shareFolderDialog.lookingUp ? '…' : '{{ __('files.folder_look_up') }}'"></button>
                </div>
            </div>

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
                        <select x-model="shareFolderDialog.role" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-accent focus:ring-accent">
                            <option value="read">{{ __('files.folder_role_read') }}</option>
                            <option value="edit">{{ __('files.folder_role_edit') }}</option>
                            <option value="manage">{{ __('files.folder_role_manage') }}</option>
                        </select>
                    </div>
                </div>
            </template>

            <p x-show="shareFolderDialog.fingerprintStatus === 'changed'" x-cloak
                class="rounded-lg bg-red-50 dark:bg-red-900/20 px-3 py-2 text-sm text-red-700 dark:text-red-300">{{ __('files.folder_fingerprint_changed') }}</p>

            <p x-show="shareFolderDialog.notice" x-cloak
                class="rounded-lg bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-sm text-amber-800 dark:text-amber-300"
                x-text="shareFolderDialog.notice"></p>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" @click="closeShareFolderDialog()"
                class="rounded-md px-4 py-2 text-sm font-medium text-gray-500 hover:bg-accent/5">{{ __('common.cancel') }}</button>
            <button type="button"
                x-show="shareFolderDialog.resolved && shareFolderDialog.fingerprintStatus !== 'changed'"
                @click="confirmShareFolder()"
                :disabled="shareFolderDialog.sharing"
                class="rounded-md ll-accent px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                <span x-text="shareFolderDialog.sharing ? '…' : '{{ __('files.folder_invite') }}'"></span>
            </button>
        </div>
    </div>
</div>

{{-- Manage folder members modal --}}
<template x-if="managingFolderVaultId !== null">
    <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="managingFolderVaultId = null">
        <div class="w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('files.folder_members') }}</h2>
                <button type="button" @click="managingFolderVaultId = null"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><x-icon name="x-mark" class="h-5 w-5" /></button>
            </div>

            <template x-if="managingFolderVaultLoading">
                <div class="px-5 py-8 text-center text-sm text-gray-400">
                    <span class="animate-spin inline-block"><x-icon name="arrow-path" class="h-5 w-5" /></span>
                </div>
            </template>

            <template x-if="! managingFolderVaultLoading">
                <div>
                    <template x-if="rotatingFolderKeys">
                        <div class="px-5 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            <span class="animate-spin inline-block mr-2"><x-icon name="arrow-path" class="h-4 w-4 inline" /></span>
                            <span>{{ __('files.folder_rotating_keys') }}</span>
                        </div>
                    </template>
                    <template x-if="! rotatingFolderKeys">
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800 max-h-80 overflow-y-auto">
                            <template x-for="m in managingFolderVaultMembers" :key="m.id">
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <x-icon name="user" class="h-5 w-5 shrink-0 text-gray-400" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" x-text="m.email || m.name || ('#' + m.user_id)"></p>
                                        <p class="text-xs text-gray-400 font-mono truncate" x-text="m.recipient_fingerprint ? m.recipient_fingerprint.slice(0,16) + '…' : '—'"></p>
                                    </div>
                                    {{-- Role: select for active non-manager members when current user has manage access; badge otherwise --}}
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

            {{-- Footer: delete-folder (manage only) + close --}}
            <div class="border-t border-gray-100 dark:border-gray-800 px-5 py-3 flex items-center justify-between gap-2">
                <button type="button"
                    x-show="sharedFolders.find(f => f.vaultId === managingFolderVaultId)?.role === 'manage'"
                    @click="deleteSharedFolder(managingFolderVaultId)"
                    class="rounded-md px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                    <x-icon name="trash" class="inline h-3.5 w-3.5 mr-1" />{{ __('files.folder_delete') }}
                </button>
                <button type="button" @click="managingFolderVaultId = null"
                    class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">{{ __('common.close') }}</button>
            </div>
        </div>
    </div>
</template>
