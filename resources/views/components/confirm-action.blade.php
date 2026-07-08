@props([
    'action',
    'method' => 'POST',
    'trigger' => null,
    'triggerClass' => 'rounded-md border border-red-300 bg-white dark:bg-gray-900 px-4 py-2 text-sm font-medium text-red-700 dark:text-red-300 hover:bg-red-50',
    'title' => null,
    'message' => null,
    'confirm' => null,
    'confirmClass' => 'rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700',
])

<div x-data="{ open: false, busy: false }" class="inline">
    <button type="button" @click="open = true" class="{{ $triggerClass }}">{{ $trigger ?? __('common.delete') }}</button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog" aria-modal="true" @keydown.escape.window="! busy && (open = false)">
            <div class="absolute inset-0 bg-gray-900/40" @click="! busy && (open = false)"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title ?? __('common.confirm_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $message ?? __('common.confirm_message') }}</p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="open = false" :disabled="busy"
                        class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50">{{ __('common.cancel') }}</button>
                    {{-- A full-page submit can take a while (e.g. emptying a large trash);
                         flip the confirm button to a spinner so the action registers. --}}
                    <form method="POST" action="{{ $action }}" @submit="busy = true">
                        @csrf
                        @unless ($method === 'POST')
                            @method($method)
                        @endunless
                        {{ $slot }}
                        <button type="submit" :disabled="busy" class="{{ $confirmClass }} inline-flex items-center gap-2 disabled:opacity-80">
                            <x-icon name="arrow-path" class="h-4 w-4 animate-spin" x-show="busy" x-cloak />
                            <span>{{ $confirm ?? __('common.delete') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
