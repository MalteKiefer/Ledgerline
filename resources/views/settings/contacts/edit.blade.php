<x-layouts.app :title="__('contacts.heading')">
    <p class="text-sm text-md-on-surface-var dark:text-md-on-surface-var">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('contacts.heading') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('contacts.heading') }}</h1>
    <p class="mt-1 text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('contacts.subheading') }}</p>


    <div class="mt-6">
        @include('settings.partials.dav-sync')
    </div>

</x-layouts.app>
