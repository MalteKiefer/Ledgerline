<x-layouts.app :title="__('calendar.ui.heading')">
    @php $cfg = [
        'dataUrl' => route('calendar.data'),
        'eventsUrl' => route('calendar.events.store'),
        'eventBase' => url('calendar/events'),
        'calUrl' => route('calendar.calendars.store'),
        'calBase' => url('calendar/calendars'),
        'importUrl' => route('calendar.import'),
        'exportUrl' => route('calendar.export'),
        'importFromUrl' => route('calendar.import-url'),
        'subscribeUrl' => route('calendar.subscribe'),
        'timezoneUrl' => route('calendar.timezone'),
        'sharesDataUrl' => route('shares.data'),
        'sharesUrl' => route('shares.store'),
        'sharesBase' => url('shares'),
        'shareError' => __('shares.error'),
        'shareLink' => route('calendar.index'),
        'mailConfigured' => \App\Services\Notifications\ChannelNotifier::mailConfigured(),
        'linkCopied' => __('shares.link_copied'),
        'mailSent' => __('shares.mail_sent'),
        'mailUnavailable' => __('shares.mail_unavailable'),
        'publicStoreUrl' => route('public-share.store'),
        'publicBase' => url('shares/public'),
        'token' => csrf_token(),
        'importUrlPrompt' => __('calendar.ui.import_url_prompt'),
        'subscribeUrlPrompt' => __('calendar.ui.subscribe_url_prompt'),
        'subscribeNamePrompt' => __('calendar.ui.subscribe_name_prompt'),
        'feedFailed' => __('calendar.ui.feed_failed'),
        'confirmDelete' => __('calendar.ui.delete_confirm'),
        'newCalendar' => __('calendar.ui.new_calendar'),
        'renameCalendar' => __('calendar.ui.rename_calendar'),
        'confirmDeleteCalendar' => __('calendar.ui.delete_calendar_confirm'),
    ]; @endphp
    <div x-data="calendarPage(@js($cfg))" x-init="init()" class="flex flex-col gap-4 md:flex-row">
        {{-- Sidebar: trigger + rail (md) + slide-over (mobile) --}}
        <div class="md:hidden">
            <button type="button" @click="$store.nav.toggleSidebar()"
                class="flex min-h-11 w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium text-gray-700 shadow-sm">
                <x-icon name="bars-3" class="h-4 w-4 text-gray-400" />
                <span>{{ __('calendar.ui.calendars') }}</span>
            </button>
        </div>
        <aside class="hidden w-full shrink-0 space-y-4 self-start rounded-lg border border-gray-200 bg-white p-3 shadow-sm md:block md:w-56">
            @include('calendar._sidebar_content')
        </aside>
        <x-sheet side="left" store="sidebarOpen" :title="__('calendar.ui.calendars')">
            <div class="space-y-4">@include('calendar._sidebar_content')</div>
        </x-sheet>

        {{-- Main --}}
        <div class="min-w-0 flex-1">
            {{-- Timezone mismatch prompt --}}
            <div x-show="tzMismatch" x-cloak class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                <span x-text="'{{ __('calendar.ui.tz_detected') }}'.replace(':tz', tzMismatch)"></span>
                <span class="flex gap-2">
                    <button @click="acceptTimezone()" class="rounded-md bg-amber-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-amber-700">{{ __('calendar.ui.tz_switch') }}</button>
                    <button @click="dismissTimezone()" class="rounded-md border border-amber-300 px-2.5 py-1 text-xs text-amber-800 hover:bg-amber-100">{{ __('calendar.ui.tz_keep') }}</button>
                </span>
            </div>
            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-1">
                    <button @click="step(-1)" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border border-gray-300 px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('calendar.ui.prev') }}"><x-icon name="chevron-left" class="h-4 w-4" /></button>
                    <button @click="today()" class="inline-flex min-h-11 items-center justify-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">{{ __('calendar.ui.today') }}</button>
                    <button @click="step(1)" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border border-gray-300 px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('calendar.ui.next') }}"><x-icon name="chevron-right" class="h-4 w-4" /></button>
                    <h2 class="ml-2 text-base font-semibold text-gray-900" x-text="title"></h2>
                </div>
                <div class="inline-flex flex-wrap rounded-md border border-gray-300 text-sm">
                    @foreach (['month', 'week', 'day', 'agenda'] as $v)
                        <button @click="setView('{{ $v }}')" :class="view==='{{ $v }}'?'bg-gray-900 text-white':'text-gray-700 hover:bg-gray-50'"
                            class="min-h-11 px-3 py-1.5 first:rounded-l-md last:rounded-r-md">{{ __("calendar.ui.$v") }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Month --}}
            <div x-show="view==='month'" class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="grid border-b border-gray-200 text-center text-xs font-medium text-gray-500"
                    :style="`grid-template-columns:${weekNumbers?'2.5rem ':''}repeat(7,minmax(0,1fr))`">
                    <template x-if="weekNumbers"><div class="py-2 text-gray-400">{{ __('calendar.ui.week_abbr') }}</div></template>
                    <template x-for="(d,i) in weekDays()" :key="'h'+i">
                        <div class="py-2" x-text="fmtWeekday(d)"></div>
                    </template>
                </div>
                <template x-for="(wk,wi) in monthWeeks()" :key="'wk'+wi">
                    <div class="grid" :style="`grid-template-columns:${weekNumbers?'2.5rem ':''}repeat(7,minmax(0,1fr))`">
                        <template x-if="weekNumbers">
                            <div class="flex items-start justify-center border-b border-r border-gray-100 pt-1 text-[11px] text-gray-400" x-text="wk.week"></div>
                        </template>
                        <template x-for="(d,i) in wk.days" :key="'d'+wi+'-'+i">
                            <div @click="openNew(d)"
                                class="min-h-[92px] cursor-pointer border-b border-r border-gray-100 p-1 last:border-r-0 hover:bg-gray-50"
                                :class="inMonth(d)?'':'bg-gray-50/60'">
                                <div class="text-right">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs"
                                        :class="isToday(d)?'bg-gray-900 text-white':(inMonth(d)?'text-gray-700':'text-gray-400')"
                                        x-text="d.getDate()"></span>
                                </div>
                                <div class="mt-0.5 space-y-0.5">
                                    <template x-for="e in eventsOn(d).slice(0,3)" :key="e.id+'-'+e.instance">
                                        <button @click.stop="openEditor(e.id)"
                                            class="block w-full truncate rounded px-1 py-0.5 text-left text-[11px] text-white"
                                            :style="`background:${e.color}`">
                                            <span x-show="!e.all_day" x-text="fmtTime(e.start)+' '"></span><span x-text="e.title||'—'"></span>
                                        </button>
                                    </template>
                                    <template x-if="eventsOn(d).length>3">
                                        <div class="px-1 text-[10px] text-gray-500" x-text="'+'+(eventsOn(d).length-3)"></div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Week (hour grid, Google-style) --}}
            <div x-show="view==='week'" class="mt-4 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <div class="overflow-x-auto">
                    <div class="min-w-[48rem]">
                        {{-- Day headers --}}
                        <div class="grid border-b border-gray-200" style="grid-template-columns:3.5rem repeat(7,minmax(0,1fr))">
                            <div></div>
                            <template x-for="(d,i) in weekDays()" :key="'wh'+i">
                                <div class="border-l border-gray-100 py-1.5 text-center text-xs font-medium"
                                    :class="isToday(d)?'text-gray-900':'text-gray-500'" x-text="fmtDay(d)"></div>
                            </template>
                        </div>
                        {{-- All-day strip --}}
                        <div class="grid border-b border-gray-200 bg-gray-50/50" style="grid-template-columns:3.5rem repeat(7,minmax(0,1fr))">
                            <div class="px-1 py-1 text-[10px] text-gray-400">{{ __('calendar.ui.all_day') }}</div>
                            <template x-for="(d,i) in weekDays()" :key="'wa'+i">
                                <div class="min-h-[1.5rem] space-y-0.5 border-l border-gray-100 p-0.5">
                                    <template x-for="e in allDayOn(d)" :key="e.id+'-'+e.instance">
                                        <button @click.stop="openEditor(e.id)" class="block w-full truncate rounded px-1 text-left text-[10px] text-white" :style="`background:${e.color}`" x-text="e.title||'—'"></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                        {{-- Time grid --}}
                        <div class="max-h-[68vh] overflow-y-auto">
                            <div class="grid" style="grid-template-columns:3.5rem repeat(7,minmax(0,1fr))">
                                {{-- hour gutter --}}
                                <div class="relative" :style="`height:${24*hourPx}px`">
                                    <template x-for="h in hours()" :key="'wg'+h">
                                        <div class="absolute right-1 -translate-y-1/2 text-[10px] text-gray-400" :style="`top:${h*hourPx}px`" x-text="h===0?'':fmtHour(h)"></div>
                                    </template>
                                </div>
                                <template x-for="(d,i) in weekDays()" :key="'wc'+i">
                                    <div class="relative border-l border-gray-100" :style="`height:${24*hourPx}px`" @click="openNewAt(d,$event)">
                                        <template x-for="h in hours()" :key="'wl'+i+'-'+h">
                                            <div class="absolute inset-x-0 border-t border-gray-100" :style="`top:${h*hourPx}px`"></div>
                                        </template>
                                        <template x-for="e in timedEventsOn(d)" :key="e.id+'-'+e.instance">
                                            <button @click.stop="openEditor(e.id)" class="absolute inset-x-0.5 overflow-hidden rounded px-1 text-left text-[10px] leading-tight text-white" :style="eventStyle(e)+`;background:${e.color}`">
                                                <span class="font-medium" x-text="fmtTime(e.start)"></span>
                                                <span class="block truncate" x-text="e.title||'—'"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Day (hour grid) --}}
            <div x-show="view==='day'" class="mt-4 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <div class="min-w-[360px]">
                {{-- All-day strip --}}
                <div class="flex border-b border-gray-200 bg-gray-50/50">
                    <div class="w-14 shrink-0 px-1 py-1 text-[10px] text-gray-400">{{ __('calendar.ui.all_day') }}</div>
                    <div class="min-h-[1.5rem] flex-1 space-y-0.5 p-0.5">
                        <template x-for="e in allDayOn(cursor)" :key="e.id+'-'+e.instance">
                            <button @click.stop="openEditor(e.id)" class="block w-full truncate rounded px-1 text-left text-[11px] text-white" :style="`background:${e.color}`" x-text="e.title||'—'"></button>
                        </template>
                    </div>
                </div>
                <div class="max-h-[68vh] overflow-y-auto">
                    <div class="flex">
                        <div class="relative w-14 shrink-0" :style="`height:${24*hourPx}px`">
                            <template x-for="h in hours()" :key="'dg'+h">
                                <div class="absolute right-1 -translate-y-1/2 text-[10px] text-gray-400" :style="`top:${h*hourPx}px`" x-text="h===0?'':fmtHour(h)"></div>
                            </template>
                        </div>
                        <div class="relative flex-1 border-l border-gray-100" :style="`height:${24*hourPx}px`" @click="openNewAt(cursor,$event)">
                            <template x-for="h in hours()" :key="'dl'+h">
                                <div class="absolute inset-x-0 border-t border-gray-100" :style="`top:${h*hourPx}px`"></div>
                            </template>
                            <template x-for="e in timedEventsOn(cursor)" :key="e.id+'-'+e.instance">
                                <button @click.stop="openEditor(e.id)" class="absolute inset-x-1 overflow-hidden rounded px-1.5 text-left text-xs leading-tight text-white" :style="eventStyle(e)+`;background:${e.color}`">
                                    <span class="font-medium" x-text="fmtTime(e.start)"></span>
                                    <span class="block truncate" x-text="e.title||'—'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            {{-- Agenda --}}
            <div x-show="view==='agenda'" class="mt-4 rounded-lg border border-gray-200 bg-white">
                <ul class="divide-y divide-gray-100">
                    <template x-for="e in agenda()" :key="e.id+'-'+e.instance">
                        <li>
                            <button @click="openEditor(e.id)" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background:${e.color}`"></span>
                                <span class="w-40 shrink-0 text-sm text-gray-500" x-text="new Date(e.start.replace(' ','T')).toLocaleString(locale,{weekday:'short',day:'numeric',month:'short',hour:e.all_day?undefined:'2-digit',minute:e.all_day?undefined:'2-digit'})"></span>
                                <span class="truncate text-sm font-medium text-gray-900" x-text="e.title||'—'"></span>
                            </button>
                        </li>
                    </template>
                    <template x-if="agenda().length===0">
                        <li class="px-4 py-8 text-center text-sm text-gray-500">{{ __('calendar.ui.no_events') }}</li>
                    </template>
                </ul>
            </div>
        </div>

        {{-- Editor modal --}}
        <div x-show="editor" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="editor=false">
            <div class="absolute inset-0 bg-gray-900/40" @click="editor=false"></div>
            <div class="relative my-8 w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900" x-text="form.read_only ? '{{ __('calendar.ui.view_event') }}' : (form.id ? '{{ __('calendar.ui.edit_event') }}' : '{{ __('calendar.ui.new_event') }}')"></h3>
                <p x-show="form.read_only" x-cloak class="mt-1 flex items-center gap-1 text-xs text-gray-400"><x-icon name="lock-closed" class="h-3.5 w-3.5" /> {{ __('calendar.ui.read_only_note') }}</p>
                <div class="mt-4 space-y-3" :class="form.read_only && 'pointer-events-none opacity-80'">
                    <input x-model="form.summary" placeholder="{{ __('calendar.ui.title') }}" class="w-full rounded-md border-gray-300 text-sm">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" x-model="form.all_day" class="rounded border-gray-300"> {{ __('calendar.ui.all_day') }}
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-xs text-gray-500">{{ __('calendar.ui.start') }}
                            <input :type="form.all_day?'date':'datetime-local'" x-model="form.start" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        </label>
                        <label class="text-xs text-gray-500">{{ __('calendar.ui.end') }}
                            <input :type="form.all_day?'date':'datetime-local'" x-model="form.end" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        </label>
                    </div>
                    <div x-show="!form.all_day">
                        <label class="text-xs text-gray-500">{{ __('calendar.ui.timezone') }}
                            <select x-model="form.timezone" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                                @foreach ($timezones as $zone)
                                    <option value="{{ $zone }}">{{ $zone }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <input x-model="form.location" placeholder="{{ __('calendar.ui.location') }}" class="w-full rounded-md border-gray-300 text-sm">
                    <textarea x-model="form.description" placeholder="{{ __('calendar.ui.description') }}" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-xs text-gray-500">{{ __('calendar.ui.repeat') }}
                            <select x-model="form.rrule" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('calendar.ui.repeats.none') }}</option>
                                <option value="FREQ=DAILY">{{ __('calendar.ui.repeats.daily') }}</option>
                                <option value="FREQ=WEEKLY">{{ __('calendar.ui.repeats.weekly') }}</option>
                                <option value="FREQ=MONTHLY">{{ __('calendar.ui.repeats.monthly') }}</option>
                                <option value="FREQ=YEARLY">{{ __('calendar.ui.repeats.yearly') }}</option>
                                <template x-if="form.rrule && !['FREQ=DAILY','FREQ=WEEKLY','FREQ=MONTHLY','FREQ=YEARLY'].includes(form.rrule)">
                                    <option :value="form.rrule" x-text="form.rrule"></option>
                                </template>
                            </select>
                        </label>
                        <label class="text-xs text-gray-500">{{ __('calendar.ui.reminder') }}
                            <select x-model="form.reminder_minutes" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('calendar.ui.reminders.none') }}</option>
                                <option value="0">{{ __('calendar.ui.reminders.at_time') }}</option>
                                <option value="5">{{ __('calendar.ui.reminders.5') }}</option>
                                <option value="15">{{ __('calendar.ui.reminders.15') }}</option>
                                <option value="30">{{ __('calendar.ui.reminders.30') }}</option>
                                <option value="60">{{ __('calendar.ui.reminders.60') }}</option>
                                <option value="1440">{{ __('calendar.ui.reminders.1440') }}</option>
                            </select>
                        </label>
                    </div>
                    <template x-if="!form.read_only">
                        <select x-model="form.calendar_id" class="w-full rounded-md border-gray-300 text-sm">
                            <template x-for="c in ownCalendars()" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                        </select>
                    </template>
                    <template x-if="form.read_only">
                        <div class="text-sm text-gray-600" x-text="calName(form.calendar_id)"></div>
                    </template>
                </div>
                <div class="mt-5 flex items-center justify-between">
                    <button x-show="form.id && !form.read_only" @click="destroy()" class="text-sm text-red-600 hover:text-red-700">{{ __('calendar.ui.delete') }}</button>
                    <div class="ml-auto flex gap-2">
                        <button @click="editor=false" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" x-text="form.read_only ? '{{ __('calendar.ui.close') }}' : '{{ __('calendar.ui.cancel') }}'"></button>
                        <button x-show="!form.read_only" @click="save()" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('calendar.ui.save') }}</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Calendar add/edit modal (name + colour; colour-only for read-only) --}}
        <div x-show="calModal.open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="calModal.open=false">
            <div class="absolute inset-0 bg-gray-900/40" @click="calModal.open=false"></div>
            <div class="relative my-16 w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900" x-text="calModal.id ? '{{ __('calendar.ui.edit_calendar') }}' : '{{ __('calendar.ui.add_calendar') }}'"></h3>
                <form @submit.prevent="saveCalModal()" class="mt-3 space-y-3">
                    <template x-if="!calModal.readOnly">
                        <div>
                            <label class="text-xs text-gray-500">{{ __('calendar.ui.name') }}</label>
                            <input x-ref="calName" x-model="calModal.name" type="text" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </template>
                    <p x-show="calModal.readOnly" x-cloak class="text-xs text-gray-500">{{ __('calendar.ui.color_only_note') }}</p>
                    <div>
                        <label class="text-xs text-gray-500">{{ __('calendar.ui.color') }}</label>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <template x-for="col in palette" :key="col">
                                <button type="button" @click="calModal.color=col" class="h-6 w-6 rounded-full ring-2 ring-offset-1" :style="`background:${col}`" :class="calModal.color===col ? 'ring-gray-900' : 'ring-transparent'"></button>
                            </template>
                            <input type="color" x-model="calModal.color" class="h-8 w-10 cursor-pointer rounded border border-gray-300 bg-white p-0.5">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="calModal.open=false" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('calendar.ui.cancel') }}</button>
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('calendar.ui.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Import-from-URL / subscribe modal --}}
        <div x-show="linkModal.open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="linkModal.open=false">
            <div class="absolute inset-0 bg-gray-900/40" @click="linkModal.open=false"></div>
            <div class="relative my-16 w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900" x-text="linkModal.mode==='subscribe' ? '{{ __('calendar.ui.subscribe_title') }}' : '{{ __('calendar.ui.import_url_title') }}'"></h3>
                <form @submit.prevent="saveLinkModal()" class="mt-3 space-y-3">
                    <div>
                        <label class="text-xs text-gray-500">{{ __('calendar.ui.url') }}</label>
                        <input x-model="linkModal.url" type="url" placeholder="https://…" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div x-show="linkModal.mode==='subscribe'">
                        <label class="text-xs text-gray-500">{{ __('calendar.ui.name') }}</label>
                        <input x-model="linkModal.name" type="text" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <p x-show="linkModal.error" x-cloak class="text-xs text-red-600" x-text="linkModal.error"></p>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="linkModal.open=false" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('calendar.ui.cancel') }}</button>
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('calendar.ui.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @include('partials.share-modal')

        {{-- Confirm modal (delete calendar / event) --}}
        <div x-show="confirmModal.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="confirmModal.open=false">
            <div class="absolute inset-0 bg-gray-900/40" @click="confirmModal.open=false"></div>
            <div class="relative my-24 w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('calendar.ui.confirm_delete_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600" x-text="confirmModal.message"></p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="confirmModal.open=false" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('calendar.ui.cancel') }}</button>
                    <button type="button" @click="doConfirm()" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('calendar.ui.delete') }}</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
