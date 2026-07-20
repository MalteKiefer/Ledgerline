<x-layouts.app :title="__('settings.paperless_heading')">
    @php $input = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm'; @endphp

    <p class="text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.paperless_section') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.paperless_heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('settings.paperless_desc') }}</p>

    <div x-data="paperlessSettings({
            testUrl: '{{ route('settings.paperless.test') }}',
            syncUrl: '{{ route('settings.paperless.sync') }}',
            testing: @js(__('settings.paperless_testing')),
            syncing: @js(__('settings.paperless_syncing')),
            failed: @js(__('settings.paperless_test_failed')),
            counts: @js($counts),
        })">
        {{-- Connection --}}
        <form method="POST" action="{{ route('settings.paperless.update') }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-4 sm:p-6 shadow-sm">
            @csrf
            @method('PUT')

            <label class="flex items-center gap-2">
                <input type="checkbox" name="paperless_enabled" value="1" @checked(old('paperless_enabled', $settings->paperless_enabled))
                    class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-accent">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.paperless_enabled') }}</span>
            </label>

            <div class="mt-4">
                <label for="paperless_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.paperless_url') }}</label>
                <input type="url" id="paperless_url" name="paperless_url" x-ref="url" placeholder="https://paperless.example.com"
                    value="{{ old('paperless_url', $settings->paperless_url) }}" class="{{ $input }}">
                @error('paperless_url')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="mt-4">
                <label for="paperless_token" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.paperless_token') }}</label>
                <input type="password" id="paperless_token" name="paperless_token" x-ref="token" autocomplete="off"
                    placeholder="{{ $settings->paperless_token ? '••••••••' : '' }}" class="{{ $input }}">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.paperless_token_hint') }}</p>
                @error('paperless_token')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('settings.save') }}</button>
                <button type="button" @click="test()" :disabled="busy"
                    class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-accent hover:text-accent disabled:opacity-50" x-text="busy === 'test' ? config.testing : @js(__('settings.paperless_test'))"></button>
                <span x-show="testResult" x-cloak :class="testOk ? 'text-green-600' : 'text-red-600 dark:text-red-400'" class="text-sm w-full break-words" x-text="testResult"></span>
            </div>
        </form>

        {{-- Cached quick-pick terms --}}
        <div class="mt-6 ll-card">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.paperless_cache_heading') }}</h2>
                <button type="button" @click="sync()" :disabled="busy"
                    class="rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-accent hover:text-accent disabled:opacity-50" x-text="busy === 'sync' ? config.syncing : @js(__('settings.paperless_sync_now'))"></button>
            </div>
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
                <div class="rounded-md bg-gray-50 dark:bg-gray-800 p-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('settings.paperless_tags') }}</div>
                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="counts.tag"></div>
                </div>
                <div class="rounded-md bg-gray-50 dark:bg-gray-800 p-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('settings.paperless_document_types') }}</div>
                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="counts.document_type"></div>
                </div>
                <div class="rounded-md bg-gray-50 dark:bg-gray-800 p-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('settings.paperless_correspondents') }}</div>
                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="counts.correspondent"></div>
                </div>
            </div>
            @if ($settings->paperless_synced_at)
                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">{{ __('settings.paperless_synced_at', ['time' => $settings->paperless_synced_at->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}</p>
            @else
                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">{{ __('settings.paperless_never_synced') }}</p>
            @endif
            <p x-show="syncError" x-cloak class="mt-2 text-sm text-red-600 dark:text-red-400" x-text="syncError"></p>
        </div>
    </div>
</x-layouts.app>
