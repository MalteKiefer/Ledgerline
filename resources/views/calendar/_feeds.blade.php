{{-- System calendars (birthdays from contacts, public holidays) + subscribed public
     iCal feeds. Bound to the calendar() Alpine component (sealed calendar store);
     used on the Profile → Calendar settings page. --}}
<div>
  <p class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('calendar.system_cal') }}</p>
  <div class="flex items-center gap-3 rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white" :style="{ background: birthdaysColor }">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="calIconPath('cake')"></path></svg>
    </span>
    <span class="min-w-0 flex-1 truncate text-sm text-gray-900 dark:text-gray-100">{{ __('calendar.sys_birthdays') }}</span>
    <input type="color" :value="birthdaysColor" @change="setFeedColor('birthdays', $event.target.value)" class="h-8 w-9 shrink-0 rounded-md border border-gray-300 dark:border-gray-700">
    <button type="button" @click="toggleBirthdays()" class="flex gap-1 rounded-xl bg-black/5 dark:bg-white/5 p-0.5 text-xs">
      <span class="rounded-lg px-2 py-1" :class="!feedBirthdays ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('common.off') }}</span>
      <span class="rounded-lg px-2 py-1" :class="feedBirthdays ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('common.on') }}</span>
    </button>
  </div>
  <div class="mt-1.5 rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
    <div class="flex items-center gap-3">
      <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white" :style="{ background: holidaysColor }">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="calIconPath('sparkles')"></path></svg>
      </span>
      <span class="min-w-0 flex-1 truncate text-sm text-gray-900 dark:text-gray-100">{{ __('calendar.sys_holidays') }}</span>
      <input type="color" :value="holidaysColor" @change="setFeedColor('holidays', $event.target.value)" class="h-8 w-9 shrink-0 rounded-md border border-gray-300 dark:border-gray-700">
    </div>
    <div class="mt-2 flex flex-wrap gap-1.5">
      <template x-for="cc in holidayCountries" :key="cc">
        <button type="button" @click="toggleHolidayCountry(cc)" class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="holidayCountriesOn.includes(cc) ? 'bg-accent text-white' : 'bg-black/[0.05] dark:bg-white/[0.06] text-gray-600 dark:text-gray-300'"
                x-text="cc"></button>
      </template>
    </div>
  </div>
</div>

<div class="mt-4">
  <div class="mb-2 flex items-center justify-between px-1">
    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('calendar.sub_heading') }}</p>
    <button type="button" x-show="subscriptions.length" @click="refreshSubscriptions()" class="text-xs text-accent hover:underline" :class="_subBusy ? 'opacity-50' : ''">{{ __('calendar.sub_refresh') }}</button>
  </div>
  <template x-if="!subscriptions.length">
    <p class="px-1 text-xs text-gray-400">{{ __('calendar.sub_none') }}</p>
  </template>
  <div class="space-y-1.5">
    <template x-for="s in subscriptions" :key="s.id">
      <div class="flex items-center gap-3 rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
        <span class="h-3.5 w-3.5 shrink-0 rounded-full" :style="{ background: s.color }"></span>
        <span class="min-w-0 flex-1 truncate text-sm text-gray-900 dark:text-gray-100" x-text="s.name" :title="s.url"></span>
        <x-icon-button name="trash" tone="red" size="sm" @click="removeSubscription(s.id)" :aria-label="__('common.delete')" />
      </div>
    </template>
  </div>
  <div class="mt-2 space-y-1.5 rounded-xl border border-black/[0.06] dark:border-white/10 p-2">
    <input type="text" x-model="_subForm.name" placeholder="{{ __('calendar.sub_name') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
    <div class="flex gap-2">
      <input type="url" x-model="_subForm.url" placeholder="{{ __('calendar.sub_url') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
      <input type="color" x-model="_subForm.color" class="h-9 w-10 shrink-0 rounded-md border border-gray-300 dark:border-gray-700">
    </div>
    <div class="text-right">
      <x-button variant="primary" size="sm" @click="addSubscription()">{{ __('calendar.sub_add') }}</x-button>
    </div>
  </div>
</div>
