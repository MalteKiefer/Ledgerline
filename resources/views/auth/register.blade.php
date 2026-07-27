<x-layouts.guest :title="__('auth_ui.register_title')">
    <div class="ll-card">
        <h1 class="text-center text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('auth_ui.register_title') }}</h1>

        @if ($errors->any())
            <x-alert variant="error" class="mt-4" role="alert">
                {{ $errors->first() }}
            </x-alert>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.name') }}</label>
                <input id="name" name="name" type="text" required autofocus value="{{ old('name') }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <div>
                <label for="email" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.email') }}</label>
                <input id="email" name="email" type="email" autocomplete="username" required value="{{ old('email') }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <div>
                <label for="password" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <div>
                <label for="password_confirmation" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.password_confirm') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
            </div>
            <x-button variant="primary" size="lg" type="submit" class="w-full">
                {{ __('auth_ui.create_account') }}
            </x-button>
        </form>

        <p class="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">{{ __('auth_ui.have_account') }}
            <a href="{{ route('login') }}" class="text-accent hover:underline">{{ __('auth_ui.login_link') }}</a>
        </p>
    </div>
</x-layouts.guest>
