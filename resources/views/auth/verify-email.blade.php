<x-layouts.guest :title="__('auth_ui.verify_title')">
    <div class="ll-card">
        <h1 class="text-center text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('auth_ui.verify_title') }}</h1>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">{{ __('auth_ui.verify_intro') }}</p>

        @if (session('status') === 'verification-link-sent')
            <div class="mt-4 rounded-md border border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950 px-3 py-2 text-sm text-green-700 dark:text-green-300" role="status">{{ __('auth_ui.verify_sent') }}</div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <x-button variant="primary" size="lg" type="submit" class="w-full">
                {{ __('auth_ui.verify_resend') }}
            </x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full text-center text-sm text-accent hover:underline">{{ __('auth_ui.sign_out') }}</button>
        </form>
    </div>
</x-layouts.guest>
