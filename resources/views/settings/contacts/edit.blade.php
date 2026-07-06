<x-layouts.app :title="__('contacts.heading')">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('contacts.heading') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('contacts.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('contacts.subheading') }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-700 dark:text-green-300">{{ session('status') }}</div>
    @endif

    <div class="mt-6">
        @include('settings.partials.dav-sync')
    </div>

</x-layouts.app>
