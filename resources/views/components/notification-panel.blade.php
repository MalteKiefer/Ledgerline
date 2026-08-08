{{-- Bell dropdown body. Expects the surrounding element to provide the
     notificationBell() Alpine scope (open/items/unread/…). Shared by the
     desktop bar and the mobile top strip. --}}
<div x-show="open" x-cloak
    class="absolute right-0 z-40 mt-2 w-80 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-md-surface shadow-xl">
    <div class="flex items-center justify-between border-b border-md-outline-variant dark:border-md-outline-variant px-3 py-2">
        <span class="text-sm font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('notifications.title') }}</span>
        <button type="button" x-show="unread > 0" @click="markAllRead()" class="text-xs text-md-on-surface-var dark:text-md-on-surface-var hover:text-md-on-surface-var dark:hover:text-md-on-surface-var">{{ __('notifications.mark_all_read') }}</button>
    </div>
    <div x-show="desktop !== 'granted' && desktop !== 'unsupported'" x-cloak class="border-b border-md-outline-variant dark:border-md-outline-variant px-3 py-2">
        <button type="button" @click="enableDesktop()" class="text-xs font-medium text-md-on-surface-var dark:text-md-on-surface-var hover:text-md-on-surface dark:hover:text-white">{{ __('notifications.enable_desktop') }}</button>
    </div>
    <div class="max-h-96 overflow-y-auto">
        <template x-if="items.length === 0">
            <p class="px-3 py-6 text-center text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('notifications.empty') }}</p>
        </template>
        <template x-for="n in items" :key="n.id">
            <button type="button" @click="activate(n)" class="flex w-full items-start gap-2 border-b border-md-outline-variant px-3 py-2 text-left hover:bg-accent/5" :class="[! n.read ? 'bg-accent/5' : '', hrefFor(n) ? 'cursor-pointer' : '']">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="n.level === 'error' ? 'bg-red-500' : (n.level === 'success' ? 'bg-green-500' : 'bg-gray-300')"></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium text-md-on-surface dark:text-md-on-surface" x-text="n.title"></span>
                    <span x-show="n.body" class="block truncate text-xs text-md-on-surface-var dark:text-md-on-surface-var" x-text="n.body"></span>
                    <span class="mt-0.5 block text-[10px] uppercase tracking-wide text-md-on-surface-var dark:text-md-on-surface-var" x-text="fmt(n.at)"></span>
                </span>
            </button>
        </template>
    </div>
</div>
