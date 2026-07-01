<x-layouts.app :title="__('settings.index_title')">
    <h1 class="text-2xl font-semibold text-gray-900">{{ __('settings.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('settings.subheading') }}</p>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <a href="{{ route('settings.company.edit') }}"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <h2 class="text-base font-semibold text-gray-900">{{ __('settings.company_profile') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('settings.company_profile_desc') }}</p>
        </a>
        <a href="{{ route('settings.tags.index') }}"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <h2 class="text-base font-semibold text-gray-900">{{ __('settings.tags') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('settings.tags_desc') }}</p>
        </a>
        <a href="{{ route('settings.units.index') }}"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <h2 class="text-base font-semibold text-gray-900">{{ __('settings.units') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('settings.units_desc') }}</p>
        </a>
    </div>
</x-layouts.app>
