<x-layouts.guest :title="__('auth_ui.login_title')">
    <div class="ll-card">
        <h1 class="text-center text-xl font-semibold text-gray-900 dark:text-gray-100">Ledgerline</h1>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">{{ __('auth_ui.login_subtitle') }}</p>

        @if (session('status'))
            <div class="mt-4 rounded-md border border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950 px-3 py-2 text-sm text-green-700 dark:text-green-300" role="status">{{ session('status') }}</div>
        @endif
        @error('email')
            <div class="mt-4 rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300" role="alert">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.email') }}</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus value="{{ old('email') }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <div>
                <label for="password" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <input type="checkbox" name="remember" class="rounded border-gray-300 dark:border-gray-600 text-accent focus:ring-accent">
                {{ __('auth_ui.remember') }}
            </label>
            <x-button variant="primary" size="lg" type="submit" class="w-full">
                {{ __('auth_ui.sign_in') }}
            </x-button>
        </form>

        <div class="mt-4 flex items-center justify-between text-xs">
            <a href="{{ route('password.request') }}" class="text-accent hover:underline">{{ __('auth_ui.forgot') }}</a>
            @if (\App\Providers\FortifyServiceProvider::registrationOpen())
                <span class="text-gray-500 dark:text-gray-400">{{ __('auth_ui.no_account') }}
                    <a href="{{ route('register') }}" class="text-accent hover:underline">{{ __('auth_ui.register_link') }}</a>
                </span>
            @endif
        </div>
    </div>
</x-layouts.guest>
