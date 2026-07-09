<x-layouts.app :title="__('pages.dashboard.title')">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('pages.dashboard.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('pages.dashboard.subtitle') }}</p>

    <a href="{{ route('gallery.index') }}" class="mt-6 flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 sm:p-6 shadow-sm hover:border-gray-300">
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.dashboard.gallery') }}</dt>
            <dd class="mt-2 text-base text-gray-900 dark:text-gray-100">{{ __('pages.dashboard.gallery_ready') }}</dd>
        </div>
        <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
        </svg>
    </a>

    <a href="{{ route('files.index') }}" class="mt-4 flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 sm:p-6 shadow-sm hover:border-gray-300">
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.dashboard.files') }}</dt>
            <dd class="mt-2 text-base text-gray-900 dark:text-gray-100">{{ __('pages.dashboard.vault_ready') }}</dd>
        </div>
        <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
        </svg>
    </a>

    <a href="{{ route('notes.index') }}" class="mt-4 flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 sm:p-6 shadow-sm hover:border-gray-300">
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.dashboard.notes') }}</dt>
            <dd class="mt-2 text-base text-gray-900 dark:text-gray-100">{{ __('pages.dashboard.notes_ready') }}</dd>
        </div>
        <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
        </svg>
    </a>

    <a href="{{ route('bookmarks.index') }}" class="mt-4 flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 sm:p-6 shadow-sm hover:border-gray-300">
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.dashboard.bookmarks') }}</dt>
            <dd class="mt-2 text-base text-gray-900 dark:text-gray-100">{{ __('pages.dashboard.bookmarks_ready') }}</dd>
        </div>
        <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" />
        </svg>
    </a>

</x-layouts.app>
