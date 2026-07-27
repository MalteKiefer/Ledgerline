<x-layouts.guest :title="__('auth_ui.forgot_title')">
    <div class="ll-card">
        <h1 class="text-center text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('auth_ui.forgot_title') }}</h1>

        @if (! \App\Models\AppSettings::current()->mail_enabled)
            <x-alert variant="warning" class="mt-4 text-xs" role="alert">{{ __('auth_ui.mail_disabled') }}</x-alert>
        @else
            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">{{ __('auth_ui.forgot_intro') }}</p>

            @if (session('status'))
                <x-alert variant="success" class="mt-4" role="status">{{ __('auth_ui.reset_link_sent') }}</x-alert>
            @endif
            @error('email')
                <x-alert variant="error" class="mt-4" role="alert">{{ $message }}</x-alert>
            @enderror

            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.email') }}</label>
                    <input id="email" name="email" type="email" autocomplete="username" required autofocus value="{{ old('email') }}"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                </div>
                <x-button variant="primary" size="lg" type="submit" class="w-full">
                    {{ __('auth_ui.send_link') }}
                </x-button>
            </form>
        @endif

        <p class="mt-4 text-center text-xs"><a href="{{ route('login') }}" class="text-accent hover:underline">{{ __('auth_ui.back_to_login') }}</a></p>
    </div>
</x-layouts.guest>
