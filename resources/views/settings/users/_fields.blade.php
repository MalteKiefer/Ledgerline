@php $isNew = $user === null; @endphp
<div>
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_name') }}</label>
    <input type="text" name="name" required value="{{ old('name', $user?->name) }}"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
</div>
<div>
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_email') }}</label>
    <input type="email" name="email" required value="{{ old('email', $user?->email) }}"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
</div>
<div>
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_role') }}</label>
    <select name="role" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
        <option value="user" @selected(old('role', $user?->role ?? 'user') === 'user')>{{ __('settings.users_role_user') }}</option>
        <option value="admin" @selected(old('role', $user?->role) === 'admin')>{{ __('settings.users_role_admin') }}</option>
    </select>
</div>
@if ($isNew)
    <div>
        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_password') }}</label>
        <input type="password" name="password" autocomplete="new-password"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
        <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ __('settings.users_password_hint') }}</p>
    </div>
@endif
<div>
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_quota_files') }}</label>
    <input type="number" min="0" name="files_quota_mb" value="{{ old('files_quota_mb', $user?->files_quota_mb) }}" placeholder="{{ __('settings.users_quota_default') }}"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
</div>
<div>
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_quota_gallery') }}</label>
    <input type="number" min="0" name="gallery_quota_mb" value="{{ old('gallery_quota_mb', $user?->gallery_quota_mb) }}" placeholder="{{ __('settings.users_quota_default') }}"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
</div>
<div class="sm:col-span-2">
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_devices') }}</label>
    <input type="number" min="1" max="50" name="max_connected_devices" value="{{ old('max_connected_devices', $user?->max_connected_devices) }}" placeholder="{{ __('settings.users_quota_default') }}"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
</div>
