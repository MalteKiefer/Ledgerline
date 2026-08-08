@php $g = $group; @endphp
<div class="sm:col-span-2">
    <label class="block text-xs font-medium text-md-on-surface-var dark:text-md-on-surface-var mb-1">{{ __('settings.groups_name') }}</label>
    <input type="text" name="name" required value="{{ old('name', $g?->name) }}"
        class="w-full rounded-lg border border-md-outline-variant dark:border-md-outline-variant bg-white dark:bg-md-surface px-3 py-2 text-sm text-md-on-surface dark:text-md-on-surface focus:border-accent focus:ring-accent">
</div>
<div class="sm:col-span-2">
    <label class="block text-xs font-medium text-md-on-surface-var dark:text-md-on-surface-var mb-1">{{ __('settings.users_devices') }}</label>
    <input type="number" min="1" max="50" name="max_connected_devices" value="{{ old('max_connected_devices', $g?->max_connected_devices) }}" placeholder="{{ __('settings.groups_no_limit') }}"
        class="w-full rounded-lg border border-md-outline-variant dark:border-md-outline-variant bg-white dark:bg-md-surface px-3 py-2 text-sm text-md-on-surface dark:text-md-on-surface focus:border-accent focus:ring-accent">
</div>
<div class="sm:col-span-2">
    <label class="inline-flex items-center gap-2 text-sm text-md-on-surface-var dark:text-md-on-surface-var">
        <input type="hidden" name="shareable" value="0">
        <input type="checkbox" name="shareable" value="1" @checked(old('shareable', $g?->shareable)) class="rounded border-md-outline-variant dark:border-md-outline-variant text-accent focus:ring-accent">
        {{ __('settings.groups_shareable_label') }}
    </label>
    <p class="mt-1 text-[11px] text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.groups_shareable_hint') }}</p>
</div>
@include('settings._module_toggles', ['modulesValue' => $g?->modules])
@php
    $memberIds = $g ? $g->members->pluck('id')->all() : [];
    $userOptions = ($users ?? collect())->map(fn ($u) => ['id' => $u->id, 'label' => $u->name, 'sub' => $u->email])->all();
@endphp
<div class="sm:col-span-2">
    <label class="block text-xs font-medium text-md-on-surface-var dark:text-md-on-surface-var mb-1">{{ __('settings.groups_members') }}</label>
    @include('settings._tag_select', [
        'fieldName' => 'members',
        'options' => $userOptions,
        'selected' => old('members', $memberIds),
        'placeholder' => __('settings.groups_members_add'),
    ])
</div>
