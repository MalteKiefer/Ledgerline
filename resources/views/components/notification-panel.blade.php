{{-- Bell dropdown body. Expects the surrounding element to provide the
     notificationBell() Alpine scope (open/items/unread/…). Shared by the
     desktop bar and the mobile top strip. --}}
<div x-show="open" x-cloak
    class="absolute right-0 z-40 mt-2 w-80 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-lg">
    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-3 py-2">
        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('notifications.title') }}</span>
        <button type="button" x-show="unread > 0" @click="markAllRead()" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">{{ __('notifications.mark_all_read') }}</button>
    </div>
    <div x-show="desktop !== 'granted' && desktop !== 'unsupported'" x-cloak class="border-b border-gray-100 dark:border-gray-800 px-3 py-2">
        <button type="button" @click="enableDesktop()" class="text-xs font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">{{ __('notifications.enable_desktop') }}</button>
    </div>
    <div class="max-h-96 overflow-y-auto">
        <template x-if="items.length === 0">
            <p class="px-3 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('notifications.empty') }}</p>
        </template>
        <template x-for="n in items" :key="n.id">
            <button type="button" @click="activate(n)" class="flex w-full items-start gap-2 border-b border-gray-50 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-800" :class="[! n.read ? 'bg-gray-50 dark:bg-gray-800' : '', hrefFor(n) ? 'cursor-pointer' : '']">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="n.level === 'error' ? 'bg-red-500' : (n.level === 'success' ? 'bg-green-500' : 'bg-gray-300')"></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100" x-text="n.title"></span>
                    <span x-show="n.body" class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="n.body"></span>
                    <span class="mt-0.5 block text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500" x-text="fmt(n.at)"></span>
                </span>
            </button>
        </template>
    </div>
</div>
