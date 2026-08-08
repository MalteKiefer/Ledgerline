<x-layouts.guest :title="__('auth_ui.confirm_title')">
    <div class="ll-card">
        <h1 class="text-center text-xl font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('auth_ui.confirm_title') }}</h1>
        <p class="mt-2 text-center text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('auth_ui.confirm_intro') }}</p>

        @error('password')
            <x-alert variant="error" class="mt-4" role="alert">{{ $message }}</x-alert>
        @enderror

        <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="password" class="block text-xs font-medium text-md-on-surface-var dark:text-md-on-surface-var mb-1">{{ __('auth_ui.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required autofocus
                    class="w-full rounded-lg border border-md-outline-variant dark:border-md-outline-variant bg-white dark:bg-md-surface px-3 py-2 text-sm text-md-on-surface dark:text-md-on-surface focus:border-accent focus:ring-accent">
            </div>
            <x-button variant="primary" size="lg" type="submit" class="w-full">
                {{ __('auth_ui.confirm_button') }}
            </x-button>
        </form>
    </div>
</x-layouts.guest>
