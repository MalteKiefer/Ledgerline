<x-layouts.share :title="__('share.title')">
    <div class="mx-auto mt-16 max-w-md px-4">
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
            <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-4 text-sm text-gray-600">{{ __('share.password_prompt') }}</p>
            @if ($error)
                <p class="mt-2 text-sm text-red-600">{{ __('share.wrong_password') }}</p>
            @endif
            <form method="POST" action="{{ route('shares.unlock', $share) }}" class="mt-5">
                @csrf
                <input type="password" name="password" required autofocus placeholder="{{ __('share.password_label') }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                <button type="submit" class="mt-4 w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('share.unlock') }}</button>
            </form>
        </div>
    </div>
</x-layouts.share>
