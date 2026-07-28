{{-- Per-user / per-group module allow-list. `modulesValue` = the model's stored value
     (array of enabled keys, or null = all enabled). All boxes checked → stored as null
     (no restriction, future modules auto-enabled); a subset restricts to those modules. --}}
@php
    $moduleList = (array) config('modules.list', []);
    $submitted = old('modules_marker') !== null;
    $current = $modulesValue ?? null; // null = all
@endphp
<div class="sm:col-span-2">
    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('settings.modules_label') }}</label>
    <p class="mb-2 text-[11px] text-gray-400 dark:text-gray-500">{{ __('settings.modules_hint') }}</p>
    <input type="hidden" name="modules_marker" value="1">
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach ($moduleList as $key => $mod)
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="modules[]" value="{{ $key }}"
                    @checked($submitted ? in_array($key, (array) old('modules', []), true) : ($current === null || in_array($key, (array) $current, true)))
                    class="rounded border-gray-300 dark:border-gray-600 text-accent focus:ring-accent">
                {{ __($mod['label']) }}
            </label>
        @endforeach
    </div>
</div>
