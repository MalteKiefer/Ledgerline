<x-layouts.app :title="__('settings.security_section')">
    <x-page-heading :title="__('settings.security_section')" :subtitle="__('settings.security_desc')" />

    <form method="POST" action="{{ route('settings.security.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.security_lock_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.security_lock_hint') }}</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.security_remember_days') }}
                    <input type="number" name="vault_remember_days" value="{{ old('vault_remember_days', $rememberDays) }}" min="1" max="365" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    @error('vault_remember_days')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.security_idle_minutes') }}
                    <input type="number" name="vault_public_idle_minutes" value="{{ old('vault_public_idle_minutes', $idleMinutes) }}" min="1" max="1440" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    @error('vault_public_idle_minutes')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
            </div>
            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">{{ __('settings.security_zk_note') }}</p>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.security_devices_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.security_devices_hint') }}</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.security_max_devices') }}
                    <input type="number" name="max_connected_devices" value="{{ old('max_connected_devices', $maxDevices) }}" min="1" max="100" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    @error('max_connected_devices')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <x-button variant="primary" type="submit">{{ __('common.save') }}</x-button>
        </div>
    </form>
</x-layouts.app>
