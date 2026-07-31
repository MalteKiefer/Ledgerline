<x-layouts.app :title="__('settings.files_limits_heading')">
    <x-page-heading :title="__('settings.files_limits_heading')" :subtitle="__('settings.files_limits_hint')" />

    <form method="POST" action="{{ route('settings.files.limits.update') }}" class="mt-6 ll-card">
        @csrf
        @method('PUT')
        {{-- Empty = use the built-in default. --}}
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                'files_quota_mb' => ['settings.files_quota', 'files.quota_mb'],
                'files_max_upload_mb' => ['settings.files_max_upload', 'files.max_upload_mb'],
                'files_blob_orphan_grace_hours' => ['settings.files_orphan_grace', 'files.blob_orphan_grace_hours'],
            ] as $field => [$label, $cfg])
                <label class="block text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ __($label) }}</span>
                    <input type="number" min="0" name="{{ $field }}" value="{{ old($field, $limits->{$field}) }}"
                        placeholder="{{ __('settings.files_default_ph', ['n' => config($cfg)]) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    @error($field)<span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
                </label>
            @endforeach
        </div>

        <div class="mt-4">
            <x-button variant="primary" type="submit">{{ __('common.save') }}</x-button>
        </div>
    </form>
</x-layouts.app>
