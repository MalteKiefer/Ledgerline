@php $g = $group; @endphp
<div class="sm:col-span-2">
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.groups_name') }}</label>
    <input type="text" name="name" required value="{{ old('name', $g?->name) }}"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
</div>
<div>
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_quota_files') }}</label>
    <input type="number" min="0" name="files_quota_mb" value="{{ old('files_quota_mb', $g?->files_quota_mb) }}" placeholder="{{ __('settings.groups_no_limit') }}"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
</div>
<div>
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_quota_gallery') }}</label>
    <input type="number" min="0" name="gallery_quota_mb" value="{{ old('gallery_quota_mb', $g?->gallery_quota_mb) }}" placeholder="{{ __('settings.groups_no_limit') }}"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
</div>
<div>
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_devices') }}</label>
    <input type="number" min="1" max="50" name="max_connected_devices" value="{{ old('max_connected_devices', $g?->max_connected_devices) }}" placeholder="{{ __('settings.groups_no_limit') }}"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
</div>
<div class="sm:col-span-2">
    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input type="hidden" name="shareable" value="0">
        <input type="checkbox" name="shareable" value="1" @checked(old('shareable', $g?->shareable)) class="rounded border-gray-300 dark:border-gray-600 text-accent focus:ring-accent">
        {{ __('settings.groups_shareable_label') }}
    </label>
    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ __('settings.groups_shareable_hint') }}</p>
</div>
@include('settings._module_toggles', ['modulesValue' => $g?->modules])
@php
    $memberIds = $g ? $g->members->pluck('id')->all() : [];
    $userOptions = ($users ?? collect())->map(fn ($u) => ['id' => $u->id, 'label' => $u->name, 'sub' => $u->email])->all();
@endphp
<div class="sm:col-span-2">
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.groups_members') }}</label>
    @include('settings._tag_select', [
        'fieldName' => 'members',
        'options' => $userOptions,
        'selected' => old('members', $memberIds),
        'placeholder' => __('settings.groups_members_add'),
    ])
</div>
