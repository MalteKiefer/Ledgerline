<x-layouts.guest :title="__('auth_ui.verify_title')">
    <div class="ll-card">
        <h1 class="text-center text-xl font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('auth_ui.verify_title') }}</h1>
        <p class="mt-2 text-center text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('auth_ui.verify_intro') }}</p>

        @if (session('status') === 'verification-link-sent')
            <x-alert variant="success" class="mt-4" role="status">{{ __('auth_ui.verify_sent') }}</x-alert>
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
