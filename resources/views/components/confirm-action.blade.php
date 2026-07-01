@props([
    'action',
    'method' => 'POST',
    'trigger' => null,
    'triggerClass' => 'rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50',
    'title' => null,
    'message' => null,
    'confirm' => null,
    'confirmClass' => 'rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700',
])

<div x-data="{ open: false }" class="inline">
    <button type="button" @click="open = true" class="{{ $triggerClass }}">{{ $trigger ?? __('common.delete') }}</button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog" aria-modal="true" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="open = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ $title ?? __('common.confirm_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $message ?? __('common.confirm_message') }}</p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="open = false"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <form method="POST" action="{{ $action }}">
                        @csrf
                        @unless ($method === 'POST')
                            @method($method)
                        @endunless
                        {{ $slot }}
                        <button type="submit" class="{{ $confirmClass }}">{{ $confirm ?? __('common.delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
