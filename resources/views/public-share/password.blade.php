<x-layouts.share :title="__('shares.public_password_prompt')">
    <div class="mx-auto flex min-h-[60vh] max-w-sm flex-col justify-center px-4 py-8">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center gap-2 text-gray-900">
                <x-icon name="lock-closed" class="h-5 w-5" />
                <h1 class="text-base font-semibold">{{ __('shares.public_password_prompt') }}</h1>
            </div>
            <form method="POST" action="{{ route('public-share.album.unlock', $share->token) }}" class="space-y-3">
                @csrf
                <input type="password" name="password" autofocus required
                    placeholder="{{ __('shares.public_password_placeholder') }}"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                @if ($error)
                    <p class="text-xs text-red-600">{{ __('shares.public_wrong_password') }}</p>
                @endif
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    {{ __('shares.public_unlock') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts.share>
