<x-layouts.app :title="__('calendar.title')">
  <div class="mx-auto max-w-[1700px]" x-data="calendar({
        default_calendar: @js(__('calendar.default_calendar')),
        rrule: {
            every: @js(__('calendar.every')),
            freq: {
                daily: @js(__('calendar.freq_daily')), weekly: @js(__('calendar.freq_weekly')),
                monthly: @js(__('calendar.freq_monthly')), yearly: @js(__('calendar.freq_yearly')),
            },
        },
        reminder: {
            at_time: @js(__('calendar.rem_at_time')), minutes: @js(__('calendar.rem_minutes')),
            hours: @js(__('calendar.rem_hours')), days: @js(__('calendar.rem_days')),
        },
        untitled: @js(__('calendar.untitled')),
        import_done: @js(__('calendar.import_done')),
        feed: { birthday: @js(__('calendar.feed_birthday')), anniversary: @js(__('calendar.feed_anniversary')) },
        remindersUrl: '{{ route('calendar.reminders') }}',
     })">

    {{-- Zero-knowledge gate: calendar data decrypts with the vault key. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <template x-if="state === 'locked'">
      <div class="mx-auto mt-16 max-w-md ll-card !p-8 text-center">
        <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400"
           x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
        <x-button variant="primary" class="mt-5" icon="lock-open" @click="$dispatch('vault-panel')">
          <span x-show="$store.vault.configured">{{ __('vault.unlock') }}</span>
          <span x-show="!$store.vault.configured" x-cloak>{{ __('vault.setup') }}</span>
        </x-button>
      </div>
    </template>

    <div x-show="state === 'ready'" x-cloak>
      {{-- Header: month nav + actions --}}
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <x-icon-button name="chevron-left" tone="gray" @click="prev()" :aria-label="__('calendar.prev_month')" />
          <h1 class="min-w-[12rem] text-center text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="view === 'month' ? monthLabel : rangeLabel"></h1>
          <x-icon-button name="chevron-right" tone="gray" @click="next()" :aria-label="__('calendar.next_month')" />
          <x-button variant="secondary" size="sm" @click="goToday()">{{ __('calendar.today') }}</x-button>
          <div class="ml-1 flex gap-1 rounded-xl bg-black/5 dark:bg-white/5 p-1">
            <button type="button" @click="switchView('month')" class="rounded-lg px-3 py-1 text-sm font-medium transition" :class="view === 'month' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500 hover:text-accent'">{{ __('calendar.view_month') }}</button>
            <button type="button" @click="switchView('week')" class="rounded-lg px-3 py-1 text-sm font-medium transition" :class="view === 'week' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500 hover:text-accent'">{{ __('calendar.view_week') }}</button>
            <button type="button" @click="switchView('day')" class="rounded-lg px-3 py-1 text-sm font-medium transition" :class="view === 'day' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500 hover:text-accent'">{{ __('calendar.view_day') }}</button>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <input type="file" x-ref="icsInput" accept=".ics,text/calendar" class="hidden" @change="importIcs($event.target.files); $event.target.value = ''">
          <x-button variant="secondary" size="sm" icon="arrow-down-tray" @click="$refs.icsInput.click()">{{ __('calendar.import') }}</x-button>
          <x-button variant="secondary" size="sm" icon="arrow-up-tray" @click="exportIcs()">{{ __('calendar.export') }}</x-button>
          <x-button variant="secondary" size="sm" icon="cog-6-tooth" @click="openCalMgr()">{{ __('calendar.calendars') }}</x-button>
          <x-button variant="primary" size="sm" icon="plus" @click="openNew()">{{ __('calendar.add_event') }}</x-button>
        </div>
      </div>

      {{-- Month grid --}}
      <div x-show="view === 'month'" class="ll-card !p-0 overflow-hidden">
        <div class="grid border-b border-black/[0.06] dark:border-white/10 text-center text-[11px] font-medium uppercase tracking-wide text-gray-500"
             :style="{ gridTemplateColumns: showWeekNumbers ? '2.25rem repeat(7, minmax(0,1fr))' : 'repeat(7, minmax(0,1fr))' }">
          <div x-show="showWeekNumbers" class="px-1 py-2 text-[10px] text-gray-400">{{ __('calendar.wk_abbr') }}</div>
          <template x-for="(wd, i) in weekdayLabels" :key="i">
            <div class="px-2 py-2" x-text="wd"></div>
          </template>
        </div>
        <template x-for="(week, wi) in monthWeeks" :key="wi">
          <div class="grid" :style="{ gridTemplateColumns: showWeekNumbers ? '2.25rem repeat(7, minmax(0,1fr))' : 'repeat(7, minmax(0,1fr))' }">
            <div x-show="showWeekNumbers" class="flex items-center justify-center border-b border-r border-black/[0.06] dark:border-white/10 text-[10px] text-gray-400" x-text="weekNumber(week)"></div>
            <template x-for="cell in week" :key="cell.iso">
              <button type="button" @click="openDay(cell.iso)"
                      class="min-h-[92px] border-b border-r border-black/[0.06] dark:border-white/10 p-1.5 text-left align-top hover:bg-accent/5 focus:outline-none"
                      :class="cell.inMonth ? '' : 'bg-black/[0.02] dark:bg-white/[0.02]'">
                <div class="flex items-center justify-between">
                  <span class="text-xs"
                        :class="cell.isToday ? 'flex h-5 w-5 items-center justify-center rounded-full bg-accent text-white' : (cell.inMonth ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400')"
                        x-text="cell.day"></span>
                </div>
                <div class="mt-1 space-y-0.5">
                  <template x-for="ev in dayEvents(cell.iso).slice(0, 3)" :key="ev.id">
                    <div class="flex items-center gap-1 truncate rounded px-1 py-0.5 text-[11px] text-white"
                         :style="{ background: calColor(ev.calendarId) }" :title="ev.title">
                      <span x-show="!ev.allDay" class="tabular-nums opacity-90" x-text="timeLabel(ev)"></span>
                      <span class="truncate" x-text="ev.title || '{{ __('calendar.untitled') }}'"></span>
                    </div>
                  </template>
                  <div x-show="dayEvents(cell.iso).length > 3" class="px-1 text-[10px] text-gray-500"
                       x-text="`+${dayEvents(cell.iso).length - 3}`"></div>
                </div>
              </button>
            </template>
          </div>
        </template>
      </div>

      {{-- Week / day time grid --}}
      <div x-show="view !== 'month'" x-cloak class="ll-card !p-0 overflow-hidden">
        {{-- Column headers --}}
        <div class="grid border-b border-black/[0.06] dark:border-white/10" :style="gridColsStyle">
          <div></div>
          <template x-for="iso in gridDays" :key="'h'+iso">
            <button type="button" @click="switchToDay(iso)" class="border-l border-black/[0.06] dark:border-white/10 py-2 text-center hover:bg-accent/5">
              <div class="text-[11px] uppercase tracking-wide text-gray-500" x-text="gridColLabel(iso).wd"></div>
              <div class="text-sm font-semibold" :class="gridColLabel(iso).isToday ? 'text-accent' : 'text-gray-900 dark:text-gray-100'" x-text="gridColLabel(iso).day"></div>
            </button>
          </template>
        </div>
        {{-- All-day row --}}
        <div class="grid border-b border-black/[0.06] dark:border-white/10" :style="gridColsStyle">
          <div class="py-1 pr-2 text-right text-[10px] text-gray-400">{{ __('calendar.all_day') }}</div>
          <template x-for="iso in gridDays" :key="'ad'+iso">
            <div class="min-h-[28px] space-y-0.5 border-l border-black/[0.06] dark:border-white/10 p-0.5">
              <template x-for="ev in allDayEventsForDay(iso)" :key="'ade'+ev.id+iso">
                <button type="button" @click="openEvent(ev)" class="block w-full truncate rounded px-1 text-left text-[11px] text-white" :style="{ background: calColor(ev.calendarId) }" x-text="ev.title || '{{ __('calendar.untitled') }}'"></button>
              </template>
            </div>
          </template>
        </div>
        {{-- Hour grid --}}
        <div class="grid max-h-[68vh] overflow-y-auto" :style="gridColsStyle">
          <div>
            <template x-for="h in gridHours" :key="'g'+h">
              <div class="h-12 border-b border-black/[0.04] dark:border-white/[0.06] pr-2 text-right text-[10px] text-gray-400" x-text="String(h).padStart(2,'0') + ':00'"></div>
            </template>
          </div>
          <template x-for="iso in gridDays" :key="'col'+iso">
            <div class="relative border-l border-black/[0.06] dark:border-white/10">
              <template x-for="h in gridHours" :key="'c'+iso+h">
                <div class="h-12 cursor-pointer border-b border-black/[0.04] dark:border-white/[0.06] hover:bg-accent/5" @click="openSlot(iso, h)"></div>
              </template>
              <template x-for="ev in timedEventsForDay(iso)" :key="'e'+ev.id+iso">
                <button type="button" @click.stop="openEvent(ev)" class="absolute left-0.5 right-0.5 overflow-hidden rounded px-1 py-0.5 text-left text-[11px] leading-tight text-white shadow-sm" :style="{ top: eventStyle(ev).top, height: eventStyle(ev).height, background: calColor(ev.calendarId) }" :title="ev.title">
                  <span class="block truncate font-medium" x-text="ev.title || '{{ __('calendar.untitled') }}'"></span>
                  <span class="block truncate opacity-90" x-text="timeLabel(ev)"></span>
                </button>
              </template>
            </div>
          </template>
        </div>
      </div>
    </div>

    {{-- Day agenda modal --}}
    <template x-teleport="body">
      <div x-show="selectedDay" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeDay()">
        <div class="absolute inset-0 bg-gray-900/40" @click="closeDay()"></div>
        <div class="relative flex max-h-[80vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
          <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="selectedDay ? fmtDay(selectedDay) : ''"></h3>
            <x-icon-button name="plus" tone="accent" @click="openNew(selectedDay)" :aria-label="__('calendar.add_event')" />
          </div>
          <div class="flex-1 overflow-y-auto p-3">
            <template x-if="selectedDay && dayEvents(selectedDay).length === 0">
              <x-empty-state class="py-8">{{ __('calendar.no_events') }}</x-empty-state>
            </template>
            <div class="space-y-1.5">
              <template x-for="ev in (selectedDay ? dayEvents(selectedDay) : [])" :key="ev.id">
                <button type="button" @click="openEvent(ev)" class="flex w-full items-start gap-3 rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2 text-left hover:bg-accent/5">
                  <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-white" :style="{ background: calColor(ev.calendarId) }">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" :d="calIconPath(calIcon(ev.calendarId))"></path></svg>
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="ev.title || '{{ __('calendar.untitled') }}'"></span>
                    <span class="block text-xs text-gray-500">
                      <span x-show="ev.allDay">{{ __('calendar.all_day') }}</span>
                      <span x-show="!ev.allDay" x-text="timeLabel(ev)"></span>
                      <span x-show="ev.location" x-text="ev.location ? ' · ' + ev.location.label : ''"></span>
                      <span x-show="isRecurring(ev)" x-cloak class="text-accent" x-text="' · ' + rruleLabel(ev)"></span>
                    </span>
                  </span>
                </button>
              </template>
            </div>
          </div>
          <div class="border-t border-black/[0.06] dark:border-white/10 px-5 py-3 text-right">
            <x-button variant="secondary" size="sm" @click="closeDay()">{{ __('common.close') }}</x-button>
          </div>
        </div>
      </div>
    </template>

    {{-- Event editor modal --}}
    <template x-teleport="body">
      <div x-show="editorOpen" x-cloak class="fixed inset-0 z-[1120] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeEditor()">
        <div class="absolute inset-0 bg-gray-900/40" @click="closeEditor()"></div>
        <div class="relative flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
          <div class="border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="editing ? '{{ __('calendar.edit_event') }}' : '{{ __('calendar.add_event') }}'"></h3>
          </div>
          <div class="flex-1 space-y-3 overflow-y-auto p-5">
            {{-- Scope selector for editing one occurrence of a series --}}
            <div x-show="_occRid" x-cloak class="rounded-xl bg-amber-50 dark:bg-amber-500/10 px-3 py-2">
              <span class="block text-xs font-medium text-amber-800 dark:text-amber-300">{{ __('calendar.edit_scope') }}</span>
              <div class="mt-1 flex gap-4 text-sm">
                <label class="flex items-center gap-1.5"><input type="radio" value="this" x-model="editScope" class="text-accent focus:ring-accent">{{ __('calendar.scope_this') }}</label>
                <label class="flex items-center gap-1.5"><input type="radio" value="all" x-model="editScope" class="text-accent focus:ring-accent">{{ __('calendar.scope_all') }}</label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('calendar.event_title') }}</label>
              <input type="text" x-model="_form.title" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm" :class="_saveAttempted && !_form.title.trim() ? 'border-red-400 ring-1 ring-red-400' : ''">
              <p x-show="_saveAttempted && !_form.title.trim()" x-cloak class="mt-1 text-xs text-red-600">{{ __('calendar.title_required') }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('calendar.calendar') }}</label>
              <select x-model="_form.calendarId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                <template x-for="c in calendars" :key="c.id">
                  <option :value="c.id" x-text="c.name"></option>
                </template>
              </select>
            </div>

            <label class="flex items-center gap-2">
              <input type="checkbox" x-model="_form.allDay" class="rounded border-gray-300 dark:border-gray-700 text-accent focus:ring-accent">
              <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('calendar.all_day') }}</span>
            </label>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('calendar.starts') }}</label>
                <input type="date" x-model="_form.startDate" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
              </div>
              <div x-show="!_form.allDay">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">&nbsp;</label>
                <input type="time" x-model="_form.startTime" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('calendar.ends') }}</label>
                <input type="date" x-model="_form.endDate" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
              </div>
              <div x-show="!_form.allDay">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">&nbsp;</label>
                <input type="time" x-model="_form.endTime" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
              </div>
            </div>

            {{-- Recurrence (hidden when editing just one occurrence) --}}
            <div x-show="!(_occRid && editScope === 'this')">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('calendar.repeat') }}</label>
              <select x-model="_form.repeat" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                <option value="none">{{ __('calendar.repeat_none') }}</option>
                <option value="DAILY">{{ __('calendar.freq_daily') }}</option>
                <option value="WEEKLY">{{ __('calendar.freq_weekly') }}</option>
                <option value="MONTHLY">{{ __('calendar.freq_monthly') }}</option>
                <option value="YEARLY">{{ __('calendar.freq_yearly') }}</option>
              </select>
              <div x-show="_form.repeat !== 'none'" x-cloak class="mt-2 space-y-2 rounded-xl border border-black/[0.06] dark:border-white/10 p-3">
                <div class="flex items-center gap-2 text-sm">
                  <span class="text-gray-600 dark:text-gray-400">{{ __('calendar.every') }}</span>
                  <input type="number" min="1" max="99" x-model.number="_form.interval" class="w-16 rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
                </div>
                <div x-show="_form.repeat === 'WEEKLY'" class="flex flex-wrap gap-1">
                  <template x-for="(wd, i) in weekdays" :key="wd">
                    <button type="button" @click="toggleByday(wd)" class="h-8 w-8 rounded-full text-xs font-medium"
                            :class="_form.byday.includes(wd) ? 'bg-accent text-white' : 'bg-black/[0.05] dark:bg-white/[0.06] text-gray-600 dark:text-gray-300'"
                            x-text="weekdayLabels[i]"></button>
                  </template>
                </div>
                <div>
                  <span class="block text-xs font-medium text-gray-500">{{ __('calendar.ends') }}</span>
                  <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    <label class="flex items-center gap-1.5"><input type="radio" value="never" x-model="_form.ends" class="text-accent focus:ring-accent">{{ __('calendar.ends_never') }}</label>
                    <label class="flex items-center gap-1.5"><input type="radio" value="count" x-model="_form.ends" class="text-accent focus:ring-accent">{{ __('calendar.ends_after') }}</label>
                    <input type="number" min="1" max="999" x-model.number="_form.count" x-show="_form.ends === 'count'" class="w-16 rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
                    <span x-show="_form.ends === 'count'" class="text-xs text-gray-500">{{ __('calendar.occurrences') }}</span>
                    <label class="flex items-center gap-1.5"><input type="radio" value="until" x-model="_form.ends" class="text-accent focus:ring-accent">{{ __('calendar.ends_on') }}</label>
                    <input type="date" x-model="_form.until" x-show="_form.ends === 'until'" class="rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
                  </div>
                </div>
              </div>
            </div>

            <div class="relative">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('calendar.location') }}</label>
              <input type="text" x-model="_form.location" @input="onLocationInput()" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
              <p x-show="geoSearching" x-cloak class="mt-1 text-xs text-gray-400">{{ __('calendar.location_searching') }}</p>
              <div x-show="geoResults.length" x-cloak @click.outside="geoResults = []" class="absolute z-10 mt-1 w-full overflow-hidden rounded-xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-lg">
                <template x-for="(r, i) in geoResults" :key="i">
                  <button type="button" @click="pickLocation(r)" class="block w-full truncate px-3 py-2 text-left text-sm hover:bg-accent/5" x-text="r.display"></button>
                </template>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('calendar.reminders') }}</label>
              <div class="mt-1 flex flex-wrap gap-1.5">
                <template x-for="min in reminderPresets" :key="min">
                  <button type="button" @click="toggleReminder(min)" class="rounded-full px-2.5 py-1 text-xs font-medium"
                          :class="hasReminder(min) ? 'bg-accent text-white' : 'bg-black/[0.05] dark:bg-white/[0.06] text-gray-600 dark:text-gray-300'"
                          x-text="reminderLabel(min)"></button>
                </template>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('calendar.description') }}</label>
              <textarea x-model="_form.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm"></textarea>
            </div>
          </div>
          <div class="flex items-center justify-between border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
            <div>
              <x-button x-show="editing" x-cloak variant="danger" size="sm" @click="deleteEvent(editing)">{{ __('calendar.delete_event') }}</x-button>
            </div>
            <div class="flex gap-2">
              <x-button variant="secondary" @click="closeEditor()">{{ __('common.cancel') }}</x-button>
              <x-button variant="primary" @click="saveEvent()">{{ __('common.save') }}</x-button>
            </div>
          </div>
        </div>
      </div>
    </template>

    {{-- Calendars manager modal --}}
    <template x-teleport="body">
      <div x-show="calMgrOpen" x-cloak class="fixed inset-0 z-[1120] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeCalMgr()">
        <div class="absolute inset-0 bg-gray-900/40" @click="closeCalMgr()"></div>
        <div class="relative flex max-h-[85vh] w-full max-w-md flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
          <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('calendar.manage_calendars') }}</h3>
            <x-icon-button name="plus" tone="accent" @click="newCalendar()" :aria-label="__('calendar.new_calendar')" />
          </div>
          <div class="flex-1 space-y-1.5 overflow-y-auto p-3">
            {{-- Inline calendar form --}}
            <template x-if="_calForm">
              <div class="rounded-xl border border-accent/40 bg-accent/5 p-3">
                <input type="text" x-model="_calForm.name" placeholder="{{ __('calendar.calendar_name') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                <div class="mt-2 flex flex-wrap gap-1.5">
                  <template x-for="col in colors" :key="col">
                    <button type="button" @click="_calForm.color = col" class="h-6 w-6 rounded-full ring-offset-1" :style="{ background: col }" :class="_calForm.color === col ? 'ring-2 ring-accent' : ''"></button>
                  </template>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                  <template x-for="ic in calIcons" :key="ic">
                    <button type="button" @click="_calForm.icon = ic" class="flex h-8 w-8 items-center justify-center rounded-lg"
                            :class="_calForm.icon === ic ? 'text-white' : 'bg-black/[0.05] dark:bg-white/[0.06] text-gray-500 dark:text-gray-300'"
                            :style="_calForm.icon === ic ? { background: _calForm.color } : {}">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="calIconPath(ic)"></path></svg>
                    </button>
                  </template>
                </div>
                <div class="mt-2 flex justify-end gap-2">
                  <x-button variant="secondary" size="sm" @click="_calForm = null">{{ __('common.cancel') }}</x-button>
                  <x-button variant="primary" size="sm" @click="saveCalendar()">{{ __('common.save') }}</x-button>
                </div>
              </div>
            </template>
            <template x-for="c in calendars" :key="c.id">
              <div class="flex items-center gap-3 rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white" :style="{ background: c.color }">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="calIconPath(c.icon)"></path></svg>
                </span>
                <span class="min-w-0 flex-1 truncate text-sm text-gray-900 dark:text-gray-100" x-text="c.name"></span>
                <x-badge x-show="c.isDefault" x-cloak variant="accent">{{ __('calendar.default') }}</x-badge>
                <x-icon-button name="star" tone="gray" size="sm" x-show="!c.isDefault" @click="setDefaultCalendar(c)" :aria-label="__('calendar.set_default')" />
                <x-icon-button name="pencil" tone="gray" size="sm" @click="editCalendar(c)" :aria-label="__('common.edit')" />
                <x-icon-button name="trash" tone="red" size="sm" x-show="calendars.length > 1" @click="deleteCalendar(c)" :aria-label="__('common.delete')" />
              </div>
            </template>

            {{-- System calendars (birthdays from contacts, public holidays) --}}
            <div class="mt-3 border-t border-black/[0.06] dark:border-white/10 pt-3">
              <p class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('calendar.system_cal') }}</p>
              <div class="flex items-center gap-3 rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white" style="background:#d1607e">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="calIconPath('cake')"></path></svg>
                </span>
                <span class="min-w-0 flex-1 truncate text-sm text-gray-900 dark:text-gray-100">{{ __('calendar.sys_birthdays') }}</span>
                <button type="button" @click="toggleBirthdays()" class="flex gap-1 rounded-xl bg-black/5 dark:bg-white/5 p-0.5 text-xs">
                  <span class="rounded-lg px-2 py-1" :class="!feedBirthdays ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('common.off') }}</span>
                  <span class="rounded-lg px-2 py-1" :class="feedBirthdays ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('common.on') }}</span>
                </button>
              </div>
              <div class="mt-1.5 flex items-center gap-3 rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white" style="background:#59ad6b">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="calIconPath('sparkles')"></path></svg>
                </span>
                <span class="min-w-0 flex-1 truncate text-sm text-gray-900 dark:text-gray-100">{{ __('calendar.sys_holidays') }}</span>
                <select @change="setHolidays($event.target.value || false)" class="rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
                  <option value="" :selected="!feedHolidays">{{ __('calendar.holidays_off') }}</option>
                  <template x-for="cc in holidayCountries" :key="cc">
                    <option :value="cc" :selected="feedHolidays === cc" x-text="cc"></option>
                  </template>
                </select>
              </div>
            </div>
          </div>
          <div class="border-t border-black/[0.06] dark:border-white/10 px-5 py-3 text-right">
            <x-button variant="secondary" size="sm" @click="closeCalMgr()">{{ __('common.close') }}</x-button>
          </div>
        </div>
      </div>
    </template>

  </div>
</x-layouts.app>
