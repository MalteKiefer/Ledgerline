<x-layouts.app :title="__('settings.security_section')">
    <x-page-heading :title="__('settings.security_section')" :subtitle="__('settings.security_desc')" />

    <form method="POST" action="{{ route('settings.security.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <div class="ll-card">
            <h2 class="text-sm font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('settings.security_devices_heading') }}</h2>
            <p class="mt-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.security_devices_hint') }}</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.security_max_devices') }}
                    <input type="number" name="max_connected_devices" value="{{ old('max_connected_devices', $maxDevices) }}" min="1" max="100" class="mt-1 block w-full rounded-md border-md-outline-variant dark:border-md-outline-variant dark:bg-md-surface-2 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    @error('max_connected_devices')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <x-button variant="primary" type="submit">{{ __('common.save') }}</x-button>
        </div>
    </form>
</x-layouts.app>
