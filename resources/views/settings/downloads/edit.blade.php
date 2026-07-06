<x-layouts.app :title="__('downloads.settings_page.heading')">
    @php $input = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm'; @endphp

    <p class="text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('downloads.settings_page.heading') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('downloads.settings_page.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('downloads.settings_page.subheading') }}</p>


    <form method="POST" action="{{ route('settings.downloads.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('PUT')

        <section class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('downloads.settings_page.zip_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('downloads.settings_page.zip_hint') }}</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('downloads.settings_page.files_max') }}</label>
                    <input type="number" min="0" max="1048576" name="export_files_max_zip_mb"
                        value="{{ old('export_files_max_zip_mb', $settings->export_files_max_zip_mb) }}" class="{{ $input }}">
                    @error('export_files_max_zip_mb')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('downloads.settings_page.gallery_max') }}</label>
                    <input type="number" min="0" max="1048576" name="export_gallery_max_zip_mb"
                        value="{{ old('export_gallery_max_zip_mb', $settings->export_gallery_max_zip_mb) }}" class="{{ $input }}">
                    @error('export_gallery_max_zip_mb')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('downloads.settings_page.notify_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('downloads.settings_page.notify_hint') }}</p>
            <div class="mt-3 space-y-2">
                @foreach (['desktop', 'ntfy', 'mail', 'webhook'] as $channel)
                    @php $field = 'export_notify_'.$channel; @endphp
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $settings->{$field}))
                            class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                        <span class="text-sm text-gray-800 dark:text-gray-200">{{ __('downloads.settings_page.notify_'.$channel) }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-gray-900 dark:bg-gray-100 dark:text-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:hover:bg-white">
                {{ __('downloads.settings_page.save') }}
            </button>
        </div>
    </form>
</x-layouts.app>
