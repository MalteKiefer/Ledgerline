<x-layouts.guest :title="__('auth_ui.login_title')">
    <div class="ll-card">
        <h1 class="text-center text-xl font-semibold text-md-on-surface dark:text-md-on-surface">Ledgerline</h1>
        <p class="mt-2 text-center text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('auth_ui.login_subtitle') }}</p>

        @if (session('status'))
            <x-alert variant="success" class="mt-4" role="status">{{ session('status') }}</x-alert>
        @endif
        @error('email')
            <x-alert variant="error" class="mt-4" role="alert">{{ $message }}</x-alert>
        @enderror

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-xs font-medium text-md-on-surface-var dark:text-md-on-surface-var mb-1">{{ __('auth_ui.email') }}</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus value="{{ old('email') }}"
                    class="w-full rounded-lg border border-md-outline-variant dark:border-md-outline-variant bg-white dark:bg-md-surface px-3 py-2 text-sm text-md-on-surface dark:text-md-on-surface focus:border-accent focus:ring-accent">
            </div>
            <div>
                <label for="password" class="block text-xs font-medium text-md-on-surface-var dark:text-md-on-surface-var mb-1">{{ __('auth_ui.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="w-full rounded-lg border border-md-outline-variant dark:border-md-outline-variant bg-white dark:bg-md-surface px-3 py-2 text-sm text-md-on-surface dark:text-md-on-surface focus:border-accent focus:ring-accent">
            </div>
            <label class="flex items-center gap-2 text-xs text-md-on-surface-var dark:text-md-on-surface-var">
                <input type="checkbox" name="remember" class="rounded border-md-outline-variant dark:border-md-outline-variant text-accent focus:ring-accent">
                {{ __('auth_ui.remember') }}
            </label>
            <x-button variant="primary" size="lg" type="submit" class="w-full">
                {{ __('auth_ui.sign_in') }}
            </x-button>
        </form>


        <div class="mt-4 flex items-center justify-between text-xs">
            <a href="{{ route('password.request') }}" class="text-accent hover:underline">{{ __('auth_ui.forgot') }}</a>
            @if (\App\Providers\FortifyServiceProvider::registrationOpen())
                <span class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('auth_ui.no_account') }}
                    <a href="{{ route('register') }}" class="text-accent hover:underline">{{ __('auth_ui.register_link') }}</a>
                </span>
            @endif
        </div>
    </div>
</x-layouts.guest>
