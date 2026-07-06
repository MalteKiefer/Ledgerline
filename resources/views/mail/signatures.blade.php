<x-layouts.app :title="__('mail.signatures_title')">
    <div x-data="mailSignatures({ deleteConfirm: @js(__('mail.signature_delete_confirm')), saved: @js(__('mail.saved')), saveFailed: @js(__('mail.save_failed')) })" x-init="init()">
        <x-page-heading :title="__('mail.signatures_heading')" :subtitle="__('mail.signatures_sub')">
            <x-slot:actions>
                <x-button icon="chevron-left" href="{{ route('mail.index') }}">{{ __('mail.back_to_mail') }}</x-button>
                <x-button variant="primary" icon="plus" @click="openNew()">{{ __('mail.signature_new') }}</x-button>
            </x-slot:actions>
        </x-page-heading>

        <div class="mt-4 grid gap-4 lg:grid-cols-[20rem_1fr]">
            {{-- List --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2 shadow-sm">
                <template x-if="! signatures.length">
                    <p class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('mail.signatures_empty') }}</p>
                </template>
                <ul class="space-y-1">
                    <template x-for="s in signatures" :key="s.id">
                        <li>
                            <button type="button" @click="openEdit(s)" class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm"
                                :class="editing && editing.id === s.id ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'">
                                <span class="truncate" x-text="s.name"></span>
                                <span x-show="s.isDefault" x-cloak class="shrink-0 rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[10px] font-medium text-gray-500 dark:text-gray-400">{{ __('mail.signature_default_badge') }}</span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Editor --}}
            <div x-show="editing" x-cloak class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
                <div class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.signature_name') }}</label>
                            <input type="text" x-model="sigForm.name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </div>
                        <label class="flex items-end gap-2 pb-1 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" x-model="sigForm.is_default" class="rounded border-gray-300 dark:border-gray-700 text-gray-800 focus:ring-gray-500">
                            {{ __('mail.signature_make_default') }}
                        </label>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('mail.signature_content') }}</label>
                        <div class="mt-1">
                            @include('mail._rich_editor', ['bind' => 'sigForm.html', 'minHeight' => 'min-h-[14rem]'])
                        </div>
                    </div>
                    <p x-show="error" x-cloak class="text-xs text-red-600 dark:text-red-400" x-text="error"></p>
                    <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-4">
                        <button type="button" x-show="editing?.id" @click="destroy()" class="text-sm text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">{{ __('mail.identity_delete') }}</button>
                        <div class="ml-auto flex gap-2">
                            <x-button @click="editing = null">{{ __('common.cancel') }}</x-button>
                            <x-button variant="primary" x-bind:disabled="saving" @click="save()">{{ __('mail.save') }}</x-button>
                        </div>
                    </div>
                </div>
            </div>
            <div x-show="! editing" class="hidden items-center justify-center rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-10 text-sm text-gray-400 dark:text-gray-500 lg:flex">
                {{ __('mail.signatures_sub') }}
            </div>
        </div>
    </div>
</x-layouts.app>
