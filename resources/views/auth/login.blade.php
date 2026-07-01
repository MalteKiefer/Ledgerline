<x-layouts.guest :title="__('pages.login.title')">
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-center text-xl font-semibold text-gray-900">Ledgerline</h1>
        <p class="mt-2 text-center text-sm text-gray-600">
            {{ __('pages.login.subtitle') }}
        </p>

        @error('pocketid')
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                role="alert">
                {{ $message }}
            </div>
        @enderror

        <a href="{{ route('auth.redirect') }}"
            class="mt-6 flex w-full items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            {{ __('pages.login.continue') }}
        </a>
    </div>
</x-layouts.guest>
