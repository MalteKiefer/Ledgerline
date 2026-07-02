<x-layouts.app :title="__('settings.index_title')">
    <h1 class="text-2xl font-semibold text-gray-900">{{ __('settings.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('settings.subheading') }}</p>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <a href="{{ route('settings.gallery.edit') }}"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <h2 class="text-base font-semibold text-gray-900">{{ __('settings.gallery_section') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('settings.gallery_desc') }}</p>
        </a>
        <a href="{{ route('settings.security.edit') }}"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <h2 class="text-base font-semibold text-gray-900">{{ __('settings.security_section') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('settings.security_desc') }}</p>
        </a>
    </div>
</x-layouts.app>
