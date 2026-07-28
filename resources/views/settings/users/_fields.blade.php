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
    <div x-data="pwStrength(@js([__('settings.users_pw_s0'), __('settings.users_pw_s1'), __('settings.users_pw_s2'), __('settings.users_pw_s3'), __('settings.users_pw_s4')]))">
        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_password') }}</label>
        <input type="password" name="password" autocomplete="new-password" @input.debounce.200ms="score($event.target.value)"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
        <div x-show="shown" x-cloak class="mt-1.5">
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-black/[0.08] dark:bg-white/10">
                <div class="h-full rounded-full transition-all" :style="{ width: pct + '%', background: color }"></div>
            </div>
            <p class="mt-1 text-[11px]" :style="{ color }" x-text="label"></p>
        </div>
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
@if (! ($groups ?? collect())->isEmpty())
    @php
        $memberIds = $user ? $user->memberGroups->pluck('id')->all() : [];
        $groupOptions = $groups->map(fn ($g) => ['id' => $g->id, 'label' => $g->name, 'sub' => ''])->all();
    @endphp
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.users_groups') }}</label>
        @include('settings._tag_select', [
            'fieldName' => 'groups',
            'options' => $groupOptions,
            'selected' => old('groups', $memberIds),
            'placeholder' => __('settings.users_groups_add'),
        ])
    </div>
@endif

@include('settings._module_toggles', ['modulesValue' => $user?->modules])
