<x-layouts.app :title="__('settings.notifications_heading')">
    @php $input = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm'; @endphp

    <p class="text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.notifications_section') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.notifications_heading') }}</h1>

    <form method="POST" action="{{ route('settings.notifications.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('PUT')

        {{-- Mail server --}}
        <section class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="mail_enabled" value="1" @checked(old('mail_enabled', $settings->mail_enabled)) class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.notify_mail_heading') }}</span>
            </label>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.notify_mail_hint') }}</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.smtp_host') }}</label>
                    <input type="text" name="smtp_host" value="{{ old('smtp_host', $settings->smtp_host) }}" class="{{ $input }}">
                    @error('smtp_host')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.smtp_port') }}</label>
                    <input type="number" name="smtp_port" value="{{ old('smtp_port', $settings->smtp_port) }}" class="{{ $input }}"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.smtp_encryption') }}</label>
                    <select name="smtp_encryption" class="{{ $input }}">
                        <option value="tls" @selected(old('smtp_encryption', $settings->smtp_encryption) === 'tls')>STARTTLS</option>
                        <option value="ssl" @selected(old('smtp_encryption', $settings->smtp_encryption) === 'ssl')>SSL/TLS</option>
                    </select></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.smtp_username') }}</label>
                    <input type="text" name="smtp_username" value="{{ old('smtp_username', $settings->smtp_username) }}" class="{{ $input }}" autocomplete="off"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.smtp_password') }}</label>
                    <input type="password" name="smtp_password" value="" class="{{ $input }}" autocomplete="new-password" placeholder="••••••••">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.notify_secret_keep_hint') }}</p></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.smtp_from_address') }}</label>
                    <input type="email" name="smtp_from_address" value="{{ old('smtp_from_address', $settings->smtp_from_address) }}" class="{{ $input }}">
                    @error('smtp_from_address')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.smtp_from_name') }}</label>
                    <input type="text" name="smtp_from_name" value="{{ old('smtp_from_name', $settings->smtp_from_name) }}" class="{{ $input }}"></div>
            </div>
        </section>

        {{-- NTFY --}}
        <section class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="ntfy_enabled" value="1" @checked(old('ntfy_enabled', $settings->ntfy_enabled)) class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.notify_ntfy_heading') }}</span>
            </label>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.ntfy_url') }}</label>
                    <input type="url" name="ntfy_url" value="{{ old('ntfy_url', $settings->ntfy_url) }}" placeholder="https://ntfy.sh" class="{{ $input }}">
                    @error('ntfy_url')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.ntfy_topic') }}</label>
                    <input type="text" name="ntfy_topic" value="{{ old('ntfy_topic', $settings->ntfy_topic) }}" class="{{ $input }}"></div>
                <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.ntfy_token') }}</label>
                    <input type="password" name="ntfy_token" value="" class="{{ $input }}" autocomplete="off" placeholder="••••••••">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.notify_secret_keep_hint') }}</p></div>
            </div>
        </section>

        {{-- Webhook --}}
        <section class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="webhook_enabled" value="1" @checked(old('webhook_enabled', $settings->webhook_enabled)) class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.notify_webhook_heading') }}</span>
            </label>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.webhook_url') }}</label>
                    <input type="url" name="webhook_url" value="{{ old('webhook_url', $settings->webhook_url) }}" class="{{ $input }}">
                    @error('webhook_url')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.webhook_secret') }}</label>
                    <input type="password" name="webhook_secret" value="" class="{{ $input }}" autocomplete="off" placeholder="••••••••">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.notify_secret_keep_hint') }}</p></div>
            </div>
        </section>

        <div><button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('settings.save') }}</button></div>
    </form>

    {{-- Send a test message over each channel (uses the saved settings above). --}}
    <div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.notify_test_heading') }}</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.notify_test_hint') }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (['mail' => __('settings.notify_mail_heading'), 'ntfy' => 'NTFY', 'webhook' => 'Webhook'] as $channel => $label)
                <form method="POST" action="{{ route('settings.notifications.test') }}">
                    @csrf
                    <input type="hidden" name="channel" value="{{ $channel }}">
                    <button type="submit" class="rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                        {{ __('settings.notify_test_send', ['channel' => $label]) }}
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</x-layouts.app>
