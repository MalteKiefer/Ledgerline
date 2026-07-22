<x-layouts.app :title="__('account.export_heading')">
    <div class="mx-auto w-full max-w-3xl">
        @include('profile._header', ['title' => __('account.export_heading'), 'subtitle' => __('account.export_hint')])

        <div class="mt-5 ll-card flex flex-col items-start gap-4 sm:flex-row sm:items-center">
            <span class="ll-chip h-11 w-11 shrink-0" style="--chip: #3fae9f"><x-icon name="arrow-down-tray" class="h-6 w-6" /></span>
            <p class="flex-1 text-sm text-gray-500 dark:text-gray-400">{{ __('account.export_hint') }}</p>
            <x-button :href="route('account.export')" icon="arrow-down-tray" class="shrink-0">{{ __('account.export_button') }}</x-button>
        </div>
    </div>
</x-layouts.app>
