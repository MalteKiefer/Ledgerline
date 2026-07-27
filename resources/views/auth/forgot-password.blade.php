<x-layouts.guest :title="__('auth_ui.forgot_title')">
    <div class="ll-card">
        <h1 class="text-center text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('auth_ui.forgot_title') }}</h1>

        @if (! \App\Models\AppSettings::current()->mail_enabled)
            <div class="mt-4 rounded-md border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950 px-3 py-2 text-xs text-amber-800 dark:text-amber-300" role="alert">
                {{ __('auth_ui.mail_disabled') }}
            </div>
        @else
            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">{{ __('auth_ui.forgot_intro') }}</p>

            @if (session('status'))
                <div class="mt-4 rounded-md border border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950 px-3 py-2 text-sm text-green-700 dark:text-green-300" role="status">{{ __('auth_ui.reset_link_sent') }}</div>
            @endif
            @error('email')
                <div class="mt-4 rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300" role="alert">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('auth_ui.email') }}</label>
                    <input id="email" name="email" type="email" autocomplete="username" required autofocus value="{{ old('email') }}"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                </div>
                <button type="submit" class="ll-accent flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-medium hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2">
                    {{ __('auth_ui.send_link') }}
                </button>
            </form>
        @endif

        <p class="mt-4 text-center text-xs"><a href="{{ route('login') }}" class="text-accent hover:underline">{{ __('auth_ui.back_to_login') }}</a></p>
    </div>
</x-layouts.guest>
