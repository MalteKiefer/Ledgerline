<x-layouts.app :title="__('calendar.ui.heading')">
    @php $cfg = [
        'dataUrl' => route('calendar.data'),
        'eventsUrl' => route('calendar.events.store'),
        'eventBase' => url('calendar/events'),
        'calUrl' => route('calendar.calendars.store'),
        'calBase' => url('calendar/calendars'),
        'importUrl' => route('calendar.import'),
        'exportUrl' => route('calendar.export'),
        'token' => csrf_token(),
        'confirmDelete' => __('calendar.ui.delete_confirm'),
        'newCalendar' => __('calendar.ui.new_calendar'),
        'renameCalendar' => __('calendar.ui.rename_calendar'),
        'confirmDeleteCalendar' => __('calendar.ui.delete_calendar_confirm'),
    ]; @endphp
    <div x-data="calendarPage(@js($cfg))" x-init="init()" class="flex flex-col gap-4 md:flex-row">
        {{-- Sidebar --}}
        <aside class="w-full shrink-0 space-y-4 md:w-56">
            <button @click="openNew()" class="w-full rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                {{ __('calendar.ui.new_event') }}
            </button>
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('calendar.ui.calendars') }}</h2>
                    <button @click="addCalendar()" class="text-gray-400 hover:text-gray-700" title="{{ __('calendar.ui.new_calendar') }}">+</button>
                </div>
                <ul class="mt-2 space-y-1 text-sm">
                    <template x-for="c in calendars" :key="c.id">
                        <li class="group flex items-center justify-between gap-1">
                            <label class="flex min-w-0 items-center gap-2">
                                <input type="checkbox" :checked="!hidden.has(c.id)" @change="toggleCalendar(c.id)"
                                    class="rounded border-gray-300" :style="`color:${c.color}`">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background:${c.color}`"></span>
                                <span class="truncate text-gray-700" x-text="c.name"></span>
                            </label>
                            <span class="hidden shrink-0 gap-1 group-hover:flex">
                                <template x-if="c.read_only">
                                    <span class="text-[10px] text-gray-400" title="{{ __('calendar.ui.read_only') }}">🔒</span>
                                </template>
                                <button @click="renameCalendar(c)" class="text-gray-400 hover:text-gray-700" title="{{ __('calendar.ui.rename_calendar') }}">✎</button>
                                <button @click="deleteCalendar(c)" class="text-gray-400 hover:text-red-600" title="{{ __('calendar.ui.delete') }}">✕</button>
                            </span>
                        </li>
                    </template>
                </ul>
            </div>
            <div class="space-y-2 border-t border-gray-100 pt-3">
                <a :href="cfg.exportUrl" class="block text-sm text-gray-600 hover:text-gray-900">{{ __('calendar.ui.export') }}</a>
                <label class="block cursor-pointer text-sm text-gray-600 hover:text-gray-900">
                    {{ __('calendar.ui.import') }}
                    <input type="file" accept=".ics,text/calendar" class="hidden" @change="importFile($event)">
                </label>
            </div>
        </aside>

        {{-- Main --}}
        <div class="min-w-0 flex-1">
            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-1">
                    <button @click="step(-1)" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('calendar.ui.prev') }}">‹</button>
                    <button @click="today()" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">{{ __('calendar.ui.today') }}</button>
                    <button @click="step(1)" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50" title="{{ __('calendar.ui.next') }}">›</button>
                    <h2 class="ml-2 text-base font-semibold text-gray-900" x-text="title"></h2>
                </div>
                <div class="inline-flex rounded-md border border-gray-300 text-sm">
                    @foreach (['month', 'week', 'day', 'agenda'] as $v)
                        <button @click="setView('{{ $v }}')" :class="view==='{{ $v }}'?'bg-gray-900 text-white':'text-gray-700 hover:bg-gray-50'"
                            class="px-3 py-1.5 first:rounded-l-md last:rounded-r-md">{{ __("calendar.ui.$v") }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Month --}}
            <div x-show="view==='month'" class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="grid grid-cols-7 border-b border-gray-200 text-center text-xs font-medium text-gray-500">
                    <template x-for="(d,i) in weekDays()" :key="'h'+i">
                        <div class="py-2" x-text="fmtWeekday(d)"></div>
                    </template>
                </div>
                <div class="grid grid-cols-7">
                    <template x-for="(d,i) in monthGrid()" :key="'d'+i">
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
            </div>

            {{-- Week --}}
            <div x-show="view==='week'" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-7">
                <template x-for="(d,i) in weekDays()" :key="'w'+i">
                    <div class="rounded-lg border border-gray-200 bg-white">
                        <div class="border-b border-gray-100 px-2 py-1.5 text-xs font-medium"
                            :class="isToday(d)?'text-gray-900':'text-gray-500'" x-text="fmtDay(d)"></div>
                        <div @click="openNew(d)" class="min-h-[120px] cursor-pointer space-y-1 p-1.5 hover:bg-gray-50">
                            <template x-for="e in eventsOn(d)" :key="e.id+'-'+e.instance">
                                <button @click.stop="openEditor(e.id)" class="block w-full truncate rounded px-1.5 py-1 text-left text-[11px] text-white" :style="`background:${e.color}`">
                                    <span x-show="!e.all_day" x-text="fmtTime(e.start)+' '"></span><span x-text="e.title||'—'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Day --}}
            <div x-show="view==='day'" class="mt-4 rounded-lg border border-gray-200 bg-white">
                <div @click="openNew(cursor)" class="min-h-[200px] cursor-pointer divide-y divide-gray-100 hover:bg-gray-50/40">
                    <template x-for="e in eventsOn(cursor)" :key="e.id+'-'+e.instance">
                        <button @click.stop="openEditor(e.id)" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background:${e.color}`"></span>
                            <span class="w-24 shrink-0 text-sm text-gray-500" x-text="e.all_day?'{{ __('calendar.ui.all_day') }}':fmtTime(e.start)"></span>
                            <span class="truncate text-sm font-medium text-gray-900" x-text="e.title||'—'"></span>
                        </button>
                    </template>
                    <template x-if="eventsOn(cursor).length===0">
                        <p class="px-4 py-8 text-center text-sm text-gray-500">{{ __('calendar.ui.no_events') }}</p>
                    </template>
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
                <h3 class="text-base font-semibold text-gray-900" x-text="form.id ? '{{ __('calendar.ui.edit_event') }}' : '{{ __('calendar.ui.new_event') }}'"></h3>
                <div class="mt-4 space-y-3">
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
                    <select x-model="form.calendar_id" class="w-full rounded-md border-gray-300 text-sm">
                        <template x-for="c in calendars.filter(c=>!c.read_only)" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                    </select>
                </div>
                <div class="mt-5 flex items-center justify-between">
                    <button x-show="form.id" @click="destroy()" class="text-sm text-red-600 hover:text-red-700">{{ __('calendar.ui.delete') }}</button>
                    <div class="ml-auto flex gap-2">
                        <button @click="editor=false" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('calendar.ui.cancel') }}</button>
                        <button @click="save()" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('calendar.ui.save') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
