<x-layouts.share :title="__('share.title')">
    <div class="mx-auto max-w-3xl px-4 py-10" x-data="sharedNote(@js(['id' => $id]), {
            untitled: @js(__('share.untitled')),
            gone: @js(__('share.gone')),
            error: @js(__('share.error')),
            no_key: @js(__('share.no_key')),
            wrong_password: @js(__('share.wrong_password')),
         })">

        {{-- Loading --}}
        <template x-if="state === 'loading'">
            <p class="mt-16 text-center text-sm text-gray-500">{{ __('share.loading') }}</p>
        </template>

        {{-- Password prompt --}}
        <template x-if="state === 'password'">
            <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
                <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
                <p class="mt-4 text-sm text-gray-600">{{ __('share.password_prompt') }}</p>
                <form @submit.prevent="submitPassword()" class="mt-5">
                    <input type="password" x-model="password" placeholder="{{ __('share.password_label') }}" autofocus
                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <p x-show="errorMsg" x-cloak class="mt-2 text-sm text-red-600" x-text="errorMsg"></p>
                    <button type="submit" class="mt-4 w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('share.unlock') }}</button>
                </form>
            </div>
        </template>

        {{-- Error / gone --}}
        <template x-if="state === 'error'">
            <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
                <p class="text-sm text-gray-600" x-text="errorMsg"></p>
            </div>
        </template>

        {{-- Note --}}
        <template x-if="state === 'ready'">
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-6 py-4">
                    <div class="min-w-0">
                        <h1 class="text-lg font-semibold text-gray-900" x-text="title"></h1>
                        <p class="mt-1 text-xs text-gray-400">{{ __('share.read_only_notice') }}</p>
                    </div>
                    <div x-show="canDownload" x-cloak class="flex shrink-0 items-center gap-2">
                        <button type="button" @click="downloadMarkdown()" title="{{ __('share.download_markdown') }}" aria-label="{{ __('share.download_markdown') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="arrow-down-tray" class="h-5 w-5" /></button>
                        <button type="button" @click="downloadPdf()" title="{{ __('share.download_pdf') }}" aria-label="{{ __('share.download_pdf') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="document-arrow-down" class="h-5 w-5" /></button>
                    </div>
                </div>
                <article class="markdown-body p-6" x-html="html"></article>
            </div>
        </template>

        <p class="mt-6 text-center text-xs text-gray-400">Ledgerline v{{ config('app.version') }}</p>
    </div>
</x-layouts.share>
