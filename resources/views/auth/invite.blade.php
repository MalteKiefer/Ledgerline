<x-layouts.guest :title="__('auth_ui.invite_title')">
    <div class="ll-card">
        <h1 class="text-center text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('auth_ui.invite_title') }}</h1>
        <p class="mt-2 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('auth_ui.invite_subtitle', ['email' => $email]) }}</p>

        @if ($errors->any())
            <x-alert variant="error" class="mt-4" role="alert">{{ $errors->first() }}</x-alert>
        @endif

        <form method="POST" action="{{ route('invite.store', ['invite' => $invite->id, 'token' => $token]) }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="password" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required autofocus
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ __('auth_ui.invite_password_hint') }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.password_confirm') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <x-button variant="primary" size="lg" type="submit" class="w-full">
                {{ __('auth_ui.invite_button') }}
            </x-button>
        </form>
    </div>
</x-layouts.guest>
