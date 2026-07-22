<x-layouts.app :title="__('account.delete_heading')">
    <div class="mx-auto w-full max-w-3xl" x-data="{ del: false }">
        @include('profile._header', ['title' => __('account.delete_heading')])

        <div class="mt-5 ll-card border-red-300 dark:border-red-800">
            <div class="flex items-start gap-3.5">
                <span class="ll-chip h-11 w-11 shrink-0" style="--chip: #ef4444"><x-icon name="exclamation-triangle" class="h-6 w-6" /></span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-red-700 dark:text-red-300">{{ __('account.delete_heading') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('account.delete_hint') }}</p>
                </div>
            </div>
            <x-button variant="danger" icon="trash" class="mt-4" @click="del = true">{{ __('account.delete_button') }}</x-button>
        </div>

        {{-- Delete confirmation modal (kept — irreversible, type-to-confirm) --}}
        <div x-show="del" x-cloak class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto p-4"
            role="dialog" aria-modal="true" @keydown.escape.window="del = false">
            <div class="absolute inset-0 bg-gray-900/50" @click="del = false"></div>
            <div class="relative my-16 w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.delete_modal_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('account.delete_modal_warning') }}</p>
                <form method="POST" action="{{ route('account.destroy') }}" class="mt-4 space-y-3">
                    @csrf @method('DELETE')
                    <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('account.delete_confirm_label') }}
                        <input type="text" name="confirmation" required autocomplete="off"
                            class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    </label>
                    @error('confirmation')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <div class="flex justify-end gap-2">
                        <x-button variant="secondary" type="button" @click="del = false">{{ __('common.cancel') }}</x-button>
                        <x-button variant="danger" type="submit">{{ __('account.delete_button') }}</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
