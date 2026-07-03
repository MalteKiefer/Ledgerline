{{-- Add / edit account modal (used inside a vaultMail x-data scope). --}}
<template x-teleport="body">
    <div x-show="dialogOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="dialogOpen = false">
        <div class="absolute inset-0 bg-gray-900/40" @click="dialogOpen = false"></div>
        <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
            <h3 class="text-base font-semibold text-gray-900" x-text="editingId ? @js(__('mail.edit_title')) : @js(__('mail.add_title'))"></h3>
            <div class="mt-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_name') }}</label>
                    <input type="text" x-model="form.name" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_host') }}</label>
                        <input type="text" x-model="form.host" placeholder="imap.example.com" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_port') }}</label>
                        <input type="number" min="1" max="65535" x-model="form.port" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_encryption') }}</label>
                    <select x-model="form.encryption" @change="form.port = form.encryption === 'starttls' ? 143 : 993"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <option value="ssl">{{ __('mail.encryption_ssl') }}</option>
                        <option value="starttls">{{ __('mail.encryption_starttls') }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_username') }}</label>
                        <input type="text" x-model="form.username" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_password') }}</label>
                        <input type="password" x-model="form.password" autocomplete="new-password" :placeholder="editingId ? '••••••••' : ''" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" x-model="form.validateCert" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                    {{ __('mail.field_validate_cert') }}
                </label>
                <p class="text-xs text-gray-400">{{ __('mail.security_note') }}</p>
                <p x-show="error" x-cloak class="text-xs text-red-600" x-text="error"></p>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" @click="dialogOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                <button type="button" @click="saveAccount()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('mail.save') }}</button>
            </div>
        </div>
    </div>
</template>

{{-- Delete confirm --}}
<template x-teleport="body">
    <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteOpen = false">
        <div class="absolute inset-0 bg-gray-900/40" @click="deleteOpen = false"></div>
        <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ __('mail.confirm_delete') }}</p>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" @click="deleteOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                <button type="button" @click="applyDelete()" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('mail.delete') }}</button>
            </div>
        </div>
    </div>
</template>
