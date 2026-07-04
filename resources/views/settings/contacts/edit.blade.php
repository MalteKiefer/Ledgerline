<x-layouts.app :title="__('contacts.heading')">
    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('contacts.heading') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('contacts.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('contacts.subheading') }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    {{-- One-time password display right after generation --}}
    @if (session('dav_password'))
        <div class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm">
            <p class="font-medium text-amber-800">{{ __('contacts.password_once') }}</p>
            <dl class="mt-3 grid gap-2 sm:grid-cols-[8rem_1fr]">
                <dt class="text-gray-500">{{ __('contacts.username') }}</dt>
                <dd class="font-mono text-gray-900">{{ session('dav_username') }}</dd>
                <dt class="text-gray-500">{{ __('contacts.password') }}</dt>
                <dd class="font-mono text-gray-900 break-all select-all">{{ session('dav_password') }}</dd>
            </dl>
        </div>
    @endif

    <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @if ($credential === null)
            <p class="text-sm text-gray-600">{{ __('contacts.not_enabled') }}</p>
            <form method="POST" action="{{ route('settings.contacts.generate') }}" class="mt-4">
                @csrf
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('contacts.enable') }}</button>
            </form>
        @else
            <dl class="grid gap-2 sm:grid-cols-[8rem_1fr] text-sm">
                <dt class="text-gray-500">{{ __('contacts.dav_url') }}</dt>
                <dd class="font-mono text-gray-900 break-all select-all">{{ $davUrl }}</dd>
                <dt class="text-gray-500">{{ __('contacts.username') }}</dt>
                <dd class="font-mono text-gray-900 select-all">{{ $credential->username }}</dd>
            </dl>
            <p class="mt-3 text-xs text-gray-500">{{ __('contacts.setup_hint') }}</p>
            <form method="POST" action="{{ route('settings.contacts.generate') }}" class="mt-4">
                @csrf
                <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('contacts.regenerate') }}</button>
            </form>
        @endif
    </section>
</x-layouts.app>
