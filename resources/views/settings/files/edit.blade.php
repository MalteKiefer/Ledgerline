<x-layouts.app :title="__('settings.files_section')">
    <x-page-heading :title="__('settings.files_section')" :subtitle="__('settings.files_desc')" />

    <form method="POST" action="{{ route('settings.files.update') }}" class="mt-6 max-w-lg rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
        @csrf
        @method('PUT')
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="file_max_versions">{{ __('settings.files_max_versions') }}</label>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.files_max_versions_hint') }}</p>
        <select id="file_max_versions" name="file_max_versions" class="mt-2 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:w-32">
            @for ($i = 1; $i <= 10; $i++)
                <option value="{{ $i }}" @selected($maxVersions === $i)>{{ $i }}</option>
            @endfor
        </select>
        @error('file_max_versions')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        <div class="mt-4">
            <x-button variant="primary" type="submit">{{ __('common.save') }}</x-button>
        </div>
    </form>
</x-layouts.app>
