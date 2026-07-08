<x-layouts.app :title="__('settings.files_section')">
    <x-page-heading :title="__('settings.files_section')" :subtitle="__('settings.files_desc')" />

    <form method="POST" action="{{ route('settings.files.update') }}" class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
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

        @if ($isAdmin)
            {{-- Global file limits (admin only). Empty = use the built-in default. --}}
            <div class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.files_limits_heading') }}</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.files_limits_hint') }}</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'files_quota_mb' => ['settings.files_quota', 'files.quota_mb'],
                        'files_max_upload_mb' => ['settings.files_max_upload', 'files.max_upload_mb'],
                        'files_trash_retention_days' => ['settings.files_trash_retention', 'files.trash_retention_days'],
                        'files_archive_max_entries' => ['settings.files_archive_entries', 'files.archive_max_entries'],
                        'files_archive_max_mb' => ['settings.files_archive_mb', 'files.archive_max_mb'],
                        'files_blob_orphan_grace_hours' => ['settings.files_orphan_grace', 'files.blob_orphan_grace_hours'],
                    ] as $field => [$label, $cfg])
                        <label class="block text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ __($label) }}</span>
                            <input type="number" min="0" name="{{ $field }}" value="{{ old($field, $limits->{$field}) }}"
                                placeholder="{{ __('settings.files_default_ph', ['n' => config($cfg)]) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            @error($field)<span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-4">
            <x-button variant="primary" type="submit">{{ __('common.save') }}</x-button>
        </div>
    </form>

    @if ($isAdmin)
        {{-- Rebuild the file full-text search index (admin, queued) --}}
        <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.files_reindex_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.files_reindex_hint') }}</p>
            <form method="POST" action="{{ route('settings.files.reindex') }}" class="mt-3">
                @csrf
                <x-button variant="secondary" type="submit">{{ __('settings.files_reindex_action') }}</x-button>
            </form>
        </div>
    @endif

    {{-- WebDAV access (same DAV login as Contacts/Calendar) --}}
    <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.files_webdav_heading') }}</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.files_webdav_hint') }}</p>
        @if ($davUsername)
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-[8rem_1fr]">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('contacts.webdav_url') }}</dt>
                <dd class="select-all break-all font-mono text-gray-900 dark:text-gray-100">{{ rtrim(url('/dav'), '/') }}/files/{{ $davUsername }}/</dd>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('contacts.username') }}</dt>
                <dd class="select-all font-mono text-gray-900 dark:text-gray-100">{{ $davUsername }}</dd>
            </dl>
        @endif
        <a href="{{ route('settings.contacts.edit') }}" class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
            {{ __('settings.files_webdav_manage') }}
        </a>
    </div>
</x-layouts.app>
