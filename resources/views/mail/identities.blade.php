<x-layouts.app :title="__('mail.identities_title')">
    <div x-data="mailIdentities({ deleteConfirm: @js(__('mail.identity_delete')), saved: @js(__('mail.saved')), saveFailed: @js(__('mail.save_failed')) })" x-init="init()" class="mx-auto max-w-3xl">
        <x-page-heading :title="__('mail.identities_heading')" :subtitle="__('mail.identities_sub')">
            <x-slot:actions>
                <x-button icon="chevron-left" href="{{ route('mail.index') }}">{{ __('mail.back_to_mail') }}</x-button>
                <x-button icon="pencil" href="{{ route('mail.signatures') }}">{{ __('mail.signatures_heading') }}</x-button>
            </x-slot:actions>
        </x-page-heading>

        <div class="mt-4 space-y-4">
            <template x-for="acc in accounts" :key="acc.id">
                <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="acc.name"></h2>
                        <button type="button" @click="openNew(acc.id)" class="inline-flex items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                            <x-icon name="plus" class="h-3.5 w-3.5" />{{ __('mail.identity_add') }}
                        </button>
                    </div>
                    <ul class="mt-2 space-y-1.5">
                        <template x-for="i in acc.identities" :key="i.id">
                            <li class="flex items-center gap-2 rounded-md border border-gray-200 dark:border-gray-800 px-3 py-2">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-gray-800 dark:text-gray-200" x-text="(i.fromName ? i.fromName + ' <' + i.fromEmail + '>' : i.fromEmail)"></p>
                                    <p class="flex flex-wrap gap-x-2 text-[11px] text-gray-500 dark:text-gray-400">
                                        <span x-show="i.isDefault">{{ __('mail.identity_default') }}</span>
                                        <span x-show="sigName(i.signatureId)" x-cloak>{{ __('mail.identity_signature') }}: <span x-text="sigName(i.signatureId)"></span></span>
                                    </p>
                                </div>
                                <button type="button" @click="openEdit(acc.id, i)" class="shrink-0 rounded p-1 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200"><x-icon name="pencil" class="h-3.5 w-3.5" /></button>
                                <button type="button" x-show="acc.identities.length > 1" @click="destroy(acc.id, i)" class="shrink-0 rounded p-1 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                            </li>
                        </template>
                    </ul>

                    {{-- Inline editor for this account --}}
                    <div x-show="editing && editing.accountId === acc.id" x-cloak class="mt-3 space-y-3 rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 p-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.identity_from_name') }}</label>
                                <input type="text" x-model="form.from_name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.identity_from_email') }}</label>
                                <input type="email" x-model="form.from_email" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm">
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.identity_reply_to') }}</label>
                                <input type="email" x-model="form.reply_to" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.identity_signature') }}</label>
                                <select x-model.number="form.signature_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm">
                                    <option :value="null">{{ __('mail.identity_signature_none') }}</option>
                                    <template x-for="s in signatures" :key="s.id"><option :value="s.id" x-text="s.name"></option></template>
                                </select>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" x-model="form.is_default" class="rounded border-gray-300 dark:border-gray-700 text-gray-800 focus:ring-gray-500">
                            {{ __('mail.identity_set_default') }}
                        </label>
                        <p x-show="error" x-cloak class="text-xs text-red-600 dark:text-red-400" x-text="error"></p>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editing = null" class="rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                            <button type="button" @click="save()" :disabled="saving" class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('mail.identity_save') }}</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-layouts.app>
