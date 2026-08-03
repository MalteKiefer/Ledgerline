<x-layouts.app :title="__('mailkeys.title')">
    <div class="mx-auto w-full max-w-3xl" x-data="mailKeys({
        errNotPrivate: @js(__('mailkeys.err_not_private')),
        errImport: @js(__('mailkeys.err_import')),
        errGenerate: @js(__('mailkeys.err_generate')),
        confirmDelete: @js(__('mailkeys.confirm_delete')),
        copied: @js(__('mailkeys.copied')),
     })">
        @include('profile._header', ['title' => __('mailkeys.title')])

        <p class="mt-4 mb-4 px-1 text-sm text-gray-500 dark:text-gray-400">{{ __('mailkeys.subtitle') }}</p>

        {{-- Vault locked --}}
        <template x-if="state === 'locked'">
            <x-alert variant="info">
                <span>{{ __('mailkeys.locked_hint') }}</span>
                <button type="button" class="ml-1 font-medium text-accent underline" @click="unlock()">{{ __('mailkeys.unlock') }}</button>
            </x-alert>
        </template>

        <template x-if="state === 'ready'">
        <div>
            <x-alert variant="error" x-show="error" x-cloak x-text="error" class="mb-4" />

            {{-- Actions --}}
            <div class="mb-4 flex flex-wrap gap-2" x-show="mode === 'list'">
                <x-button variant="secondary" size="sm" icon="plus" @click="mode = 'import'">{{ __('mailkeys.import') }}</x-button>
                <x-button variant="secondary" size="sm" icon="key" @click="mode = 'generate'">{{ __('mailkeys.generate') }}</x-button>
                <x-button variant="secondary" size="sm" icon="lock-closed" @click="mode = 'smime'">{{ __('mailkeys.import_smime') }}</x-button>
            </div>

            {{-- S/MIME import (.p12) --}}
            <div class="ll-card mb-4" x-show="mode === 'smime'" x-cloak>
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('mailkeys.import_smime') }}</h3>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.name') }}</label>
                <input type="text" x-model="smName" class="mt-1 mb-3 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.p12_file') }}</label>
                <input type="file" accept=".p12,.pfx,application/x-pkcs12" @change="p12Chosen($event)" class="mt-1 mb-3 block w-full text-sm text-gray-600 dark:text-gray-400">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.passphrase_opt') }}</label>
                <input type="password" x-model="smPass" autocomplete="new-password" class="mt-1 mb-4 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                <div class="flex gap-2">
                    <x-button variant="primary" size="sm" ::disabled="busy" @click="importSmime()">{{ __('mailkeys.add') }}</x-button>
                    <x-button variant="secondary" size="sm" @click="mode = 'list'; error = ''">{{ __('common.cancel') }}</x-button>
                </div>
            </div>

            {{-- Import form --}}
            <div class="ll-card mb-4" x-show="mode === 'import'" x-cloak>
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('mailkeys.import') }}</h3>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.name') }}</label>
                <input type="text" x-model="impName" class="mt-1 mb-3 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.armored_private') }}</label>
                <textarea x-model="impArmored" rows="6" placeholder="-----BEGIN PGP PRIVATE KEY BLOCK-----" class="mt-1 mb-3 block w-full rounded-md border-gray-300 dark:border-gray-700 font-mono text-xs"></textarea>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.passphrase_opt') }}</label>
                <input type="password" x-model="impPassphrase" autocomplete="new-password" class="mt-1 mb-4 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                <div class="flex gap-2">
                    <x-button variant="primary" size="sm" ::disabled="busy" @click="importKey()">{{ __('mailkeys.add') }}</x-button>
                    <x-button variant="secondary" size="sm" @click="mode = 'list'; error = ''">{{ __('common.cancel') }}</x-button>
                </div>
            </div>

            {{-- Generate form --}}
            <div class="ll-card mb-4" x-show="mode === 'generate'" x-cloak>
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('mailkeys.generate') }}</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.name') }}</label>
                        <input type="text" x-model="genName" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.email') }}</label>
                        <input type="email" x-model="genEmail" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                    </div>
                </div>
                <label class="mt-3 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.passphrase_opt') }}</label>
                <input type="password" x-model="genPassphrase" autocomplete="new-password" class="mt-1 mb-4 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                <div class="flex items-center gap-2">
                    <x-button variant="primary" size="sm" ::disabled="busy" @click="generate()">
                        <span x-show="!busy">{{ __('mailkeys.generate') }}</span>
                        <span x-show="busy" x-cloak><x-icon name="arrow-path" class="mr-1 inline h-4 w-4 animate-spin" />{{ __('mailkeys.generating') }}</span>
                    </x-button>
                    <x-button variant="secondary" size="sm" @click="mode = 'list'; error = ''">{{ __('common.cancel') }}</x-button>
                </div>
            </div>

            {{-- Key list --}}
            <x-empty-state icon="key" x-show="mode === 'list' && keys.length === 0" class="py-10">{{ __('mailkeys.no_keys') }}</x-empty-state>

            <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10" x-show="keys.length > 0">
                <template x-for="k in keys" :key="k.id">
                    <div class="flex items-start gap-3 px-4 py-3">
                        <span class="ll-chip mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background:#7066f5">
                            <x-icon name="key" class="h-4 w-4 text-white" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                <span class="truncate" x-text="k.name"></span>
                                <x-badge variant="gray" x-text="keyType(k)"></x-badge>
                            </p>
                            <p class="truncate text-xs text-gray-500" x-text="k.userId"></p>
                            <p class="mt-0.5 font-mono text-[11px] text-gray-400" x-text="fp(k)"></p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <x-icon-button name="clipboard" tone="gray" size="sm" @click="copyPublic(k)" :aria-label="__('mailkeys.copy_public')" />
                            <x-icon-button name="trash" tone="red" size="sm" @click="removeKey(k.id)" :aria-label="__('common.delete')" />
                        </div>
                    </div>
                </template>
            </div>
        </div>
        </template>
    </div>
</x-layouts.app>
