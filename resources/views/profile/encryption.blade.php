<x-layouts.app :title="__('account.nav_encryption')">
    <div class="mx-auto w-full max-w-3xl">
        @include('profile._header', ['title' => __('account.nav_encryption'), 'subtitle' => __('vault.settings_hint')])

        <div class="mt-5 ll-card">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('vault.settings_heading') }}</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <template x-if="$store.vault.configured">
                    <div class="flex flex-wrap gap-3">
                        <x-button variant="secondary" type="button" x-on:click="$dispatch('vault-change')">{{ __('vault.change_action') }}</x-button>
                        <x-button variant="secondary" type="button" x-on:click="$dispatch('vault-recover')">{{ __('vault.reset_action') }}</x-button>
                    </div>
                </template>
                <template x-if="! $store.vault.configured">
                    <x-button variant="secondary" type="button" x-on:click="$dispatch('vault-panel')">{{ __('vault.setup') }}</x-button>
                </template>
            </div>
        </div>
    </div>
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])
</x-layouts.app>
