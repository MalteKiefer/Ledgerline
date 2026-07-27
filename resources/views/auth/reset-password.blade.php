<x-layouts.guest :title="__('auth_ui.reset_title')">
    <div class="ll-card">
        <h1 class="text-center text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('auth_ui.reset_title') }}</h1>

        @if ($errors->any())
            <div class="mt-4 rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div>
                <label for="email" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.email') }}</label>
                <input id="email" name="email" type="email" autocomplete="username" required value="{{ old('email', $request->email) }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <div>
                <label for="password" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required autofocus
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <div>
                <label for="password_confirmation" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.password_confirm') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <button type="submit" class="ll-accent flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-medium hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2">
                {{ __('auth_ui.reset_button') }}
            </button>
        </form>
    </div>
</x-layouts.guest>
