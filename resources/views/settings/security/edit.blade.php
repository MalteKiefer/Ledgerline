<x-layouts.app :title="__('settings.security_heading')">
    @php $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm'; @endphp

    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.security_section') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('settings.security_heading') }}</h1>

    <form method="POST" action="{{ route('settings.security.update') }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.vault_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('settings.vault_hint') }}</p>
        <div class="mt-3 sm:max-w-xs">
            <label for="vault_idle_minutes" class="block text-sm font-medium text-gray-700">{{ __('settings.vault_idle_minutes') }}</label>
            <input type="number" min="1" max="120" id="vault_idle_minutes" name="vault_idle_minutes" value="{{ old('vault_idle_minutes', $company->vault_idle_minutes ?? 10) }}" class="{{ $input }}">
            @error('vault_idle_minutes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="mt-4 sm:max-w-xs">
            <label for="mail_sync_minutes" class="block text-sm font-medium text-gray-700">{{ __('settings.mail_sync_minutes') }}</label>
            <input type="number" min="5" max="120" id="mail_sync_minutes" name="mail_sync_minutes" value="{{ old('mail_sync_minutes', $company->mail_sync_minutes ?? 5) }}" class="{{ $input }}">
            <p class="mt-1 text-xs text-gray-500">{{ __('settings.mail_sync_minutes_hint') }}</p>
            @error('mail_sync_minutes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="mt-4">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('settings.save') }}</button>
        </div>
    </form>
</x-layouts.app>
