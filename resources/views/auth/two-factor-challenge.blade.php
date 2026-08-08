<x-layouts.guest :title="__('auth_ui.twofa_title')">
    <div class="ll-card" x-data="{ recovery: false }">
        <h1 class="text-center text-xl font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('auth_ui.twofa_title') }}</h1>
        <p class="mt-2 text-center text-sm text-md-on-surface-var dark:text-md-on-surface-var" x-show="! recovery">{{ __('auth_ui.twofa_intro') }}</p>

        @if ($errors->any())
            <x-alert variant="error" class="mt-4" role="alert">{{ $errors->first() }}</x-alert>
        @endif

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6 space-y-4">
            @csrf
            <div x-show="! recovery">
                <label for="code" class="block text-xs font-medium text-md-on-surface-var dark:text-md-on-surface-var mb-1">{{ __('auth_ui.twofa_code') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                    class="w-full rounded-lg border border-md-outline-variant dark:border-md-outline-variant bg-white dark:bg-md-surface px-3 py-2 text-sm tracking-widest text-md-on-surface dark:text-md-on-surface focus:border-accent focus:ring-accent">
            </div>
            <div x-show="recovery" x-cloak>
                <label for="recovery_code" class="block text-xs font-medium text-md-on-surface-var dark:text-md-on-surface-var mb-1">{{ __('auth_ui.twofa_recovery') }}</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                    class="w-full rounded-lg border border-md-outline-variant dark:border-md-outline-variant bg-white dark:bg-md-surface px-3 py-2 text-sm text-md-on-surface dark:text-md-on-surface focus:border-accent focus:ring-accent">
            </div>
            <x-button variant="primary" size="lg" type="submit" class="w-full">
                {{ __('auth_ui.twofa_verify') }}
            </x-button>
        </form>

        <button type="button" @click="recovery = ! recovery"
            class="mt-4 w-full text-center text-xs text-accent hover:underline">
            <span x-show="! recovery">{{ __('auth_ui.twofa_use_recovery') }}</span>
            <span x-show="recovery" x-cloak>{{ __('auth_ui.twofa_use_code') }}</span>
        </button>
    </div>
</x-layouts.guest>
