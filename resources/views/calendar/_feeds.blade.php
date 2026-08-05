{{-- System calendars (birthdays / public holidays) + subscribed public iCal feeds.
     Bound to the calendar() Alpine component (sealed calendar store); used on the
     Profile → Calendar settings page. --}}

{{-- Birthdays --}}
<div class="rounded-2xl border border-black/[0.06] dark:border-white/10 p-4">
  <div class="flex items-center gap-3">
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white" :style="{ background: birthdaysColor }">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" :d="calIconPath('cake')"></path></svg>
    </span>
    <div class="min-w-0 flex-1">
      <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('calendar.sys_birthdays') }}</p>
      <p class="text-xs text-gray-400">{{ __('calendar.sys_birthdays_hint') }}</p>
    </div>
    <input type="color" :value="birthdaysColor" @change="setFeedColor('birthdays', $event.target.value)" class="h-8 w-9 shrink-0 cursor-pointer rounded-md border border-gray-300 dark:border-gray-700" title="{{ __('calendar.color') }}">
    <button type="button" @click="toggleBirthdays()" class="flex shrink-0 gap-1 rounded-xl bg-black/5 dark:bg-white/5 p-0.5 text-xs">
      <span class="rounded-lg px-2 py-1" :class="!feedBirthdays ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('common.off') }}</span>
      <span class="rounded-lg px-2 py-1" :class="feedBirthdays ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('common.on') }}</span>
    </button>
  </div>
</div>

{{-- Public holidays (multi-country) --}}
<div class="mt-3 rounded-2xl border border-black/[0.06] dark:border-white/10 p-4">
  <div class="flex items-center gap-3">
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white" :style="{ background: holidaysColor }">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" :d="calIconPath('sparkles')"></path></svg>
    </span>
    <div class="min-w-0 flex-1">
      <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('calendar.sys_holidays') }}</p>
      <p class="text-xs text-gray-400" x-text="holidayCountriesOn.length ? holidayCountriesOn.join(', ') : '{{ __('calendar.holidays_off') }}'"></p>
    </div>
    <input type="color" :value="holidaysColor" @change="setFeedColor('holidays', $event.target.value)" class="h-8 w-9 shrink-0 cursor-pointer rounded-md border border-gray-300 dark:border-gray-700" title="{{ __('calendar.color') }}">
  </div>
  <div class="mt-3 flex flex-wrap gap-1.5">
    <template x-for="cc in holidayCountries" :key="cc">
      <button type="button" @click="toggleHolidayCountry(cc)" class="rounded-full px-2.5 py-1 text-xs font-medium transition"
              :class="holidayCountriesOn.includes(cc) ? 'bg-accent text-white shadow-sm' : 'bg-black/[0.05] dark:bg-white/[0.06] text-gray-600 dark:text-gray-300 hover:bg-accent/10'"
              x-text="cc"></button>
    </template>
  </div>
</div>

{{-- School holidays (Ferien) — one-click subscribe via OpenHolidays --}}
<div class="mt-3 rounded-2xl border border-black/[0.06] dark:border-white/10 p-4">
  <div class="flex items-center gap-3">
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white" style="background:#e2915a">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" :d="calIconPath('sun')"></path></svg>
    </span>
    <div class="min-w-0 flex-1">
      <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('calendar.school_heading') }}</p>
      <p class="text-xs text-gray-400">{{ __('calendar.school_hint') }}</p>
    </div>
  </div>
  <div class="mt-3 flex flex-wrap items-end gap-2">
    <select x-model="schoolForm.country" @change="schoolForm.region = ''" class="rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
      <template x-for="cc in schoolCountries" :key="cc"><option :value="cc" x-text="cc"></option></template>
    </select>
    <select x-model="schoolForm.region" class="min-w-0 flex-1 rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
      <option value="">{{ __('calendar.region_select') }}</option>
      <template x-for="r in schoolRegions()" :key="r.code"><option :value="r.code" x-text="r.name"></option></template>
    </select>
    <x-button variant="secondary" size="sm" ::disabled="!schoolForm.region || _subBusy" @click="addSchoolHolidays()">{{ __('calendar.school_add') }}</x-button>
  </div>
</div>

{{-- Subscribed public calendars (.ics URL) --}}
<div class="mt-3 rounded-2xl border border-black/[0.06] dark:border-white/10 p-4">
  <div class="mb-3 flex items-center justify-between">
    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('calendar.sub_heading') }}</p>
    <button type="button" x-show="subscriptions.length" @click="refreshSubscriptions()" class="text-xs text-accent hover:underline" :class="_subBusy ? 'opacity-50' : ''">{{ __('calendar.sub_refresh') }}</button>
  </div>
  <template x-if="!subscriptions.length">
    <p class="px-1 pb-2 text-xs text-gray-400">{{ __('calendar.sub_none') }}</p>
  </template>
  <div class="space-y-1.5">
    <template x-for="s in subscriptions" :key="s.id">
      <div class="flex items-center gap-2.5 rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
        <input type="color" :value="s.color" @change="setSubColor(s.id, $event.target.value)" class="h-7 w-8 shrink-0 cursor-pointer rounded-md border border-gray-300 dark:border-gray-700" title="{{ __('calendar.color') }}">
        <input type="text" :value="s.name" @change="renameSubscription(s.id, $event.target.value)" class="min-w-0 flex-1 rounded-md border-transparent bg-transparent px-1 text-sm text-gray-900 focus:border-accent focus:bg-white focus:ring-accent dark:text-gray-100 dark:focus:bg-[#2c2c2e]" :title="s.url">
        <x-icon-button name="trash" tone="red" size="sm" @click="removeSubscription(s.id)" :aria-label="__('common.delete')" />
      </div>
    </template>
  </div>
  <div class="mt-3 space-y-2 rounded-xl bg-black/[0.02] dark:bg-white/[0.03] p-3">
    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('calendar.sub_add') }}</p>
    <input type="text" x-model="_subForm.name" placeholder="{{ __('calendar.sub_name') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
    <div class="flex gap-2">
      <input type="url" x-model="_subForm.url" placeholder="{{ __('calendar.sub_url') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
      <input type="color" x-model="_subForm.color" class="h-9 w-10 shrink-0 cursor-pointer rounded-md border border-gray-300 dark:border-gray-700" title="{{ __('calendar.color') }}">
    </div>
    <div class="text-right">
      <x-button variant="primary" size="sm" ::disabled="_subBusy" @click="addSubscription()">{{ __('calendar.sub_add') }}</x-button>
    </div>
  </div>
</div>
