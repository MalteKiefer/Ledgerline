            <x-button variant="primary" icon="plus" class="w-full cursor-pointer" @click="openNew()">{{ __('calendar.ui.new_event') }}</x-button>
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('calendar.ui.calendars') }}</h2>
                    <button @click="addCalendar()" class="cursor-pointer text-gray-400 hover:text-gray-700" title="{{ __('calendar.ui.new_calendar') }}"><x-icon name="plus" class="h-4 w-4" /></button>
                </div>
                <ul class="mt-2 space-y-1 text-sm">
                    <template x-for="c in ownCalendars()" :key="c.id">
                        <li class="group flex items-center justify-between gap-1">
                            <label class="flex min-w-0 items-center gap-2">
                                <input type="checkbox" :checked="!hidden.has(c.id)" @change="toggleCalendar(c.id)"
                                    class="rounded border-gray-300" :style="`color:${c.color}`">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background:${c.color}`"></span>
                                <span class="truncate text-gray-700" x-text="c.name"></span>
                            </label>
                            <span class="flex md:hidden shrink-0 gap-1 md:group-hover:flex">
                                <button @click="openShare('calendars', c.id, c.name)" class="cursor-pointer text-gray-400 hover:text-gray-700" title="{{ __('shares.share') }}"><x-icon name="share" class="h-4 w-4" /></button>
                                <button @click="editCalendar(c)" class="cursor-pointer text-gray-400 hover:text-gray-700" title="{{ __('calendar.ui.edit_calendar') }}"><x-icon name="pencil" class="h-4 w-4" /></button>
                                <button @click="deleteCalendar(c)" class="cursor-pointer text-gray-400 hover:text-red-600" title="{{ __('calendar.ui.delete') }}"><x-icon name="x-mark" class="h-4 w-4" /></button>
                            </span>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Read-only, generated calendars (birthdays, anniversaries, holidays) --}}
            <div x-show="otherCalendars().length" x-cloak>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('calendar.ui.other_calendars') }}</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    <template x-for="c in otherCalendars()" :key="c.id">
                        <li class="group flex items-center justify-between gap-1">
                            <label class="flex min-w-0 items-center gap-2">
                                <input type="checkbox" :checked="!hidden.has(c.id)" @change="toggleCalendar(c.id)"
                                    class="rounded border-gray-300" :style="`color:${c.color}`">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background:${c.color}`"></span>
                                <span class="truncate text-gray-700" x-text="c.name"></span>
                            </label>
                            <span class="flex shrink-0 items-center gap-1">
                                <button @click="editCalendar(c)" class="inline-flex cursor-pointer md:hidden text-gray-400 hover:text-gray-700 md:group-hover:inline-flex" title="{{ __('calendar.ui.color') }}"><x-icon name="pencil" class="h-4 w-4" /></button>
                                <x-icon name="lock-closed" class="h-3.5 w-3.5 text-gray-400" title="{{ __('calendar.ui.read_only') }}" />
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
                <button @click="importFromUrl()" class="block cursor-pointer text-left text-sm text-gray-600 hover:text-gray-900">{{ __('calendar.ui.import_url') }}</button>
                <button @click="subscribe()" class="block cursor-pointer text-left text-sm text-gray-600 hover:text-gray-900">{{ __('calendar.ui.subscribe') }}</button>
            </div>
