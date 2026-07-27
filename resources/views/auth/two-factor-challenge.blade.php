<x-layouts.guest :title="__('auth_ui.twofa_title')">
    <div class="ll-card" x-data="{ recovery: false }">
        <h1 class="text-center text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('auth_ui.twofa_title') }}</h1>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400" x-show="! recovery">{{ __('auth_ui.twofa_intro') }}</p>

        @if ($errors->any())
            <div class="mt-4 rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6 space-y-4">
            @csrf
            <div x-show="! recovery">
                <label for="code" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.twofa_code') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm tracking-widest text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <div x-show="recovery" x-cloak>
                <label for="recovery_code" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.twofa_recovery') }}</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <button type="submit" class="ll-accent flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-medium hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2">
                {{ __('auth_ui.twofa_verify') }}
            </button>
        </form>

        <button type="button" @click="recovery = ! recovery"
            class="mt-4 w-full text-center text-xs text-accent hover:underline">
            <span x-show="! recovery">{{ __('auth_ui.twofa_use_recovery') }}</span>
            <span x-show="recovery" x-cloak>{{ __('auth_ui.twofa_use_code') }}</span>
        </button>
    </div>
</x-layouts.guest>
