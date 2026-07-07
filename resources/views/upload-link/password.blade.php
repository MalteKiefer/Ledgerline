<x-layouts.minimal :title="__('upload_links.title')">
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <div class="mb-4 flex items-center gap-2 text-gray-900 dark:text-gray-100">
            <x-icon name="lock-closed" class="h-5 w-5" />
            <h1 class="text-base font-semibold">{{ __('shares.public_password_prompt') }}</h1>
        </div>
        <form method="POST" action="{{ route('upload-link.unlock', $token) }}" class="space-y-3">
            @csrf
            <input type="password" name="password" autofocus required placeholder="{{ __('shares.public_password_placeholder') }}"
                class="w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            @if ($error)<p class="text-xs text-red-600">{{ __('shares.public_wrong_password') }}</p>@endif
            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-800">{{ __('shares.public_unlock') }}</button>
        </form>
    </div>
</x-layouts.minimal>
