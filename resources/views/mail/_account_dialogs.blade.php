{{-- Add / edit account modal (used inside a vaultMail x-data scope). --}}
<template x-teleport="body">
    <div x-show="dialogOpen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center" role="dialog" aria-modal="true" @keydown.escape.window="dialogOpen = false">
        <div class="absolute inset-0 bg-gray-900/40" @click="dialogOpen = false"></div>
        <div class="relative my-8 flex max-h-[90vh] w-full max-w-lg flex-col rounded-lg bg-white shadow-xl dark:bg-gray-900">
            <h3 class="shrink-0 border-b border-gray-100 px-6 py-4 text-base font-semibold text-gray-900 dark:border-gray-800 dark:text-gray-100" x-text="editingId ? @js(__('mail.edit_title')) : @js(__('mail.add_title'))"></h3>
            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-6 py-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_name') }}</label>
                    <input type="text" x-model="form.name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_host') }}</label>
                        <input type="text" x-model="form.host" placeholder="imap.example.com" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_port') }}</label>
                        <input type="number" min="1" max="65535" x-model="form.port" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_encryption') }}</label>
                    <select x-model="form.encryption" @change="form.port = imapPortFor(form.encryption)"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <option value="ssl">{{ __('mail.encryption_ssl_imap') }}</option>
                        <option value="starttls">{{ __('mail.encryption_starttls_imap') }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_username') }}</label>
                        <input type="text" x-model="form.username" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_password') }}</label>
                        <input type="password" x-model="form.password" autocomplete="new-password" :placeholder="editingId ? '••••••••' : ''" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" x-model="form.validateCert" class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                    {{ __('mail.field_validate_cert') }}
                </label>
                <p x-show="! form.validateCert" x-cloak class="text-xs text-amber-600">{{ __('mail.validate_cert_warning') }}</p>

                {{-- Collapsible SMTP / sending + identity section --}}
                <div x-data="{ smtpOpen: false }" class="border-t border-gray-200 pt-3 dark:border-gray-800">
                    <button type="button" @click="smtpOpen = ! smtpOpen"
                        class="flex w-full items-center justify-between text-xs font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                        <span>{{ __('mail.smtp_section') }}</span>
                        <x-icon name="chevron-down" class="h-4 w-4 transition-transform" ::class="smtpOpen ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="smtpOpen" x-cloak class="mt-3 space-y-3">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.smtp_host') }}</label>
                                <input type="text" x-model="form.smtp_host" placeholder="smtp.example.com" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.smtp_port') }}</label>
                                <input type="number" min="1" max="65535" x-model="form.smtp_port" placeholder="587" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.smtp_encryption') }}</label>
                            <select x-model="form.smtp_encryption" @change="form.smtp_port = smtpPortFor(form.smtp_encryption)" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <option value="ssl">{{ __('mail.encryption_ssl_smtp') }}</option>
                                <option value="starttls">{{ __('mail.encryption_starttls_smtp') }}</option>
                                <option value="none">{{ __('mail.encryption_none_smtp') }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.smtp_username') }}</label>
                                <input type="text" x-model="form.smtp_username" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.smtp_password') }}</label>
                                <input type="password" x-model="form.smtp_password" autocomplete="new-password" :placeholder="editingId && form.hasSmtpPassword ? '••••••••' : ''" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('mail.smtp_hint') }}</p>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.from_name') }}</label>
                            <input type="text" x-model="form.from_name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.reply_to') }}</label>
                            <input type="email" x-model="form.reply_to" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.signature') }}</label>
                            <textarea x-model="form.signature" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Sender identities (only for an already-saved account). --}}
                <template x-if="editingId">
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.identities') }}</span>
                            <button type="button" @click="openIdentityAdd()" class="inline-flex items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                                <x-icon name="plus" class="h-3.5 w-3.5" />{{ __('mail.identity_add') }}
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('mail.identities_hint') }}</p>
                        <ul class="mt-2 space-y-1.5">
                            <template x-for="i in editingIdentities()" :key="i.id">
                                <li class="flex items-center gap-2 rounded-md border border-gray-200 dark:border-gray-800 px-3 py-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm text-gray-800 dark:text-gray-200" x-text="(i.fromName ? i.fromName + ' <' + i.fromEmail + '>' : i.fromEmail)"></p>
                                        <span x-show="i.isDefault" x-cloak class="text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ __('mail.identity_default') }}</span>
                                    </div>
                                    <button type="button" x-show="! i.isDefault" @click="setIdentityDefault(i)" class="shrink-0 text-[11px] font-medium text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">{{ __('mail.identity_set_default') }}</button>
                                    <button type="button" @click="openIdentityEdit(i)" class="shrink-0 rounded p-1 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200" aria-label="{{ __('mail.identity_edit') }}"><x-icon name="pencil" class="h-3.5 w-3.5" /></button>
                                    <button type="button" x-show="editingIdentities().length > 1" @click="deleteIdentity(i)" class="shrink-0 rounded p-1 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-red-600" aria-label="{{ __('mail.identity_delete') }}"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                                </li>
                            </template>
                        </ul>

                        {{-- Identity add/edit sub-form. --}}
                        <div x-show="identityForm.open" x-cloak class="mt-3 space-y-3 rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 p-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.identity_from_name') }}</label>
                                    <input type="text" x-model="identityForm.from_name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.identity_from_email') }}</label>
                                    <input type="email" x-model="identityForm.from_email" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.identity_reply_to') }}</label>
                                <input type="email" x-model="identityForm.reply_to" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.identity_signature') }}</label>
                                <textarea x-model="identityForm.signature" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></textarea>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" x-model="identityForm.is_default" class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                                {{ __('mail.identity_set_default') }}
                            </label>
                            <p x-show="identityForm.error" x-cloak class="text-xs text-red-600 dark:text-red-400" x-text="identityForm.error"></p>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="cancelIdentity()" class="rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                                <button type="button" @click="saveIdentity()" class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">{{ __('mail.identity_save') }}</button>
                            </div>
                        </div>
                    </div>
                </template>

                <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('mail.security_note') }}</p>
                <p x-show="error" x-cloak class="text-xs text-red-600 dark:text-red-400" x-text="error"></p>

                {{-- Connection test result --}}
                <template x-if="testResult">
                    <div class="space-y-1.5 rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 p-3 text-xs">
                        <div class="flex items-start gap-2">
                            <x-icon ::name="testResult.imap.ok ? 'check-circle' : 'x-circle'" class="h-4 w-4 shrink-0" ::class="testResult.imap.ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" />
                            <div class="min-w-0">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('mail.test_imap') }}:</span>
                                <span x-text="testResult.imap.ok ? @js(__('mail.test_ok')) : (testResult.imap.error || @js(__('mail.test_failed')))" ::class="testResult.imap.ok ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'"></span>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <x-icon ::name="!testResult.smtp.configured ? 'info' : (testResult.smtp.ok ? 'check-circle' : 'x-circle')" class="h-4 w-4 shrink-0" ::class="!testResult.smtp.configured ? 'text-gray-400' : (testResult.smtp.ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')" />
                            <div class="min-w-0">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('mail.test_smtp') }}:</span>
                                <span x-text="!testResult.smtp.configured ? @js(__('mail.test_smtp_unconfigured')) : (testResult.smtp.ok ? @js(__('mail.test_ok')) : (testResult.smtp.error || @js(__('mail.test_failed'))))"
                                    ::class="!testResult.smtp.configured ? 'text-gray-500 dark:text-gray-400' : (testResult.smtp.ok ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400')"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="flex shrink-0 items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                <button type="button" @click="testAccount()" :disabled="testing" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    <x-icon name="arrow-path" class="h-4 w-4" ::class="testing && 'animate-spin'" />{{ __('mail.test_connection') }}
                </button>
                <div class="flex gap-3">
                    <button type="button" @click="dialogOpen = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                    <button type="button" @click="saveAccount()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('mail.save') }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

{{-- Delete confirm --}}
<template x-teleport="body">
    <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteOpen = false">
        <div class="absolute inset-0 bg-gray-900/40" @click="deleteOpen = false"></div>
        <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('mail.delete_account') }}</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('mail.delete_account_confirm') }}</p>
            <div class="mt-5 flex flex-col gap-2">
                <button type="button" @click="applyDelete(true)" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('mail.delete_account_keep') }}</button>
                <button type="button" @click="applyDelete(false)" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('mail.delete_account_with_archive') }}</button>
                <button type="button" @click="deleteOpen = false" class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
            </div>
        </div>
    </div>
</template>
