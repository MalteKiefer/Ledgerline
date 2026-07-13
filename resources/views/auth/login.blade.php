<x-layouts.guest :title="__('pages.login.title')">
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h1 class="text-center text-xl font-semibold text-gray-900 dark:text-gray-100">Ledgerline</h1>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
            {{ __('pages.login.subtitle') }}
        </p>

        @error('pocketid')
            <div class="mt-4 rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300"
                role="alert">
                {{ $message }}
            </div>
        @enderror

        <div x-data="{ pub: false }">
            <a :href="pub ? '{{ route('auth.redirect') }}?public=1' : '{{ route('auth.redirect') }}'"
                class="mt-6 flex w-full items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                {{ __('pages.login.continue') }}
            </a>
            <label class="mt-3 flex items-center justify-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <input type="checkbox" x-model="pub" class="rounded border-gray-300 dark:border-gray-600 text-gray-800 focus:ring-0">
                {{ __('pages.login.public_computer') }}
            </label>
        </div>
    </div>
</x-layouts.guest>
