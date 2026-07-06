<x-layouts.app :title="$account ? __('mail.edit_title') : __('mail.add_title')">
    @php $inp = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500'; @endphp
    <div x-data="mailAccountEdit({
            account: @js($account),
            settingsUrl: @js(route('settings.mail.edit')),
            saveFailed: @js(__('mail.save_failed')),
        })" class="mx-auto max-w-2xl">
        <x-page-heading :title="$account ? __('mail.edit_title') : __('mail.add_title')">
            <x-slot:actions>
                <x-button icon="chevron-left" href="{{ route('settings.mail.edit') }}">{{ __('mail.back_to_settings') }}</x-button>
            </x-slot:actions>
        </x-page-heading>

        <div class="mt-4 space-y-5">
            {{-- General --}}
            <section class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('mail.section_general') }}</h2>
                <div class="mt-3">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_name') }}</label>
                    <input type="text" x-model="form.name" class="{{ $inp }}">
                </div>
            </section>

            {{-- IMAP (incoming) --}}
            <section class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('mail.section_imap') }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_host') }}</label>
                        <input type="text" x-model="form.host" placeholder="imap.example.com" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_port') }}</label>
                        <input type="number" min="1" max="65535" x-model="form.port" class="{{ $inp }}">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_encryption') }}</label>
                    <select x-model="form.encryption" @change="form.port = imapPortFor(form.encryption)" class="{{ $inp }}">
                        <option value="ssl">{{ __('mail.encryption_ssl_imap') }}</option>
                        <option value="starttls">{{ __('mail.encryption_starttls_imap') }}</option>
                    </select>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_username') }}</label>
                        <input type="text" x-model="form.username" autocomplete="off" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.field_password') }}</label>
                        <input type="password" x-model="form.password" autocomplete="new-password" :placeholder="id ? '••••••••' : ''" class="{{ $inp }}">
                    </div>
                </div>
                <label class="mt-3 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" x-model="form.validate_cert" class="rounded border-gray-300 dark:border-gray-700 text-gray-800 focus:ring-gray-500">
                    {{ __('mail.field_validate_cert') }}
                </label>
                <p x-show="! form.validate_cert" x-cloak class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ __('mail.validate_cert_warning') }}</p>
            </section>

            {{-- SMTP (outgoing) --}}
            <section class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('mail.section_smtp') }}</h2>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('mail.smtp_hint') }}</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.smtp_host') }}</label>
                        <input type="text" x-model="form.smtp_host" placeholder="smtp.example.com" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.smtp_port') }}</label>
                        <input type="number" min="1" max="65535" x-model="form.smtp_port" placeholder="465" class="{{ $inp }}">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.smtp_encryption') }}</label>
                    <select x-model="form.smtp_encryption" @change="form.smtp_port = smtpPortFor(form.smtp_encryption)" class="{{ $inp }}">
                        <option value="ssl">{{ __('mail.encryption_ssl_smtp') }}</option>
                        <option value="starttls">{{ __('mail.encryption_starttls_smtp') }}</option>
                        <option value="none">{{ __('mail.encryption_none_smtp') }}</option>
                    </select>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.smtp_username') }}</label>
                        <input type="text" x-model="form.smtp_username" autocomplete="off" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.smtp_password') }}</label>
                        <input type="password" x-model="form.smtp_password" autocomplete="new-password" :placeholder="id && form.hasSmtpPassword ? '••••••••' : ''" class="{{ $inp }}">
                    </div>
                </div>
            </section>

            {{-- Sender defaults --}}
            <section class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('mail.section_sender') }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.from_name') }}</label>
                        <input type="text" x-model="form.from_name" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.reply_to') }}</label>
                        <input type="email" x-model="form.reply_to" autocomplete="off" class="{{ $inp }}">
                    </div>
                </div>
                @if ($account)
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 dark:border-gray-800 pt-4">
                        <a href="{{ route('mail.identities.page') }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="user" class="h-4 w-4" />{{ __('mail.identities_heading') }}</a>
                        <a href="{{ route('mail.signatures') }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="pencil" class="h-4 w-4" />{{ __('mail.signatures_heading') }}</a>
                    </div>
                @else
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">{{ __('mail.identities_after_save') }}</p>
                @endif
            </section>

            {{-- Connection test result --}}
            <template x-if="testResult">
                <div class="space-y-1.5 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 text-xs shadow-sm">
                    <div class="flex items-start gap-2">
                        <x-icon ::name="testResult.imap.ok ? 'check-circle' : 'x-circle'" class="h-4 w-4 shrink-0" ::class="testResult.imap.ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" />
                        <div class="min-w-0"><span class="font-medium text-gray-700 dark:text-gray-300">{{ __('mail.test_imap') }}:</span>
                            <span x-text="testResult.imap.ok ? @js(__('mail.test_ok')) : (testResult.imap.error || @js(__('mail.test_failed')))" ::class="testResult.imap.ok ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'"></span></div>
                    </div>
                    <div class="flex items-start gap-2">
                        <x-icon ::name="!testResult.smtp.configured ? 'info' : (testResult.smtp.ok ? 'check-circle' : 'x-circle')" class="h-4 w-4 shrink-0" ::class="!testResult.smtp.configured ? 'text-gray-400' : (testResult.smtp.ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')" />
                        <div class="min-w-0"><span class="font-medium text-gray-700 dark:text-gray-300">{{ __('mail.test_smtp') }}:</span>
                            <span x-text="!testResult.smtp.configured ? @js(__('mail.test_smtp_unconfigured')) : (testResult.smtp.ok ? @js(__('mail.test_ok')) : (testResult.smtp.error || @js(__('mail.test_failed'))))" ::class="!testResult.smtp.configured ? 'text-gray-500 dark:text-gray-400' : (testResult.smtp.ok ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400')"></span></div>
                    </div>
                </div>
            </template>
            <p x-show="error" x-cloak class="text-sm text-red-600 dark:text-red-400" x-text="error"></p>

            {{-- Delete choice (keep or drop the local archive) --}}
            @if ($account)
                <div x-data="{ open: false }">
                    <div x-show="open" x-cloak class="mb-3 rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-4">
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ __('mail.delete_account_confirm') }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" @click="destroy(true)" class="rounded-md border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-800">{{ __('mail.delete_account_keep') }}</button>
                            <button type="button" @click="destroy(false)" class="rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('mail.delete_account_with_archive') }}</button>
                            <button type="button" @click="open = false" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-white dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex gap-2">
                            <x-button icon="arrow-path" @click="testConnection()" x-bind:disabled="testing"><span x-text="testing ? '…' : @js(__('mail.test_connection'))"></span></x-button>
                            <x-button variant="danger" icon="trash" @click="open = ! open">{{ __('mail.delete') }}</x-button>
                        </div>
                        <div class="flex gap-2">
                            <x-button href="{{ route('settings.mail.edit') }}">{{ __('common.cancel') }}</x-button>
                            <x-button variant="primary" @click="save()" x-bind:disabled="saving">{{ __('mail.save') }}</x-button>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <x-button icon="arrow-path" @click="testConnection()" x-bind:disabled="testing"><span x-text="testing ? '…' : @js(__('mail.test_connection'))"></span></x-button>
                    <div class="flex gap-2">
                        <x-button href="{{ route('settings.mail.edit') }}">{{ __('common.cancel') }}</x-button>
                        <x-button variant="primary" @click="save()" x-bind:disabled="saving">{{ __('mail.save') }}</x-button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
